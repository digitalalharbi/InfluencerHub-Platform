<?php

namespace Tests\Feature;

use App\Domain\Brands\Models\BrandSignup;
use App\Domain\Brands\Services\BrandMatchingService;
use App\Domain\Brands\Services\BrandProvisioningService;
use App\Domain\Brands\Services\BrandSignupService;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * عزل مساحة العلامة، وعدم كشف وجودها.
 *
 * مساحة العلامة مستأجر كامل كغيره — والعزل ليس ميزة تُضاف لها بل شرط وجودها.
 * والمطابقة تفتح سطحًا جديدًا: استعلامٌ يقرأ **عبر كل المستأجرين** عمدًا. فيجب
 * أن يُثبَت أن ما يخرج منه لا يصل المستخدم.
 */
class BrandWorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function registerBrand(string $name, string $email): array
    {
        $svc = app(BrandSignupService::class);
        [$signup, $code] = $svc->start($email);
        $svc->verifyEmail($signup, $code);
        $phoneCode = $svc->startPhone($signup->fresh(), '+9665'.random_int(10000000, 99999999));
        $svc->verifyPhone($signup->fresh(), $phoneCode);
        $svc->saveDetails($signup->fresh(),
            ['legal_name' => $name, 'commercial_registration' => null],
            ['name' => $name, 'sector' => 'عام', 'website' => null]);
        $signup = $svc->runMatch($signup->fresh(), app(BrandMatchingService::class));

        return app(BrandProvisioningService::class)->provision($signup, [
            'name' => "مالك {$name}", 'email' => $email, 'password' => 'secret-pass-123',
        ]);
    }

    public function test_two_self_registered_brands_cannot_see_each_other(): void
    {
        $a = $this->registerBrand('ألفا', 'owner@alpha-brand.test');
        $b = $this->registerBrand('بيتا', 'owner@beta-brand.test');

        $this->assertNotSame($a['tenant']->id, $b['tenant']->id);

        // داخل مستأجر ألفا لا تظهر إلا علامته
        $seen = TenantContext::withTenant($a['tenant']->id, fn () => Brand::pluck('name')->all());
        $this->assertSame(['ألفا'], $seen);

        $seenB = TenantContext::withTenant($b['tenant']->id, fn () => Brand::pluck('name')->all());
        $this->assertSame(['بيتا'], $seenB);
    }

    /** جلب علامة الغير بمعرّفها مباشرةً — يجب أن يعود فارغًا لا أن يُسلّمها. */
    public function test_fetching_another_brand_by_id_returns_nothing(): void
    {
        $a = $this->registerBrand('ألفا', 'owner@alpha-brand.test');
        $b = $this->registerBrand('بيتا', 'owner@beta-brand.test');

        $stolen = TenantContext::withTenant($a['tenant']->id, fn () => Brand::find($b['brand']->id));

        $this->assertNull($stolen, 'IDOR: علامة مستأجر آخر وصلت بمعرّفها');
    }

    public function test_a_brands_campaigns_stay_inside_its_workspace(): void
    {
        $a = $this->registerBrand('ألفا', 'owner@alpha-brand.test');
        $b = $this->registerBrand('بيتا', 'owner@beta-brand.test');

        TenantContext::withTenant($a['tenant']->id, fn () => Campaign::create([
            'tenant_id' => $a['tenant']->id, 'brand_id' => $a['brand']->id, 'client_id' => null,
            'name' => 'حملة ألفا', 'status' => 'draft', 'currency' => 'SAR', 'campaign_number' => 'CM-A-1',
        ]));

        $this->assertSame(1, TenantContext::withTenant($a['tenant']->id, fn () => Campaign::count()));
        $this->assertSame(0, TenantContext::withTenant($b['tenant']->id, fn () => Campaign::count()));
    }

    /** حملة العلامة بلا عميل — وهي الحالة التي كانت تصطدم بـNOT NULL. */
    public function test_a_brand_can_run_a_campaign_without_any_client(): void
    {
        $a = $this->registerBrand('ألفا', 'owner@alpha-brand.test');

        $campaign = TenantContext::withTenant($a['tenant']->id, fn () => Campaign::create([
            'tenant_id' => $a['tenant']->id, 'brand_id' => $a['brand']->id, 'client_id' => null,
            'name' => 'حملة ذاتية', 'status' => 'draft', 'currency' => 'SAR', 'campaign_number' => 'CM-A-2',
        ]));

        $this->assertNull($campaign->client_id);
        $this->assertSame($a['brand']->id, $campaign->brand_id);
    }

    // ===== عدم الكشف =====

    /**
     * المطابقة تقرأ عبر المستأجرين، ونتيجتها تبقى في الخادم.
     *
     * لو خرج اسم العلامة المطابَقة أو معرّف مستأجرها إلى المستخدم لصارت
     * البوّابة أداة تعداد: يجرّب المهاجم نطاقات فيعرف من هم عملاؤنا.
     */
    public function test_the_match_result_never_carries_the_matched_brands_details(): void
    {
        $agencyTenant = Tenant::create(['name' => 'وكالة', 'slug' => Str::random(8),
            'type' => 'agency', 'deployment_mode' => 'saas', 'status' => 'active']);

        // الاسم المخزون **يخالف** ما سيكتبه المسجِّل — وإلّا لَما كشف الاختبار
        // تسريبًا: أيّ ظهور للاسم سيكون صدى لإدخال المستخدم لا كشفًا عن سجلّنا.
        TenantContext::withTenant($agencyTenant->id, fn () => Brand::create([
            'tenant_id' => $agencyTenant->id, 'name' => 'مؤسسة الغيث القابضة',
            'slug' => 'secret-'.Str::random(4),
            'normalized_name' => app(BrandMatchingService::class)->normalizeName('مؤسسة الغيث القابضة'),
            'email_domain' => 'secret.test', 'website_domain' => 'secret.test',
            'status' => 'approved', 'current_version' => 1,
        ]));

        $svc = app(BrandSignupService::class);
        [$signup, $code] = $svc->start('someone@secret.test');
        $svc->verifyEmail($signup, $code);
        $phoneCode = $svc->startPhone($signup->fresh(), '+966500000009');
        $svc->verifyPhone($signup->fresh(), $phoneCode);
        $svc->saveDetails($signup->fresh(),
            ['legal_name' => 'متجري', 'commercial_registration' => null],
            ['name' => 'متجري', 'website' => 'https://secret.test']);

        $matched = $svc->runMatch($signup->fresh(), app(BrandMatchingService::class));

        // النتيجة محفوظة للخادم: النطاق وحده بلغ عتبة القوّة
        $this->assertSame(BrandSignup::DECISION_STRONG, $matched->match_decision);
        $this->assertNotNull($matched->matched_brand_id);

        // وما يُسلسَل لا يحمل اسم العلامة المطابَقة ولا مستأجرها.
        // (النطاق يظهر في المؤشّرات — لكنّه ما أدخله المستخدم بنفسه، لا كشف.)
        $serialized = json_encode($matched->toArray(), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('الغيث', $serialized,
            'اسم العلامة المطابَقة تسرّب في تسلسل سجلّ التسجيل');
        $this->assertArrayNotHasKey('matched_tenant_id', $matched->toArray(),
            'معرّف مستأجر العلامة المطابَقة لا مكان له هنا');
        $this->assertArrayNotHasKey('matched_brand', $matched->toArray(),
            'العلاقة لا تُحمَّل تلقائيًّا فلا تتسرّب بيانات العلامة');
    }

    /** الملكية علاقة، لا نسخة — الربط لا يكرّر سجلّ العلامة. */
    public function test_registering_never_duplicates_an_existing_brand_record(): void
    {
        $agencyTenant = Tenant::create(['name' => 'وكالة', 'slug' => Str::random(8),
            'type' => 'agency', 'deployment_mode' => 'saas', 'status' => 'active']);

        $existing = TenantContext::withTenant($agencyTenant->id, fn () => Brand::create([
            'tenant_id' => $agencyTenant->id, 'name' => 'دلتا', 'slug' => 'delta-'.Str::random(4),
            'normalized_name' => app(BrandMatchingService::class)->normalizeName('دلتا'),
            'status' => 'approved', 'current_version' => 1,
        ]));

        // تسجيل باسم مطابق لكن بلا مؤشّر آخر ⇒ لا تطابق ⇒ سجلّ جديد مشروع
        $new = $this->registerBrand('دلتا', 'owner@delta-brand.test');

        $this->assertNotSame($existing->id, $new['brand']->id);

        // ولكلٍّ مالكه ومستأجره — ولا علاقة ملكية مزدوجة على أيٍّ منهما
        $this->assertSame(1, BrandWorkspaceRelationship::where('brand_id', $new['brand']->id)
            ->where('relationship_type', 'owner')->count());
        $this->assertSame(0, BrandWorkspaceRelationship::where('brand_id', $existing->id)
            ->where('relationship_type', 'owner')->count());
    }
}
