<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Onboarding\Models\DemoRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * الصفحات التعريفية والنظامية — كل رابط في الرأس والتذييل يجب أن يصل إلى صفحة حقيقية.
 *
 * سبب وجود هذا الملف: التذييل كان يحيل إلى مسارات غير موجودة، فيصطدم الزائر بـ404
 * في أول محاولة لفهم المنتَج. الاختبارات هنا تحرس وجود كل وجهة، وتحرس أن نموذج
 * العرض التوضيحي يحفظ سجلًّا فعليًّا لا أن يبتلع ما أُدخل فيه.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /**
     * @dataProvider publicPages
     */
    public function test_public_page_renders_expected_component(string $url, string $component): void
    {
        $this->get($url)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component($component));
    }

    public static function publicPages(): array
    {
        return [
            'المزايا' => ['/features', 'Public/Features'],
            'حلول العملاء' => ['/solutions/clients', 'Public/Solution'],
            'حلول الوكالات' => ['/solutions/agencies', 'Public/Solution'],
            'حلول صنّاع المحتوى' => ['/solutions/creators', 'Public/Solution'],
            'الأسعار' => ['/pricing', 'Public/Pricing'],
            'المساعدة' => ['/help', 'Public/Help'],
            'الشروط' => ['/terms', 'Public/Terms'],
            'الخصوصية' => ['/privacy', 'Public/Privacy'],
            'طلب عرض' => ['/demo', 'Public/Demo'],
        ];
    }

    public function test_features_page_carries_grouped_content(): void
    {
        $this->get('/features')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Features')
                ->has('groups', fn (Assert $g) => $g->has('0.title')->has('0.summary')->has('0.items')->etc()));
    }

    public function test_each_solution_page_carries_its_own_role_content(): void
    {
        foreach (['clients', 'agencies', 'creators'] as $role) {
            $this->get("/solutions/{$role}")->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('Public/Solution')
                    ->where('role', $role)
                    ->has('content.title')
                    ->has('content.pains')
                    ->has('content.caps')
                    ->has('content.ctaPrimary'));
        }
    }

    public function test_unknown_solution_role_is_not_routable(): void
    {
        $this->get('/solutions/ugc')->assertNotFound();
        $this->get('/solutions/publishers')->assertNotFound();
    }

    /** الباقات تُعرَض بالقدرة: لا مزوّد دفع مربوط، فلا حقل سعر يُختلق هنا. */
    public function test_pricing_lists_plans_by_capability_without_fabricated_prices(): void
    {
        $this->get('/pricing')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Pricing')
                ->has('plans', 3)
                ->has('plans.0.name')
                ->has('plans.0.includes')
                ->missing('plans.0.price'));
    }

    public function test_help_page_carries_grouped_questions(): void
    {
        $this->get('/help')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Help')
                ->has('groups', fn (Assert $g) => $g->has('0.title')->has('0.items.0.q')->has('0.items.0.a')->etc()));
    }

    /** الصفحات التسويقية ليست خاصة بالزوّار: روابط التذييل تُفتح من داخل النظام أيضًا. */
    public function test_authenticated_user_can_still_read_marketing_and_legal_pages(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer',
            'display_name' => 'م', 'status' => 'active', 'user_id' => $u->id]);
        TenantContext::reset();

        // الجذر يُحوِّل المصادَق إلى بوابته — وهذه الصفحات لا، وهذا هو الفرق المقصود
        $this->actingAs($u)->get('/')->assertRedirect('/creator');

        foreach (['/features', '/pricing', '/help', '/terms', '/privacy', '/demo', '/solutions/agencies'] as $url) {
            $this->actingAs($u)->get($url)->assertOk();
        }
    }

    public function test_demo_request_is_persisted_and_confirmed_with_reference(): void
    {
        $this->post('/demo', [
            'audience' => 'agency',
            'contact_name' => 'محمد',
            'email' => 'demo@ex.com',
            'phone' => '0500000000',
            'company_name' => 'وكالة',
            'role_title' => 'مدير تشغيل',
            'team_size' => '6-20',
            'preferred_time' => 'morning',
            'interests' => 'اعتماد المحتوى والمستحقات',
        ])->assertRedirect();

        $demo = DemoRequest::where('email', 'demo@ex.com')->first();
        $this->assertNotNull($demo, 'لم يُحفظ طلب العرض التوضيحي');
        $this->assertSame('agency', $demo->audience);
        $this->assertSame('submitted', $demo->status);
        $this->assertStringStartsWith('DM-', $demo->reference);
        $this->assertSame('اعتماد المحتوى والمستحقات', $demo->interests);

        // صفحة التأكيد تعرض المرجع نفسه — بلا مرجع يعود المستخدم بلا وسيلة متابعة
        $this->get("/demo/submitted/{$demo->reference}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/DemoSubmitted')
                ->where('reference', $demo->reference)
                ->where('email', 'demo@ex.com')
                ->where('audienceLabel', 'وكالة'));
    }

    public function test_demo_request_validates_and_stores_nothing_on_bad_input(): void
    {
        $this->from('/demo')
            ->post('/demo', ['audience' => 'agency', 'contact_name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['contact_name', 'email']);

        $this->assertSame(0, DemoRequest::count(), 'حُفظ طلب عرض رغم فشل التحقّق');
    }

    /** جهة مجهولة تعني جلسة لا يمكن تحضيرها — تُرفض بدل أن تُحفظ ناقصة. */
    public function test_demo_request_rejects_unknown_audience(): void
    {
        $this->from('/demo')
            ->post('/demo', ['audience' => 'investor', 'contact_name' => 'م', 'email' => 'a@ex.com'])
            ->assertSessionHasErrors('audience');

        $this->assertSame(0, DemoRequest::count());
    }

    public function test_unknown_demo_reference_is_not_found(): void
    {
        $this->get('/demo/submitted/DM-NOPE1234')->assertNotFound();
    }
}
