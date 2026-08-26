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

    private function notif(array $data = [], ?string $locale = 'ar', ?string $url = '/app/x', string $name = 'محمد العتيبي'): Notification
    {
        $n = new Notification;
        $n->title = 'عنوان الحدث';
        $n->body = 'شرح موجز للحدث.';
        $n->action_url = $url;
        $n->data = $data;
        $n->category = 'general';
        $n->type = 'test.event';
        $u = new User;
        $u->name = $name;
        $u->locale = $locale;
        $n->setRelation('user', $u);

        return $n;
    }

    public function test_email_respects_arabic_recipient_locale(): void
    {
        $html = (new NotificationMail($this->notif([], 'ar')))->render();
        $this->assertStringContainsString('lang="ar"', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('عرض التفاصيل', $html);       // CTA عربي (افتراضي)
        $this->assertStringContainsString('هذه رسالة آليّة', $html);    // تذييل عربي
        $this->assertStringContainsString('الخصوصية', $html);
    }

    public function test_email_respects_english_recipient_locale(): void
    {
        $html = (new NotificationMail($this->notif([], 'en')))->render();
        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertStringContainsString('View details', $html);                   // CTA إنجليزي (افتراضي)
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

    public function test_personalized_greeting_uses_recipient_name(): void
    {
        $ar = (new NotificationMail($this->notif([], 'ar', '/app/x', 'سارة الزهراني')))->render();
        $this->assertStringContainsString('مرحبًا سارة الزهراني،', $ar);
        $en = (new NotificationMail($this->notif([], 'en', '/app/x', 'Sarah')))->render();
        $this->assertStringContainsString('Hello Sarah,', $en);
    }

    public function test_greeting_falls_back_when_name_is_email_or_missing(): void
    {
        $email = (new NotificationMail($this->notif([], 'ar', '/app/x', 'user@example.com')))->render();
        $this->assertStringContainsString('مرحبًا،', $email);          // لا نكشف اسم مستخدم البريد
        $this->assertStringNotContainsString('user@example.com', $email);
    }

    public function test_business_objects_render_with_human_labels_not_context(): void
    {
        $html = (new NotificationMail($this->notif([
            'objects' => [['type' => 'campaign', 'name' => 'صيف الرياض'], ['type' => 'creator', 'name' => 'نورة القحطاني']],
            'status' => 'بانتظار قرارك',
            'priority' => 'high',
        ], 'ar')))->render();

        $this->assertStringContainsString('الحملة', $html);            // تسمية العمل البشريّة
        $this->assertStringContainsString('صيف الرياض', $html);
        $this->assertStringContainsString('صانع المحتوى', $html);
        $this->assertStringContainsString('نورة القحطاني', $html);
        $this->assertStringContainsString('الحالة', $html);
        $this->assertStringContainsString('مرتفعة', $html);
        $this->assertStringNotContainsString('السياق', $html);         // لا مصطلح تقنيّ
        $this->assertStringNotContainsString('الطالب', $html);         // غائب ⇒ لا يُعرض
    }

    public function test_event_specific_cta_label_used_when_present(): void
    {
        $html = (new NotificationMail($this->notif(['cta_label' => 'مراجعة الترشيحات'], 'ar')))->render();
        $this->assertStringContainsString('مراجعة الترشيحات', $html);
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
            'objects' => [['type' => 'campaign', 'name' => 'حملة نايك']],
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
