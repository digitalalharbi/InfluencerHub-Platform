<?php

namespace App\Mail;

use App\Domain\Communications\Models\Notification;
use App\Support\Brand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * بريد إشعار عام — عنوان + ملخّص + بطاقة سياق اختياريّة + زرّ إجراء (رابط عميق).
 *
 * محترم للغة المستقبِل: العنوان/الأزرار/التذييل/التسميات تُترجَم إلى preferredLocale،
 * والقالب يضبط lang/dir تلقائيًّا. الحقول البنيويّة (سياق/حالة/طالب/موعد/أولويّة/رابط
 * ثانوي) تُقرأ من notification.data وتظهر **إن وُجدت فقط** — لا تُختلق بيانات لملء القالب.
 * قابل للجدولة. صنف بريد لا Mail::raw حتى يُرصَد في الاختبارات ويُقولَب.
 */
class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function build(): self
    {
        $n = $this->notification;
        $locale = $n->user?->preferredLocale() ?: (string) config('app.locale', 'ar');
        $this->locale($locale); // يضبط لغة عرض القالب (lang/dir + __() داخل الـview)

        // ترجمة نصوص البناء بلغة المستقبِل صراحةً (قد تختلف عن لغة الطلب الحالي).
        $t = fn (string $key, array $r = []) => trans($key, $r, $locale);
        $brand = Brand::name();

        // الرابط في الإشعار مسار نسبي؛ نبنيه مطلقًا للبريد.
        $url = null;
        if ($n->action_url) {
            $url = str_starts_with($n->action_url, 'http')
                ? $n->action_url
                : rtrim((string) config('app.url'), '/').'/'.ltrim($n->action_url, '/');
        }

        $data = is_array($n->data) ? $n->data : [];

        // بطاقة سياق اختياريّة — تُبنى من الحقول الموجودة فقط.
        $meta = [];
        foreach (['context', 'status', 'requester', 'due'] as $key) {
            if (! empty($data[$key])) {
                $meta[] = ['label' => $t("mail.meta.$key"), 'value' => (string) $data[$key]];
            }
        }

        // شارة أولويّة — فقط إن كانت قيمة معروفة (لا افتراض).
        $priority = null;
        $pk = $data['priority'] ?? null;
        $tones = [
            'urgent' => ['fg' => '#b42318', 'bg' => '#fef3f2'],
            'high' => ['fg' => '#b54708', 'bg' => '#fffaeb'],
            'normal' => ['fg' => '#3538cd', 'bg' => '#eef4ff'],
            'low' => ['fg' => '#475467', 'bg' => '#f2f4f7'],
        ];
        if (is_string($pk) && isset($tones[$pk])) {
            $priority = ['label' => $t("mail.priority.$pk")] + $tones[$pk];
        }

        // رابط ثانوي اختياريّ.
        $secondary = null;
        if (! empty($data['secondary_url'])) {
            $secondary = [
                'url' => (string) $data['secondary_url'],
                'label' => (string) ($data['secondary_label'] ?? $t('mail.secondary_link')),
            ];
        }

        return $this->subject($n->title.$t('mail.subject_suffix', ['brand' => $brand]))
            ->view('mail.notification', [
                'locale' => $locale, // يُمرَّر صراحةً كي يكون العرض حتميًّا (render وsend سواء)
                'title' => $n->title,
                'body' => $n->body,
                'url' => $url,
                'cta' => $t('mail.cta_open', ['brand' => $brand]),
                'meta' => $meta,
                'priority' => $priority,
                'secondary' => $secondary,
            ]);
    }
}
