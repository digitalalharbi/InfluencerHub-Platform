<?php

namespace App\Domain\Communications\Inbox;

use App\Domain\Communications\WhatsApp\WhatsAppCloudClient;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * يُمثّل قنوات الاتّصال (واتساب/بريد/SMS) على سلّم جاهزية الأساس (COMMS-01) دون بنية موازية
 * ودون ادّعاء: الحالة تُشتقّ من إعداد حقيقيّ (config/channels) — مُهيّأ ⇒ connected (رمز موجود،
 * AUTH_VERIFIED)، غير مُهيّأ ⇒ waiting_for_credentials. درجات LIVE لا تُرفع إلا بعد إرسال/استقبال
 * حقيقيّ ناجح (last_success_sync_at / علامة live_write في meta). رموز القنوات في البيئة لا تُخزَّن هنا.
 */
class CommsConnectionService
{
    public const PROVIDERS = ['whatsapp_cloud', 'email', 'sms'];

    public function __construct(private WhatsAppCloudClient $whatsapp) {}

    /** يُطابق اتّصالات المستأجر مع إعداد القنوات الفعليّ (لا يخترع اتّصالًا متّصلًا). */
    public function syncFromConfig(int $tenantId): void
    {
        TenantContext::withTenant($tenantId, function () use ($tenantId) {
            foreach (self::PROVIDERS as $provider) {
                $configured = $this->configured($provider);
                $conn = IntegrationConnection::firstOrNew([
                    'tenant_id' => $tenantId, 'provider' => $provider, 'environment' => 'production',
                ]);
                // لا نخفض درجة اتّصال أثبت حياةً فعليّة سابقًا.
                if ($conn->exists && $conn->isConnected() && $configured) {
                    continue;
                }
                $conn->fill([
                    'status' => $configured ? 'connected' : 'waiting_for_credentials',
                    'health' => $configured ? 'healthy' : 'unknown',
                    'capabilities' => [self::capabilityFor($provider) => $configured ? 'connected' : 'waiting_for_credentials'],
                ])->save();
            }
        });
    }

    /** يُعلّم كتابةً حيّة ناجحة (رسالة صادرة) — يرفع الجاهزية إلى LIVE_WRITE_VERIFIED. */
    public function markLiveWrite(int $tenantId, string $provider): void
    {
        $this->touch($tenantId, $provider, liveWrite: true);
    }

    /** يُعلّم قراءةً حيّة ناجحة (رسالة واردة مُعالَجة) — يرفع إلى LIVE_READ_VERIFIED. */
    public function markLiveRead(int $tenantId, string $provider): void
    {
        $this->touch($tenantId, $provider, liveWrite: false);
    }

    private function touch(int $tenantId, string $provider, bool $liveWrite): void
    {
        TenantContext::withTenant($tenantId, function () use ($tenantId, $provider, $liveWrite) {
            $conn = IntegrationConnection::firstOrNew([
                'tenant_id' => $tenantId, 'provider' => $provider, 'environment' => 'production',
            ]);
            $meta = $conn->meta ?? [];
            if ($liveWrite) {
                $meta['live_write_verified_at'] = now()->toIso8601String();
            }
            $conn->fill([
                'status' => $conn->isConnected() ? $conn->status : 'connected',
                'health' => 'healthy',
                'last_success_sync_at' => now(),
                'meta' => $meta,
            ])->save();
        });
    }

    public function configured(string $provider): bool
    {
        return match ($provider) {
            'whatsapp_cloud' => (bool) config('channels.whatsapp.enabled') && $this->whatsapp->configured(),
            'email' => (bool) config('channels.email.enabled'),
            'sms' => (bool) config('channels.sms.enabled') && filled(config('services.twilio.sid')),
            default => false,
        };
    }

    private static function capabilityFor(string $provider): string
    {
        return match ($provider) {
            'whatsapp_cloud' => 'whatsapp',
            default => $provider,
        };
    }
}
