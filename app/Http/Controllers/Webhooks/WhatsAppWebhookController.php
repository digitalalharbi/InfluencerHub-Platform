<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Communications\Models\NotificationDeliveryAttempt;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ويبهوك واتساب (Meta Cloud API).
 *  - GET: تحقّق الاشتراك (hub.challenge) بمطابقة verify_token.
 *  - POST: أحداث حالة الرسائل (sent/delivered/read/failed) مع تحقّق توقيع
 *    X-Hub-Signature-256 (HMAC-SHA256 بالـ app_secret)، وتحديث محاولة التسليم
 *    بمعرّف الرسالة، بلا تنازل عن حالة أعلى (idempotent).
 *
 * لا نبني صندوق وارد؛ نعالج تحديثات الحالة فقط. نعيد 200 دائمًا لأحداث صحيحة
 * حتى لا يُعيد Meta الإرسال؛ ونرفض التوقيع غير الصحيح بـ403.
 */
class WhatsAppWebhookController extends Controller
{
    /** رُتب الحالات — لا نتنازل عن حالة أعلى إلى أدنى. */
    private const RANK = ['queued' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4, 'failed' => 5];

    public function verify(Request $r)
    {
        $expected = config('channels.whatsapp.verify_token');
        if ($r->query('hub_mode') === 'subscribe'
            && $expected
            && hash_equals((string) $expected, (string) $r->query('hub_verify_token'))) {
            return response((string) $r->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('forbidden', 403);
    }

    public function receive(Request $r)
    {
        if (! $this->validSignature($r)) {
            return response('invalid signature', 403);
        }

        foreach ($r->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['statuses'] ?? [] as $status) {
                    $this->applyStatus($status);
                }
            }
        }

        return response('ok', 200);
    }

    private function validSignature(Request $r): bool
    {
        $secret = config('channels.whatsapp.app_secret');
        if (! $secret) {
            // بلا سرّ لا يمكن التحقّق — نرفض بدل الثقة العمياء.
            Log::warning('whatsapp webhook rejected: no app_secret configured');
            return false;
        }
        $header = (string) $r->header('X-Hub-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $r->getContent(), (string) $secret);

        return $header !== '' && hash_equals($expected, $header);
    }

    private function applyStatus(array $status): void
    {
        $id = $status['id'] ?? null;
        $to = $status['status'] ?? null;
        if (! $id || ! isset(self::RANK[$to])) {
            return;
        }

        // نبحث عبر المستأجرين (الويبهوك بلا سياق) ثم نحدّث ضمن مستأجر الصف.
        $attempt = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::where('provider_message_id', $id)->first());
        if (! $attempt) {
            return;
        }

        TenantContext::withTenant($attempt->tenant_id, function () use ($attempt, $to, $status) {
            $currentRank = self::RANK[$attempt->status] ?? 0;
            if (self::RANK[$to] < $currentRank) {
                return; // idempotent: لا تنازل عن حالة أعلى
            }
            $now = now();
            $attempt->update([
                'status' => $to,
                'delivered_at' => in_array($to, ['delivered', 'read'], true) ? ($attempt->delivered_at ?? $now) : $attempt->delivered_at,
                'read_at' => $to === 'read' ? ($attempt->read_at ?? $now) : $attempt->read_at,
                'failed_at' => $to === 'failed' ? ($attempt->failed_at ?? $now) : $attempt->failed_at,
                'failure_code' => $to === 'failed' ? (string) ($status['errors'][0]['code'] ?? 'failed') : $attempt->failure_code,
            ]);
        });
    }
}
