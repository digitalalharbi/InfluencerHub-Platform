<?php

namespace App\Domain\Communications\Inbox;

use App\Domain\Integrations\Models\IntegrationExternalEvent;
use App\Domain\Integrations\Models\IntegrationWebhookEvent;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Carbon;

/**
 * ابتلاع الرسائل الواردة (COMMS-01) — واتساب الآن. idempotent عبر IntegrationWebhookEvent
 * (unique provider,event_id): الحدث نفسه لا يُعالَج مرّتين. يحلّ المستأجر المالك من رقم المرسِل،
 * فإن طابق مبدعًا/عميلًا يُسجَّل حدثًا حقيقيًّا (message_in) يغذّي الخطّ الزمني؛ وإلّا يُسجَّل في
 * سجلّ الويبهوك فقط (status=skipped) بلا صفّ خطّ زمنيّ — لا نلفّق مستأجرًا ولا مطابقة.
 */
class InboundMessageService
{
    public const PROVIDER = 'whatsapp_cloud';

    public const CAPABILITY = 'whatsapp';

    public function __construct(private ContactMatcher $matcher) {}

    /**
     * يعالج رسالة واردة واحدة من حمولة Meta. @param array<string,mixed> $message
     *
     * @return ?IntegrationExternalEvent الحدث المُنشأ (أو null إن تكرّر/لم يُطابَق)
     */
    public function ingest(array $message): ?IntegrationExternalEvent
    {
        $id = $message['id'] ?? null;
        $from = (string) ($message['from'] ?? '');
        if (! $id || $from === '') {
            return null;
        }

        // idempotency: أوّل استقبال يُنشئ السجلّ؛ أيّ تكرار لاحق يتوقّف.
        $hook = IntegrationWebhookEvent::firstOrCreate(
            ['provider' => self::PROVIDER, 'event_id' => (string) $id],
            ['type' => $message['type'] ?? 'message', 'signature_valid' => true, 'status' => 'received',
                'payload' => $message, 'received_at' => now()],
        );
        if (! $hook->wasRecentlyCreated) {
            return null; // مُعالَج سابقًا
        }

        $match = $this->matcher->resolveAcrossTenants($from);
        if (! $match['tenant_id']) {
            $hook->update(['status' => 'skipped', 'processed_at' => now()]);

            return null; // لا مطابقة ⇒ لا صفّ خطّ زمنيّ (لا تلفيق)
        }

        $event = TenantContext::withTenant($match['tenant_id'], fn () => IntegrationExternalEvent::updateOrCreate(
            ['tenant_id' => $match['tenant_id'], 'provider' => self::PROVIDER, 'external_id' => (string) $id],
            [
                'capability' => self::CAPABILITY, 'kind' => 'message_in', 'direction' => 'inbound',
                'creator_id' => $match['creator_id'], 'client_id' => $match['client_id'],
                'occurred_at' => $this->timestamp($message),
                'title' => mb_substr($this->body($message), 0, 300),
                'summary' => mb_substr($this->body($message), 0, 500),
                'meta' => ['phone' => $from, 'wa_type' => $message['type'] ?? 'text'],
            ],
        ));

        $hook->update(['tenant_id' => $match['tenant_id'], 'status' => 'processed', 'processed_at' => now()]);

        return $event;
    }

    private function body(array $message): string
    {
        return match ($message['type'] ?? 'text') {
            'text' => (string) ($message['text']['body'] ?? ''),
            'button' => (string) ($message['button']['text'] ?? ''),
            'interactive' => (string) ($message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? ''),
            default => '['.($message['type'] ?? 'message').']',
        };
    }

    private function timestamp(array $message): Carbon
    {
        return isset($message['timestamp']) ? Carbon::createFromTimestamp((int) $message['timestamp']) : now();
    }
}
