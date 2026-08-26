<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\Notification;
use App\Domain\Identity\Models\User;
use App\Mail\NotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * تجربة البريد الاحترافيّة: احترام لغة المستقبِل (ar/en)، بطاقة السياق البنيويّة التي
 * تعرض الموجود فقط، وعدم تسريب أي مفتاح data خارج القائمة البيضاء (حصانة خصوصيّة للقالب).
 */
class EmailExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function notif(array $data = [], ?string $locale = 'ar', ?string $url = '/app/x'): Notification
    {
        $n = new Notification;
        $n->title = 'عنوان الحدث';
        $n->body = 'شرح موجز للحدث.';
        $n->action_url = $url;
        $n->data = $data;
        $n->category = 'general';
        $n->type = 'test.event';
        $u = new User;
        $u->name = 'مستقبِل';
        $u->locale = $locale;
        $n->setRelation('user', $u);

        return $n;
    }

    public function test_email_respects_arabic_recipient_locale(): void
    {
        $html = (new NotificationMail($this->notif([], 'ar')))->render();
        $this->assertStringContainsString('lang="ar"', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('الفتح في', $html);          // CTA عربي
        $this->assertStringContainsString('هذه رسالة آليّة', $html);    // تذييل عربي
        $this->assertStringContainsString('الخصوصية', $html);
    }

    public function test_email_respects_english_recipient_locale(): void
    {
        $html = (new NotificationMail($this->notif([], 'en')))->render();
        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertStringContainsString('Open in', $html);                        // CTA إنجليزي
        $this->assertStringContainsString('This is an automated message', $html);   // تذييل إنجليزي
        $this->assertStringContainsString('Privacy', $html);
        $this->assertStringNotContainsString('هذه رسالة آليّة', $html);             // لا عربيّة للمستقبِل الإنجليزي
    }

    public function test_english_subject_uses_english_recipient_locale(): void
    {
        $mail = (new NotificationMail($this->notif([], 'en')));
        $mail->render(); // يبني الرسالة
        $this->assertStringContainsString('عنوان الحدث', $mail->subject); // اسم الحدث لا يُترجَم
        $this->assertStringContainsString('— InfluencerHub', $mail->subject);
    }

    public function test_structured_meta_renders_only_present_fields(): void
    {
        $html = (new NotificationMail($this->notif([
            'context' => 'Nike · حملة الصيف',
            'status' => 'بانتظار قرارك',
            'priority' => 'high',
            // لا requester ولا due
        ], 'ar')))->render();

        $this->assertStringContainsString('Nike · حملة الصيف', $html);
        $this->assertStringContainsString('بانتظار قرارك', $html);
        $this->assertStringContainsString('السياق', $html);   // تسمية موجودة
        $this->assertStringContainsString('الحالة', $html);
        $this->assertStringContainsString('مرتفعة', $html);    // شارة الأولويّة
        $this->assertStringNotContainsString('الطالب', $html); // غائب ⇒ لا يُعرض
        $this->assertStringNotContainsString('الموعد', $html);
    }

    public function test_priority_pill_ignored_for_unknown_value(): void
    {
        $html = (new NotificationMail($this->notif(['priority' => 'bogus'], 'ar')))->render();
        $this->assertStringNotContainsString('عاجلة', $html);
        $this->assertStringNotContainsString('مرتفعة', $html);
    }

    /** حصانة خصوصيّة: القالب يعرض المفاتيح البيضاء فقط، فلا يُسرَّب أي حقل داخليّ عرضًا. */
    public function test_template_does_not_leak_non_whitelisted_data_keys(): void
    {
        $html = (new NotificationMail($this->notif([
            'context' => 'حملة نايك',
            'cost_minor' => '4000000',     // تكلفة داخليّة — يجب ألّا تظهر
            'margin' => '35%',             // هامش — يجب ألّا يظهر
            'internal_note' => 'مصدر سرّي', // ملاحظة داخليّة — يجب ألّا تظهر
        ], 'ar')))->render();

        $this->assertStringContainsString('حملة نايك', $html);      // مسموح
        $this->assertStringNotContainsString('4000000', $html);     // لا تسريب
        $this->assertStringNotContainsString('35%', $html);
        $this->assertStringNotContainsString('مصدر سرّي', $html);
    }

    public function test_fallback_link_present_when_url_set(): void
    {
        $html = (new NotificationMail($this->notif([], 'ar', '/app/contracts/9')))->render();
        $this->assertStringContainsString('انسخ هذا الرابط', $html); // fallback hint (ar)
        $this->assertStringContainsString('/app/contracts/9', $html);
    }
}
