<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\Channels\ChannelRegistry;
use App\Domain\Communications\Models\{Notification, NotificationDeliveryAttempt, NotificationPreference};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Log;

/**
 * موزّع التسليم: يأخذ إشعارًا مُنشأً ويحاول تسليمه عبر القنوات المُفعّلة في التفضيلات،
 * مسجّلًا لكل قناة محاولة تسليم بحالة صادقة (sent/queued/waiting_for_credentials/
 * failed/skipped) مع المزوّد والمستلِم ومعرّف الرسالة والطوابع الزمنية.
 *
 * القناة داخل التطبيق تُسلَّم دائمًا؛ القنوات الخارجية لا تُرسِل ما لم تكن available().
 */
class DeliveryDispatcher
{
    public function __construct(private ChannelRegistry $channels) {}

    /** يوزّع تسليم إشعار. يُنفَّذ داخل سياق مستأجر الإشعار. */
    public function dispatch(Notification $notification, NotificationPreference $pref): void
    {
        TenantContext::withTenant($notification->tenant_id, function () use ($notification, $pref) {
            $user = User::find($notification->user_id);
            if (! $user) {
                return;
            }

            foreach ($this->channels->all() as $key => $channel) {
                $enabled = (bool) ($pref->{$key} ?? false);

                // القناة المُعطّلة في التفضيلات: نُسجّل تخطّيًا صريحًا لِـ in_app فقط
                // (بقيّة القنوات لا محاولة لها أصلًا — لا ضجيج).
                if (! $enabled) {
                    if ($key === 'in_app') {
                        $this->record($notification, $channel->key(), $channel->provider(), null, 'skipped', detail: 'معطّلة في التفضيلات');
                    }
                    continue;
                }

                // توفّر المزوّد أوّلًا: «غير مهيّأة» حقيقة أسبق من «لا مستلِم».
                if (! $channel->available()) {
                    $this->record($notification, $channel->key(), $channel->provider(), $channel->recipientFor($user), 'waiting_for_credentials', detail: 'القناة غير مهيّأة بعد');
                    continue;
                }

                $recipient = $channel->recipientFor($user);
                if ($recipient === null) {
                    $this->record($notification, $channel->key(), $channel->provider(), null, 'skipped', detail: 'لا عنوان مستلِم لهذه القناة');
                    continue;
                }

                try {
                    $outcome = $channel->send($notification, $user, $recipient);
                } catch (\Throwable $e) {
                    // لا نُفشِل سير العمل الأساسي بفشل قناة؛ نُسجّله بأمان بلا أسرار.
                    Log::warning('notification channel send failed', ['channel' => $key, 'notification' => $notification->id, 'error' => $e->getMessage()]);
                    $this->record($notification, $channel->key(), $channel->provider(), $recipient, 'failed', failureCode: 'exception', detail: 'تعذّر الإرسال');
                    continue;
                }

                $this->record(
                    $notification, $channel->key(), $channel->provider(), $recipient,
                    $outcome->status, $outcome->providerMessageId, $outcome->failureCode, $outcome->detail,
                );
            }
        });
    }

    private function record(Notification $n, string $channel, string $provider, ?string $recipient, string $status, ?string $providerMessageId = null, ?string $failureCode = null, ?string $detail = null): void
    {
        $now = now();
        NotificationDeliveryAttempt::create([
            'tenant_id' => $n->tenant_id,
            'notification_id' => $n->id,
            'channel' => $channel,
            'provider' => $provider,
            'recipient' => $recipient,
            'provider_message_id' => $providerMessageId,
            'status' => $status,
            'queued_at' => in_array($status, ['queued', 'sent', 'delivered'], true) ? $now : null,
            'delivered_at' => in_array($status, ['sent', 'delivered'], true) ? $now : null,
            'failed_at' => $status === 'failed' ? $now : null,
            'failure_code' => $failureCode,
            'detail' => $detail,
            'attempted_at' => $now,
        ]);
    }
}
