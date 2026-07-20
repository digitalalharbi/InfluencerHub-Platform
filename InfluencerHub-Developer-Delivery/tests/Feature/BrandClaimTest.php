<?php

namespace Tests\Feature;

use App\Domain\Brands\Models\BrandClaimRequest;
use App\Domain\Brands\Services\BrandClaimService;
use App\Domain\Brands\Services\BrandMatchingService;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * المطالبة بعلامة قائمة.
 *
 * القرار المحروس: **لا ملكية تنتقل بلا مراجعة بشر**. أقوى مؤشّر عندنا هو نطاق
 * البريد المؤسسي، ومعناه «يملك بريدًا على هذا النطاق» لا «يملك هذه العلامة» —
 * وموظّف مستقيل أو متعاقد أو مشتري نطاقٍ منتهٍ كلّهم يجتازونه.
 */
class BrandClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function agencyBrand(): array
    {
        $tenant = Tenant::create(['name' => 'وكالة', 'slug' => Str::random(8),
            'type' => 'agency', 'deployment_mode' => 'saas', 'status' => 'active']);
        $org = Organization::create(['tenant_id' => $tenant->id, 'name' => 'و', 'slug' => Str::random(8),
            'type' => 'agency', 'status' => 'active']);

        $brand = TenantContext::withTenant($tenant->id, fn () => Brand::create([
            'tenant_id' => $tenant->id, 'name' => 'نايك', 'slug' => 'nike-'.Str::random(4),
            'normalized_name' => app(BrandMatchingService::class)->normalizeName('نايك'),
            'email_domain' => 'nike.com', 'status' => 'approved', 'current_version' => 1,
        ]));

        return ['tenant' => $tenant, 'org' => $org, 'brand' => $brand];
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'مدير النظام', 'email' => 'sa-'.Str::random(5).'@x.test',
            'password' => 'x', 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true])->save();

        return $u->fresh();
    }

    private function claimant(): User
    {
        return User::create(['name' => 'طالب', 'email' => 'claimant@nike.com',
            'password' => 'x', 'is_active' => true]);
    }

    // ===== الفتح ومنع التكرار =====

    public function test_opening_a_claim_starts_it_pending_and_grants_nothing(): void
    {
        $a = $this->agencyBrand();

        $claim = app(BrandClaimService::class)->open($a['brand'], 'claimant@nike.com');

        $this->assertSame(BrandClaimRequest::PENDING, $claim->status);
        $this->assertTrue($claim->isLive());

        // لا علاقة أُنشئت — الطلب لا يمنح شيئًا
        $this->assertSame(0, BrandWorkspaceRelationship::where('brand_id', $a['brand']->id)
            ->where('relationship_type', 'owner')->count());
        $this->assertSame($a['tenant']->id, $a['brand']->fresh()->tenant_id, 'العلامة لم تتحرّك');
    }

    public function test_a_second_live_claim_by_the_same_email_is_refused(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $svc->open($a['brand'], 'claimant@nike.com');

        $this->expectExceptionMessage('لديك طلب قائم');
        $svc->open($a['brand'], 'claimant@nike.com');
    }

    /** التفرّد يخصّ الحيّ وحده: من رُفض له أن يعيد المحاولة بأدلّة أفضل. */
    public function test_a_new_claim_is_allowed_after_the_previous_one_closed(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $admin = $this->admin();

        $first = $svc->open($a['brand'], 'claimant@nike.com');
        $svc->reject($svc->startReview($first, $admin), $admin, 'المستندات لا تثبت الملكية.');

        $second = $svc->open($a['brand'], 'claimant@nike.com');
        $this->assertSame(BrandClaimRequest::PENDING, $second->status);
    }

    // ===== المراجعة =====

    public function test_only_a_system_admin_may_review(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $claim = $svc->open($a['brand'], 'claimant@nike.com');

        $notAdmin = $this->claimant();

        $this->expectExceptionMessage('صلاحية مدير النظام');
        $svc->startReview($claim, $notAdmin);
    }

    /**
     * حتّى مدير وكالة العلامة لا يراجع.
     *
     * الطلب يقرّر نقل ملكية بين مستأجرَين؛ مراجعته من داخل أحدهما تجعل الخصم
     * حَكَمًا في قضيّته.
     */
    public function test_the_holding_agency_admin_cannot_review_its_own_case(): void
    {
        $a = $this->agencyBrand();
        $agencyAdmin = User::create(['name' => 'مدير الوكالة', 'email' => 'aa@agency.test',
            'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $a['tenant']->id, 'organization_id' => $a['org']->id,
            'user_id' => $agencyAdmin->id, 'role' => Role::AgencyAdmin->value, 'status' => 'active']);

        $svc = app(BrandClaimService::class);
        $claim = $svc->open($a['brand'], 'claimant@nike.com');

        $this->expectExceptionMessage('صلاحية مدير النظام');
        $svc->startReview($claim, $agencyAdmin);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $admin = $this->admin();
        $claim = $svc->startReview($svc->open($a['brand'], 'claimant@nike.com'), $admin);

        $this->expectExceptionMessage('سبب الرفض إلزامي');
        $svc->reject($claim, $admin, '   ');
    }

    public function test_more_info_can_be_requested_and_returns_to_review(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $admin = $this->admin();

        $claim = $svc->startReview($svc->open($a['brand'], 'claimant@nike.com'), $admin);
        $claim = $svc->requestMoreInfo($claim, $admin, 'أرفق السجلّ التجاري.');

        $this->assertSame(BrandClaimRequest::MORE_INFO, $claim->status);
        $this->assertSame('أرفق السجلّ التجاري.', $claim->info_requested);
        $this->assertTrue($claim->canTransitionTo(BrandClaimRequest::UNDER_REVIEW));
    }

    public function test_a_decided_claim_is_final(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $admin = $this->admin();

        $claim = $svc->reject($svc->startReview($svc->open($a['brand'], 'c@nike.com'), $admin), $admin, 'سبب');

        $this->assertFalse($claim->canTransitionTo(BrandClaimRequest::APPROVED));
        $this->expectExceptionMessage('انتقال غير مسموح');
        $svc->startReview($claim, $admin);
    }

    // ===== الاعتماد: نقل ملكية بلا فقد بيانات =====

    public function test_approval_moves_ownership_without_losing_any_data(): void
    {
        $a = $this->agencyBrand();

        // بيانات تشغيلية قائمة على العلامة قبل المطالبة
        $campaign = TenantContext::withTenant($a['tenant']->id, fn () => Campaign::create([
            'tenant_id' => $a['tenant']->id, 'brand_id' => $a['brand']->id,
            'name' => 'حملة قائمة', 'status' => 'draft', 'currency' => 'SAR',
            'campaign_number' => 'CM-TEST-1',
        ]));

        $svc = app(BrandClaimService::class);
        $admin = $this->admin();
        $claim = $svc->startReview($svc->open($a['brand'], 'claimant@nike.com'), $admin);

        $approved = $svc->approve($claim, $admin, [
            'name' => 'مالك نايك', 'email' => 'claimant@nike.com', 'password' => 'secret-pass-123',
        ]);

        $this->assertSame(BrandClaimRequest::APPROVED, $approved->status);

        $brand = Brand::withoutGlobalScopes()->find($a['brand']->id);
        $newTenant = Tenant::find($brand->tenant_id);

        // العلامة انتقلت إلى مستأجر مستقلّ من نوع علامة
        $this->assertNotSame($a['tenant']->id, $brand->tenant_id);
        $this->assertSame(Tenant::TYPE_BRAND, $newTenant->type);
        $this->assertNull($brand->client_id);

        // ولا نسخة ثانية من العلامة — نفس المعرّف
        $this->assertSame($a['brand']->id, $brand->id, 'العلامة تُنقل ولا تُنسخ');
        $this->assertSame(1, Brand::withoutGlobalScopes()->where('normalized_name', $brand->normalized_name)->count());

        // الحملة القائمة لم تُمَسّ
        $this->assertNotNull(Campaign::withoutGlobalScopes()->find($campaign->id));
        $this->assertSame($a['brand']->id, Campaign::withoutGlobalScopes()->find($campaign->id)->brand_id);

        // المالك الجديد
        $owner = $brand->ownerRelationship();
        $this->assertNotNull($owner);
        $this->assertSame($brand->tenant_id, $owner->tenant_id);

        // والوكالة السابقة صارت مفوَّضة لا مالكة — تفويضٌ لا حذف
        $delegated = BrandWorkspaceRelationship::where('brand_id', $brand->id)
            ->where('tenant_id', $a['tenant']->id)
            ->where('relationship_type', BrandWorkspaceRelationship::MANAGING_AGENCY)->first();
        $this->assertNotNull($delegated);
        $this->assertSame('active', $delegated->status);
    }

    public function test_approval_grants_the_delegated_agency_a_scope_not_everything(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $admin = $this->admin();

        $svc->approve($svc->startReview($svc->open($a['brand'], 'c@nike.com'), $admin), $admin, [
            'name' => 'م', 'email' => 'c@nike.com', 'password' => 'secret-pass-123',
        ]);

        $delegated = BrandWorkspaceRelationship::where('brand_id', $a['brand']->id)
            ->where('tenant_id', $a['tenant']->id)
            ->where('relationship_type', BrandWorkspaceRelationship::MANAGING_AGENCY)->first();

        $this->assertNotContains('commerce', $delegated->services_scope, 'لا وصول شامل افتراضيًّا');
        $this->assertNotContains('integrations', $delegated->services_scope);
        $this->assertFalse($delegated->grants('commerce'));
        $this->assertTrue($delegated->grants('campaigns'));
    }

    // ===== الإلغاء والانتهاء =====

    public function test_only_the_requester_may_cancel(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $claimant = $this->claimant();
        $claim = $svc->open($a['brand'], 'claimant@nike.com', null, [], $claimant);

        $stranger = User::create(['name' => 'غريب', 'email' => 'x@y.test', 'password' => 'x', 'is_active' => true]);

        $this->expectExceptionMessage('لا تملك إلغاء');
        $svc->cancel($claim, $stranger);
    }

    public function test_the_requester_cancels_their_own_claim(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $claimant = $this->claimant();

        $claim = $svc->cancel($svc->open($a['brand'], 'claimant@nike.com', null, [], $claimant), $claimant);

        $this->assertSame(BrandClaimRequest::CANCELLED, $claim->status);
        $this->assertNotNull($claim->cancelled_at);
    }

    /** طلب معلّق أبدًا يُبقي العلامة في منطقة رمادية ويحبس صاحبها الحقيقي. */
    public function test_due_claims_expire_and_free_the_brand_for_a_new_claim(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $claim = $svc->open($a['brand'], 'claimant@nike.com');

        $claim->update(['expires_at' => now()->subDay()]);

        $this->assertSame(1, $svc->expireDue());
        $this->assertSame(BrandClaimRequest::EXPIRED, $claim->fresh()->status);

        // وبانتهائه يُفتح الباب لطلب جديد
        $this->assertSame(BrandClaimRequest::PENDING, $svc->open($a['brand'], 'claimant@nike.com')->status);
    }

    public function test_a_live_claim_does_not_expire_early(): void
    {
        $a = $this->agencyBrand();
        $svc = app(BrandClaimService::class);
        $svc->open($a['brand'], 'claimant@nike.com');

        $this->assertSame(0, $svc->expireDue());
    }
}
