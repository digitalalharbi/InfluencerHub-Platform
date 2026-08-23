<?php

namespace App\Domain\Communications\Channels;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Support\DeliveryOutcome;
use App\Domain\Identity\Models\User;

/**
 * قناة الرسائل القصيرة. البنية جاهزة؛ الإرسال يُنفَّذ حين يُربَط مزوّد SMS حقيقي.
 */
class SmsChannel implements DeliveryChannel
{
    public function key(): string { return 'sms'; }

    public function provider(): string { return 'sms'; }

    public function available(): bool
    {
        return (bool) config('channels.sms.enabled');
    }

    public function recipientFor(User $user): ?string
    {
        return $user->phone ?: null;
    }

    public function send(Notification $notification, User $user, string $recipient): DeliveryOutcome
    {
        return DeliveryOutcome::waitingForCredentials('لم تُفعَّل قناة الرسائل القصيرة بعد');
    }
}
