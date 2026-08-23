<?php

namespace App\Domain\Communications\Channels;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Support\DeliveryOutcome;
use App\Domain\Identity\Models\User;

/**
 * قناة البريد. البنية جاهزة؛ الإرسال الفعلي يُنفَّذ في وحدة مزوّد البريد.
 * available() يعتمد على عَلَم صريح — بلا اعتماد لا ادّعاء تسليم.
 */
class EmailChannel implements DeliveryChannel
{
    public function key(): string { return 'email'; }

    public function provider(): string { return 'smtp'; }

    public function available(): bool
    {
        return (bool) config('channels.email.enabled');
    }

    public function recipientFor(User $user): ?string
    {
        return $user->email ?: null;
    }

    public function send(Notification $notification, User $user, string $recipient): DeliveryOutcome
    {
        // يُستبدَل بمنطق إرسال حقيقي في وحدة مزوّد البريد.
        return DeliveryOutcome::waitingForCredentials('لم تُفعَّل قناة البريد بعد');
    }
}
