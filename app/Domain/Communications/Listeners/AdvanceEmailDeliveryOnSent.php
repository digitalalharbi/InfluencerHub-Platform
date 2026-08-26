<?php

namespace App\Domain\Communications\Listeners;

use App\Domain\Communications\Models\NotificationDeliveryAttempt;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Mail\Events\MessageSent;

/**
 * يقدّم حالة تسليم البريد queued→sent من حدث النقل الفعليّ (MessageSent) — لا ادّعاء.
 *
 * «sent» تعني: قَبِل النقل (SMTP/log/…) الرسالة فعلًا. لا نضع «delivered» أبدًا هنا لأن
 * التسليم الحقيقيّ يحتاج webhook من المزوّد (غير متاح) — فنبقى صادقين. يربط الرسالة
 * بالإشعار عبر ترويسة X-IH-Notification-Id التي يضعها NotificationMail.
 *
 * في الإنتاج (QUEUE_CONNECTION=database) يُسجَّل صفّ المحاولة «queued» قبل أن يرسل العامل،
 * فيجده هذا المستمع ويحدّثه. آمن التكرار: يحدّث فقط المحاولة التي ما زالت «queued».
 */
final class AdvanceEmailDeliveryOnSent
{
    public function handle(MessageSent $event): void
    {
        $headers = $event->message->getHeaders();
        if (! $headers->has('X-IH-Notification-Id')) {
            return;
        }
        $notificationId = (int) $headers->get('X-IH-Notification-Id')->getBodyAsString();
        if ($notificationId <= 0) {
            return;
        }

        // معرّف رسالة المزوّد إن توفّر — حقيقة النقل لا تخمين.
        $providerMessageId = $headers->has('Message-ID')
            ? trim($headers->get('Message-ID')->getBodyAsString(), '<> ')
            : null;

        TenantContext::withBypass(function () use ($notificationId, $providerMessageId) {
            $attempt = NotificationDeliveryAttempt::where('notification_id', $notificationId)
                ->where('channel', 'email')
                ->where('status', 'queued')
                ->latest('id')
                ->first();

            if (! $attempt) {
                return;
            }

            $attempt->update([
                'status' => 'sent', // سُلِّم إلى النقل — لا delivered (يلزم webhook مزوّد)
                'provider_message_id' => $providerMessageId ?: $attempt->provider_message_id,
                'attempted_at' => now(),
            ]);
        });
    }
}
