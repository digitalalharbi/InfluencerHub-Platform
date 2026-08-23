<?php

namespace App\Mail;

use App\Domain\Communications\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * بريد إشعار عام — عنوان + ملخّص + زر إجراء (رابط عميق) بقالب عربي RTL موسوم.
 * قابل للجدولة في الطابور. صنف بريد لا Mail::raw حتى يُرصَد في الاختبارات ويُقولَب.
 */
class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function build(): self
    {
        $n = $this->notification;
        // الرابط في الإشعار مسار نسبي؛ نبنيه مطلقًا للبريد.
        $url = null;
        if ($n->action_url) {
            $url = str_starts_with($n->action_url, 'http')
                ? $n->action_url
                : rtrim((string) config('app.url'), '/') . '/' . ltrim($n->action_url, '/');
        }

        return $this->subject($n->title . ' — إنفلونسر هَب')
            ->view('mail.notification', [
                'title' => $n->title,
                'body' => $n->body,
                'url' => $url,
                'cta' => 'فتح في إنفلونسر هَب',
            ]);
    }
}
