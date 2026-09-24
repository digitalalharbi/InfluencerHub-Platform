<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * حلّ لغة الواجهة + استمراريّتها (ar|en).
 *
 * الأولوية: تفضيل المستخدم ← الجلسة ← الافتراضي. القيَم المدعومة فقط، وأيّ قيمة أخرى تُتجاهَل.
 * تُشارَك اللغة والاتّجاه وحزمة الترجمة إلى الواجهة (Inertia) في كل صفحة.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    /** الافتراضي للضيف = لغة التطبيق (عربي)، مع مشاركة الاتّجاه والترجمة. */
    public function test_guest_defaults_to_app_locale_and_shares_i18n(): void
    {
        $this->get('/features')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('locale', 'ar')
            ->where('dir', 'rtl')
            ->has('translations.navigation')
            ->has('translations.common'));
    }

    /** الضيف يبدّل إلى الإنجليزية → تُحفظ في الجلسة وتُطبَّق في الطلب التالي (dir=ltr). */
    public function test_guest_switch_persists_in_session(): void
    {
        $this->post('/locale', ['locale' => 'en'])->assertRedirect();
        $this->get('/features')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('locale', 'en')
            ->where('dir', 'ltr'));
    }

    /** المُصادَق يبدّل اللغة → تُحفظ في users.locale (تبقى بعد انتهاء الجلسة). */
    public function test_authenticated_switch_persists_to_user(): void
    {
        $u = User::create(['name' => 'م', 'email' => 'loc@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $this->actingAs($u)->post('/locale', ['locale' => 'en'])->assertRedirect();
        $this->assertSame('en', $u->fresh()->locale);
    }

    /** تفضيل المستخدم يعلو على الجلسة والافتراضي. */
    public function test_user_locale_takes_priority(): void
    {
        $u = User::create(['name' => 'م', 'email' => 'loc2@ex.com', 'password' => bcrypt('x'), 'is_active' => true, 'locale' => 'en']);
        $this->actingAs($u)->get('/features')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('locale', 'en')->where('dir', 'ltr'));
    }

    /** لغة غير مدعومة تُتجاهَل (لا ثقة بمدخل عشوائيّ) — تبقى اللغة الافتراضية. */
    public function test_unsupported_locale_is_ignored(): void
    {
        $this->post('/locale', ['locale' => 'fr'])->assertRedirect();
        $this->get('/features')->assertOk()->assertInertia(fn (Assert $p) => $p->where('locale', 'ar'));
    }

    /** اللغة تبقى بعد التنقّل بين الصفحات (نفس الجلسة). */
    public function test_locale_survives_navigation(): void
    {
        $this->post('/locale', ['locale' => 'en']);
        $this->get('/features')->assertInertia(fn (Assert $p) => $p->where('locale', 'en'));
        $this->get('/pricing')->assertInertia(fn (Assert $p) => $p->where('locale', 'en'));
    }
}
