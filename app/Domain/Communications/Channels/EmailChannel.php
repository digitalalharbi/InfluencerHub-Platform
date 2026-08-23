<?php

namespace App\Domain\Communications\Channels;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Support\DeliveryOutcome;
use App\Domain\Identity\Models\User;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;

/**
 * قناة البريد — إرسال فعلي عبر Laravel Mail (أي transport مُعَدّ).
 * متاحة فقط حين يُفعَّل العَلَم؛ المزوّد يعكس الـ mailer الحقيقي (smtp/log/...)
 * فيَعرِف المُدقّق إن كان تسليمًا حقيقيًا أم كتابةً إلى السجل.
 */
class EmailChannel implements DeliveryChannel
{
    public function key(): string { return 'email'; }

    public function provider(): string { return (string) config('mail.default', 'smtp'); }

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
        // البريد قابل للجدولة (ShouldQueue) — يُسلَّم للطابور/الـ transport.
        // فشل الـ transport يرمي فيلتقطه الموزّع ويُسجّله failed.
        Mail::to($recipient)->send(new NotificationMail($notification));

        return DeliveryOutcome::queued(detail: 'سُلِّم إلى ' . $this->provider());
    }
}
