<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\PlanEntitlement;
use App\Domain\Billing\Models\PlanVersion;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Brands\Models\BrandClaimRequest;
use App\Domain\Brands\Models\BrandSignup;
use App\Domain\Brands\Services\BrandClaimService;
use App\Domain\Brands\Services\BrandMatchingService;
use App\Domain\Brands\Services\BrandProvisioningService;
use App\Domain\Brands\Services\BrandSignupService;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * التسجيل الذاتي للعلامة: من البريد إلى مساحة تعمل.
 *
 * القرار المحروس هنا: **العلامة تملك نفسها**. ملكيّتها صفٌّ بنوع `owner` لا
 * عمود `client_id`، ولا يُنشأ لها «عميل ذاتي» يحمل اسمها — فذلك يخلط العميل
 * بالعلامة ويشوّه التقارير والصلاحيات والفوترة.
 */
class BrandSelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function plan(): PlanVersion
    {
        $plan = Plan::create(['key' => 'brand-pro', 'name' => 'علامة', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'campaigns.max', 'value' => 10]);

        return $v;
    }

    /** يوصل تسجيلًا إلى لحظة ما قبل التزويد. */
    private function verifiedSignup(array $brand = [], array $org = [], string $email = 'owner@nike.com'): BrandSignup
    {
        $svc = app(BrandSignupService::class);
        [$signup, $emailCode] = $svc->start($email, '127.0.0.1');
        $svc->verifyEmail($signup, $emailCode);

        $phoneCode = $svc->startPhone($signup->fresh(), '+966500000001');
        $svc->verifyPhone($signup->fresh(), $phoneCode);

        $svc->saveDetails($signup->fresh(),
            $org + ['legal_name' => 'شركة نايك العربية', 'commercial_registration' => '1010111222'],
            $brand + ['name' => 'نايك', 'sector' => 'رياضة', 'website' => 'https://nike.com'],
        );

        return $svc->runMatch($signup->fresh(), app(BrandMatchingService::class));
    }

    // ===== الرحلة السعيدة =====

    public function test_a_brand_registers_itself_and_gets_a_working_workspace(): void
    {
        $this->plan();
        $signup = $this->verifiedSignup();

        $this->assertSame(BrandSignup::DECISION_NONE, $signup->match_decision);

        $result = app(BrandProvisioningService::class)->provision($signup, [
            'name' => 'مالك نايك', 'email' => 'owner@nike.com', 'password' => 'secret-pass-123',
        ]);

        $tenant = $result['tenant'];
        $brand = $result['brand'];

        // المستأجر مستقلّ ونوعه علامة — لا مستأجر وكالة
        $this->assertSame(Tenant::TYPE_BRAND, $tenant->type);

        // الملكية في صفٍّ، لا في عمود
        $this->assertNull($brand->client_id, 'العلامة المسجِّلة لنفسها لا عميل لها');
        $owner = $brand->ownerRelationship();
        $this->assertNotNull($owner);
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertTrue($brand->isSelfOwned());

        // ولا «عميل ذاتي» بديلًا عن الملكية — وهو الحلّ الممنوع صراحةً
        $this->assertSame(0, Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            'ممنوع إنشاء عميل وهمي باسم العلامة');

        // المالك يدخل بدور brand_admin
        $membership = OrganizationMembership::withoutGlobalScopes()
            ->where('organization_id', $result['organization']->id)
            ->where('user_id', $result['user']->id)->first();
        $this->assertSame(Role::BrandAdmin->value, $membership->role);
        $this->assertSame('active', $membership->status);

        // اشتراك تجريبي فعّال
        $sub = Subscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($sub);
        $this->assertSame('trialing', $sub->status);
        $this->assertNotNull($sub->trial_ends_at);
    }

    public function test_each_entity_is_created_exactly_once(): void
    {
        $this->plan();
        $r = app(BrandProvisioningService::class)->provision($this->verifiedSignup(), [
            'name' => 'مالك', 'email' => 'owner@nike.com', 'password' => 'secret-pass-123',
        ]);

        $tid = $r['tenant']->id;

        $this->assertSame(1, Tenant::withoutGlobalScopes()->where('id', $tid)->count());
        $this->assertSame(1, Brand::withoutGlobalScopes()->where('tenant_id', $tid)->count());
        $this->assertSame(1, Organization::withoutGlobalScopes()->where('tenant_id', $tid)->count());
        $this->assertSame(1, User::withoutGlobalScopes()->where('email', 'owner@nike.com')->count());
        $this->assertSame(1, OrganizationMembership::withoutGlobalScopes()->where('tenant_id', $tid)->count());
        $this->assertSame(1, BrandWorkspaceRelationship::where('brand_id', $r['brand']->id)
            ->where('relationship_type', 'owner')->count());
    }

    public function test_provisioning_twice_is_refused(): void
    {
        $this->plan();
        $signup = $this->verifiedSignup();
        $svc = app(BrandProvisioningService::class);

        $svc->provision($signup, ['name' => 'م', 'email' => 'owner@nike.com', 'password' => 'secret-pass-123']);

        $this->expectExceptionMessage('اكتمل هذا التسجيل من قبل.');
        $svc->provision($signup->fresh(), ['name' => 'م', 'email' => 'other@nike.com', 'password' => 'secret-pass-123']);
    }

    public function test_an_email_that_already_has_an_account_is_refused(): void
    {
        User::create(['name' => 'قائم', 'email' => 'owner@nike.com', 'password' => 'x', 'is_active' => true]);

        $this->expectExceptionMessage('لهذا البريد حساب بالفعل');
        app(BrandProvisioningService::class)->provision($this->verifiedSignup(), [
            'name' => 'م', 'email' => 'owner@nike.com', 'password' => 'secret-pass-123',
        ]);
    }

    /**
     * الفشل في أيّ خطوة يمحو ما قبلها.
     *
     * النصف المزوَّد أسوأ من الفشل الصريح: مستأجرٌ بلا مالك لا يدخله أحد،
     * وهي حالة لا يكشفها إلا شكوى.
     */
    public function test_a_failure_midway_rolls_back_everything(): void
    {
        $signup = $this->verifiedSignup();
        $before = [
            'tenants' => Tenant::withoutGlobalScopes()->count(),
            'brands' => Brand::withoutGlobalScopes()->count(),
            'users' => User::withoutGlobalScopes()->count(),
            'rels' => BrandWorkspaceRelationship::count(),
        ];

        // بريد مملوك ⇐ يرمي بعد أن تكون خطوات سابقة قد كُتبت داخل المعاملة
        User::create(['name' => 'قائم', 'email' => 'taken@nike.com', 'password' => 'x', 'is_active' => true]);

        try {
            app(BrandProvisioningService::class)->provision($signup, [
                'name' => 'م', 'email' => 'taken@nike.com', 'password' => 'secret-pass-123',
            ]);
            $this->fail('كان يجب أن يفشل');
        } catch (\RuntimeException) {
            // متوقّع
        }

        $this->assertSame($before['tenants'], Tenant::withoutGlobalScopes()->count(), 'مستأجر يتيم بقي بعد الفشل');
        $this->assertSame($before['brands'], Brand::withoutGlobalScopes()->count());
        $this->assertSame($before['rels'], BrandWorkspaceRelationship::count());
        $this->assertNull($signup->fresh()->created_tenant_id);
    }

    // ===== المطابقة =====

    public function test_a_strong_match_does_not_provision_but_requires_a_claim(): void
    {
        $agency = $this->agencyWithBrand();

        // نفس النطاق المؤسسي + نفس السجلّ التجاري ⇒ قويّ
        $signup = $this->verifiedSignup(
            ['name' => 'نايك', 'website' => 'https://nike.com'],
            ['legal_name' => 'نايك', 'commercial_registration' => '1010111222'],
            'someone@nike.com',
        );

        $this->assertSame(BrandSignup::DECISION_STRONG, $signup->match_decision);
        $this->assertSame($agency['brand']->id, $signup->matched_brand_id);

        $this->expectExceptionMessage('يحتاج إثبات ملكية');
        app(BrandProvisioningService::class)->provision($signup, [
            'name' => 'م', 'email' => 'someone@nike.com', 'password' => 'secret-pass-123',
        ]);
    }

    public function test_a_weak_match_is_possible_not_strong(): void
    {
        $this->agencyWithBrand();

        // الاسم وحده يتطابق — بريد عامّ، لا سجلّ تجاري، لا موقع
        $signup = $this->verifiedSignup(
            ['name' => 'نايك', 'website' => null],
            ['legal_name' => 'أخرى', 'commercial_registration' => null],
            'someone@gmail.com',
        );

        $this->assertSame(BrandSignup::DECISION_NONE, $signup->match_decision,
            'تشابه الاسم وحده (15) دون عتبة الاحتمال (25) — عمدًا');
    }

    public function test_name_plus_website_reaches_possible_and_opens_a_claim(): void
    {
        $agency = $this->agencyWithBrand();

        $signup = $this->verifiedSignup(
            ['name' => 'نايك', 'website' => 'https://www.nike.com/'],
            ['legal_name' => 'أخرى', 'commercial_registration' => null],
            'someone@gmail.com',
        );

        // الاسم 15 + الموقع 25 = 40 ⇒ محتمَل
        $this->assertSame(BrandSignup::DECISION_POSSIBLE, $signup->match_decision);
        $this->assertSame(40, $signup->match_score);

        $claim = app(BrandClaimService::class)->open(
            $agency['brand'], $signup->email, $signup, ['note' => 'نحن أصحابها'],
        );

        $this->assertSame(BrandClaimRequest::PENDING, $claim->status);
    }

    /** بريد عامّ لا يصلح مؤشّرًا — وإلّا صار كل من يسجّل بـGmail مطابقًا لغيره. */
    public function test_a_public_email_domain_is_never_a_match_signal(): void
    {
        $matcher = app(BrandMatchingService::class);

        $this->assertNull($matcher->emailDomain('someone@gmail.com'));
        $this->assertNull($matcher->emailDomain('someone@outlook.com'));
        $this->assertSame('nike.com', $matcher->emailDomain('someone@nike.com'));
    }

    public function test_name_normalization_folds_arabic_variants_and_company_suffixes(): void
    {
        $m = app(BrandMatchingService::class);

        $this->assertSame($m->normalizeName('شركة نايك المحدودة'), $m->normalizeName('نايك'));
        $this->assertSame($m->normalizeName('شركه نايك'), $m->normalizeName('شركة نايك'));
        $this->assertSame($m->normalizeName('Nike Inc'), $m->normalizeName('nike'));
    }

    public function test_website_normalization_ignores_scheme_www_and_path(): void
    {
        $m = app(BrandMatchingService::class);

        $this->assertSame('nike.com', $m->domain('https://WWW.Nike.com/ar/men'));
        $this->assertSame('nike.com', $m->domain('nike.com'));
        $this->assertNull($m->domain('not-a-domain'));
    }

    // ===== التحقّق =====

    public function test_a_wrong_code_is_counted_and_the_channel_locks_after_five(): void
    {
        $svc = app(BrandSignupService::class);
        [$signup] = $svc->start('owner@nike.com');

        for ($i = 0; $i < 5; $i++) {
            try {
                $svc->verifyEmail($signup->fresh(), '000000');
            } catch (\RuntimeException) {
            }
        }

        $this->assertSame(5, $signup->fresh()->email_attempts);

        $this->expectExceptionMessage('تجاوزتَ عدد المحاولات');
        $svc->verifyEmail($signup->fresh(), '000000');
    }

    /** الحدّ على القناة لا على المستخدم: من أخطأ في بريده لا يُقفَل عليه جواله. */
    public function test_attempt_limits_are_per_channel(): void
    {
        $svc = app(BrandSignupService::class);
        [$signup, $emailCode] = $svc->start('owner@nike.com');
        $svc->verifyEmail($signup->fresh(), $emailCode);

        $phoneCode = $svc->startPhone($signup->fresh(), '+966500000001');

        for ($i = 0; $i < 5; $i++) {
            try {
                $svc->verifyPhone($signup->fresh(), '000000');
            } catch (\RuntimeException) {
            }
        }

        $this->assertSame(5, $signup->fresh()->phone_attempts);
        $this->assertSame(0, $signup->fresh()->email_attempts, 'قفل قناة لا يمسّ الأخرى');
    }

    public function test_codes_are_never_stored_in_readable_form(): void
    {
        $svc = app(BrandSignupService::class);
        [$signup, $code] = $svc->start('owner@nike.com');

        $row = DB::table('brand_signups')->find($signup->id);

        $this->assertNotSame($code, $row->email_code_hash);
        $this->assertStringNotContainsString($code, (string) $row->email_code_hash);
        $this->assertArrayNotHasKey('email_code_hash', $signup->toArray(), 'الرمز لا يخرج في التسلسل');
    }

    public function test_a_verified_code_cannot_be_replayed(): void
    {
        $svc = app(BrandSignupService::class);
        [$signup, $code] = $svc->start('owner@nike.com');
        $svc->verifyEmail($signup->fresh(), $code);

        $this->assertNull($signup->fresh()->email_code_hash, 'الرمز يُمحى بعد نجاحه');
    }

    // ===== أدوات =====

    private function agencyWithBrand(): array
    {
        $tenant = Tenant::create(['name' => 'وكالة', 'slug' => Str::random(8),
            'type' => 'agency', 'deployment_mode' => 'saas', 'status' => 'active']);

        $brand = TenantContext::withTenant($tenant->id, fn () => Brand::create([
            'tenant_id' => $tenant->id,
            'name' => 'نايك',
            'slug' => 'nike-'.Str::random(4),
            'normalized_name' => app(BrandMatchingService::class)->normalizeName('نايك'),
            'email_domain' => 'nike.com',
            'website_domain' => 'nike.com',
            'commercial_registration' => '1010111222',
            'status' => 'approved',
            'current_version' => 1,
        ]));

        return ['tenant' => $tenant, 'brand' => $brand];
    }
}
