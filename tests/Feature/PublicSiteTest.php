<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\Onboarding\Support\AccountTypes;
use App\Domain\Identity\Models\User;
use App\Domain\Onboarding\Models\SignupRequest;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * الموقع العام — أوّل ما يراه الزائر.
 *
 * كان الجذر يحوّل مباشرة إلى /app فيهبط زائر بلا حساب داخل لوحة تشغيل داخلية.
 * هذه الاختبارات تحرس المدخل: الزائر يرى المنتَج، والمصادَق يذهب إلى بوابته.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_guest_sees_the_product_gateway_not_an_internal_dashboard(): void
    {
        $this->get('/')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Gateway')->has('accountTypes', 3));
    }

    /** ثلاثة أنواع لا رابع — وUGC قدرة داخل صانع المحتوى لا نوعًا. */
    public function test_account_type_offers_exactly_three_choices(): void
    {
        $keys = array_column(AccountTypes::all(), 'key');

        $this->assertSame(['brand', 'agency', 'creator'], $keys);
        $this->assertNotContains('ugc', $keys, 'UGC نوع حساب — يجب أن يكون قدرة داخل صانع المحتوى');
    }

    // ===== توحيد مسار الاختيار =====

    public function test_start_is_the_single_place_to_choose_an_account_type(): void
    {
        $this->get('/start')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Start')->has('accountTypes', 3));
    }

    /** @dataProvider accountTypeKeys */
    public function test_start_preselects_the_type_from_the_url(string $key): void
    {
        $this->get("/start?type={$key}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Start')->where('selected', $key));
    }

    public static function accountTypeKeys(): array
    {
        return [['brand'], ['agency'], ['creator']];
    }

    /** نوعٌ مجهول لا يُختار ولا يُسقط الصفحة — يعود الاختيار فارغًا. */
    public function test_an_unknown_type_is_ignored_not_trusted(): void
    {
        $this->get('/start?type=admin')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('selected', null));
    }

    /**
     * `/register` تحويل دائم إلى `/start` — لا صفحة اختيار ثانية.
     *
     * @dataProvider legacyRegisterPaths
     */
    public function test_legacy_register_paths_redirect_to_start(string $path): void
    {
        $this->get($path)->assertRedirect('/start')->assertStatus(301);
    }

    public static function legacyRegisterPaths(): array
    {
        return [['/register'], ['/register/account-type']];
    }

    /**
     * المعاملات لا تضيع في التحويل.
     *
     * رابطُ حملةٍ إعلانية يحمل الخطّة والإحالة؛ فقدُها يعني فقد مصدر العميل
     * وخطّته — ولا يظهر ذلك في أيّ خطأ، بل في تقرير تسويق ناقص.
     */
    public function test_the_redirect_carries_type_email_referral_and_plan(): void
    {
        $r = $this->get('/register?type=brand&email=a%40b.com&referral=partner7&plan=pro');

        $target = $r->headers->get('Location');

        $this->assertStringContainsString('/start?', $target);
        foreach (['type=brand', 'email=a%40b.com', 'referral=partner7', 'plan=pro'] as $part) {
            $this->assertStringContainsString($part, $target, "ضاع المعامل: {$part}");
        }
    }

    /** ولا يُحمَل ما لم يُذكَر — وإلّا صار الرابط نفقًا مفتوحًا. */
    public function test_unlisted_parameters_are_dropped(): void
    {
        $target = $this->get('/register?plan=pro&redirect=https://evil.test')->headers->get('Location');

        $this->assertStringContainsString('plan=pro', $target);
        $this->assertStringNotContainsString('evil.test', $target);
    }

    /**
     * السلسلة لا تنقطع عند الإرسال.
     *
     * عند POST تصل المعاملات في **جسم** الطلب لا في الرابط؛ وقراءة الرابط
     * وحده كانت تُسقط `referral` و`plan` عند أوّل إرسال — أي في الخطوة التي
     * يبدأ فيها التسجيل. ولا يظهر ذلك في أيّ خطأ، بل في تقرير تسويق ناقص.
     */
    public function test_referral_and_plan_survive_the_form_submission(): void
    {
        $target = $this->post('/start', [
            'type' => 'brand',
            'email' => 'founder@example.test',
            'referral' => 'partner7',
            'plan' => 'pro',
        ])->headers->get('Location');

        $this->assertStringContainsString('/register/brand/verify/', $target);
        $this->assertStringContainsString('referral=partner7', $target, 'ضاعت الإحالة عند الإرسال');
        $this->assertStringContainsString('plan=pro', $target, 'ضاعت الخطّة عند الإرسال');
    }

    /** النوع يُحدّد الوجهة — ولا يضيع بين الاختيار وبدء التسجيل. */
    public function test_each_type_starts_its_own_journey(): void
    {
        $brand = $this->post('/start', ['type' => 'brand', 'email' => 'a@ex.test'])->headers->get('Location');
        $agency = $this->post('/start', ['type' => 'agency', 'email' => 'b@ex.test'])->headers->get('Location');
        $creator = $this->post('/start', ['type' => 'creator', 'email' => 'c@ex.test'])->headers->get('Location');

        $this->assertStringContainsString('/register/brand/verify/', $brand);
        $this->assertStringContainsString('/register/agency/verify/', $agency);
        $this->assertStringContainsString('/join/creator', $creator);
        $this->assertStringContainsString('email=c%40ex.test', $creator, 'البريد لا يُطلب مرّتين');
    }

    /** `/start` لا يحوّل إلى نفسه ولا إلى `/register` — لا حلقة. */
    public function test_start_does_not_redirect_a_guest_anywhere(): void
    {
        $this->get('/start')->assertOk();
        $this->get('/start?type=brand')->assertOk();
    }

    public function test_agency_member_is_sent_to_their_portal_not_the_marketing_site(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        $this->actingAs($u)->get('/')->assertRedirect('/app');
    }

    public function test_creator_is_sent_to_creator_portal(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer',
            'display_name' => 'م', 'status' => 'active', 'user_id' => $u->id]);
        TenantContext::reset();

        $this->actingAs($u)->get('/')->assertRedirect('/creator');
    }

    /**
     * المسار اليدوي: العميل على /register/client، والوكالة على المسار المؤسسي.
     * الوكالة العادية انتقلت إلى التسجيل الذاتي، فبقي اليدوي للحالات المخصّصة.
     *
     * @dataProvider signupTypes
     */
    public function test_signup_request_is_stored_with_reference(string $type, string $url): void
    {
        $this->post($url, [
            'contact_name' => 'محمد', 'email' => 'x@ex.com', 'company_name' => 'شركة',
        ])->assertRedirect();

        $s = SignupRequest::where('email', 'x@ex.com')->first();
        $this->assertNotNull($s, 'لم يُحفظ طلب فتح الحساب');
        $this->assertSame($type, $s->account_type);
        $this->assertSame('submitted', $s->status);
        $this->assertStringStartsWith('SU-', $s->reference);
    }

    public static function signupTypes(): array
    {
        return [
            'client' => ['client', '/register/client'],
            'agency (مؤسسي)' => ['agency', '/register/agency/enterprise'],
        ];
    }

    public function test_signup_validates_and_stores_nothing_on_bad_input(): void
    {
        $this->from('/register/agency/enterprise')
            ->post('/register/agency/enterprise', ['contact_name' => '', 'email' => 'not-an-email', 'company_name' => ''])
            ->assertSessionHasErrors(['contact_name', 'email', 'company_name']);

        $this->assertSame(0, SignupRequest::count(), 'حُفظ طلب رغم فشل التحقّق');
    }

    public function test_unknown_account_type_is_not_routable(): void
    {
        $this->get('/register/ugc')->assertNotFound();
        $this->post('/register/ugc', [])->assertNotFound();
    }

    /** /register/agency صار بداية التسجيل الذاتي لا نموذج مراجعة. */
    public function test_agency_registration_starts_the_self_serve_path(): void
    {
        $this->get('/register/agency')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/SelfSignup/Start'));
    }
}
