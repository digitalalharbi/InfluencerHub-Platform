<?php

namespace App\Domain\Communications\Channels;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Support\DeliveryOutcome;
use App\Domain\Identity\Models\User;

/**
 * القناة داخل التطبيق — التسليم فوري ومضمون: صفّ الإشعار نفسه هو التسليم.
 * متاحة دائمًا.
 */
class InAppChannel implements DeliveryChannel
{
    public function key(): string { return 'in_app'; }

    public function provider(): string { return 'in_app'; }

    public function available(): bool { return true; }

    public function recipientFor(User $user): ?string { return 'user:' . $user->id; }

    public function send(Notification $notification, User $user, string $recipient): DeliveryOutcome
    {
        // لا نداء خارجي — الإشعار مُخزّن ويظهر في مركز الإشعارات فور إنشائه.
        return DeliveryOutcome::sent(detail: 'داخل التطبيق');
    }
}
