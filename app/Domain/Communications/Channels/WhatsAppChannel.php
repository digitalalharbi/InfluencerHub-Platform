<?php

namespace App\Domain\Communications\Channels;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Support\DeliveryOutcome;
use App\Domain\Identity\Models\User;

/**
 * قناة واتساب (Meta Cloud API). البنية جاهزة؛ الإرسال عبر القالب يُنفَّذ في
 * وحدة مزوّد واتساب. متاحة فقط حين تُفعَّل ووُجدت بيانات اعتماد Cloud API.
 */
class WhatsAppChannel implements DeliveryChannel
{
    public function key(): string { return 'whatsapp'; }

    public function provider(): string { return 'whatsapp_cloud'; }

    public function available(): bool
    {
        return (bool) config('channels.whatsapp.enabled')
            && filled(config('channels.whatsapp.phone_number_id'))
            && filled(config('channels.whatsapp.access_token'));
    }

    public function recipientFor(User $user): ?string
    {
        return $user->phone ?: null; // يُطبَّع إلى E.164 في وحدة المزوّد
    }

    public function send(Notification $notification, User $user, string $recipient): DeliveryOutcome
    {
        return DeliveryOutcome::waitingForCredentials('لم تُفعَّل قناة واتساب بعد');
    }
}
