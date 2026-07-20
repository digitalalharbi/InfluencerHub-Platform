<?php

namespace Tests\Feature;

use App\Domain\Brands\Services\AgencyDelegationService;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Contracts\Models\Contract;
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
 * تفويض وكالة بإدارة علامة.
 *
 * القراران المحروسان:
 *
 * 1. **التفويض ليس ملكية.** صفّ `owner` لا يُمَسّ، والإلغاء ينهي الوصول ولا
 *    يحذف شيئًا — وكالةٌ تُصرَف لا تأخذ معها عمل سنة.
 * 2. **ولا وصول شامل افتراضيًّا.** ما لم يُذكر في النطاق فهو غير مفوَّض.
 */
class AgencyDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{tenant:Tenant, org:Organization, brand:Brand, owner:User} */
    private function brandSide(): array
    {
        $tenant = Tenant::create(['name' => 'علامة', 'slug' => Str::random(8),
            'type' => Tenant::TYPE_BRAND, 'deployment_mode' => 'saas', 'status' => 'active']);
        $org = Organization::create(['tenant_id' => $tenant->id, 'name' => 'علامة', 'slug' => Str::random(8),
            'type' => 'brand', 'status' => 'active']);

        $owner = User::create(['name' => 'مالك', 'email' => 'owner-'.Str::random(4).'@brand.test',
            'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $tenant->id, 'organization_id' => $org->id,
            'user_id' => $owner->id, 'role' => Role::BrandAdmin->value, 'status' => 'active']);

        $brand = TenantContext::withTenant($tenant->id, fn () => Brand::create([
            'tenant_id' => $tenant->id, 'name' => 'علامتي', 'slug' => 'b-'.Str::random(5),
            'status' => 'approved', 'current_version' => 1,
        ]));

        BrandWorkspaceRelationship::create([
            'brand_id' => $brand->id, 'tenant_id' => $tenant->id,
            'relationship_type' => BrandWorkspaceRelationship::OWNER,
            'status' => 'active', 'services_scope' => BrandWorkspaceRelationship::SERVICES,
            'started_at' => now(),
        ]);

        return ['tenant' => $tenant, 'org' => $org, 'brand' => $brand, 'owner' => $owner];
    }

    /** @return array{tenant:Tenant, admin:User} */
    private function agencySide(): array
    {
        $tenant = Tenant::create(['name' => 'وكالة', 'slug' => Str::random(8),
            'type' => Tenant::TYPE_AGENCY, 'deployment_mode' => 'saas', 'status' => 'active']);
        $org = Organization::create(['tenant_id' => $tenant->id, 'name' => 'وكالة', 'slug' => Str::random(8),
            'type' => 'agency', 'status' => 'active']);

        $admin = User::create(['name' => 'مدير الوكالة', 'email' => 'aa-'.Str::random(4).'@agency.test',
            'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $tenant->id, 'organization_id' => $org->id,
            'user_id' => $admin->id, 'role' => Role::AgencyAdmin->value, 'status' => 'active']);

        return ['tenant' => $tenant, 'admin' => $admin];
    }

    // ===== الدعوة من الطرفين =====

    public function test_a_brand_invites_an_agency_and_it_starts_pending(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();

        $rel = app(AgencyDelegationService::class)
            ->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns', 'content'], $b['owner']);

        $this->assertSame('pending', $rel->status);
        $this->assertFalse($rel->isLive(), 'الدعوة لا تمنح وصولًا قبل الموافقة');
        $this->assertFalse($rel->grants('campaigns'));
    }

    public function test_an_agency_requests_delegation_and_the_brand_owner_approves(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $rel = $svc->requestDelegation($b['brand'], $a['tenant']->id, ['campaigns'], $a['admin']);
        $this->assertSame('pending', $rel->status);

        $approved = $svc->approve($rel, $b['owner']);

        $this->assertSame('active', $approved->status);
        $this->assertTrue($approved->isLive());
        $this->assertNotNull($approved->started_at);
    }

    /** الداعي لا يوافق على دعوته — وإلّا منح نفسه وصولًا. */
    public function test_the_inviter_cannot_approve_their_own_invitation(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $rel = $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns'], $b['owner']);

        $this->expectExceptionMessage('لمدير الوكالة وحده');
        $svc->approve($rel, $b['owner']);
    }

    public function test_a_stranger_cannot_approve(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $rel = $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns'], $b['owner']);
        $stranger = User::create(['name' => 'غريب', 'email' => 'x@y.test', 'password' => 'x', 'is_active' => true]);

        $this->expectExceptionMessage('لمدير الوكالة وحده');
        $svc->approve($rel, $stranger);
    }

    public function test_only_the_brand_owner_may_invite(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();

        $member = User::create(['name' => 'عضو', 'email' => 'm@brand.test', 'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $b['tenant']->id, 'organization_id' => $b['org']->id,
            'user_id' => $member->id, 'role' => Role::BrandMember->value, 'status' => 'active']);

        $this->expectExceptionMessage('لمالك العلامة وحده');
        app(AgencyDelegationService::class)->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns'], $member);
    }

    // ===== النطاق =====

    public function test_a_delegation_without_a_scope_is_refused(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();

        $this->expectExceptionMessage('حدّد نطاق الخدمات');
        app(AgencyDelegationService::class)->inviteAgency($b['brand'], $a['tenant']->id, [], $b['owner']);
    }

    /** ما لم يُذكر فهو غير مفوَّض — لا وصول شامل افتراضيًّا. */
    public function test_an_unlisted_service_is_never_granted(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $rel = $svc->approve(
            $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns', 'content'], $b['owner']),
            $a['admin'],
        );

        $this->assertTrue($rel->grants('campaigns'));
        $this->assertTrue($rel->grants('content'));
        $this->assertFalse($rel->grants('finance'));
        $this->assertFalse($rel->grants('commerce'));
        $this->assertFalse($rel->grants('contracts'));
    }

    public function test_an_unknown_service_is_refused(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();

        $this->expectExceptionMessage('خدمة غير معروفة');
        app(AgencyDelegationService::class)
            ->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns', 'everything'], $b['owner']);
    }

    /** تضييق النطاق يسري فورًا — سحبُ صلاحية لا ينتظر دورة موافقة. */
    public function test_narrowing_the_scope_takes_effect_immediately(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $rel = $svc->approve(
            $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns', 'finance'], $b['owner']),
            $a['admin'],
        );
        $this->assertTrue($rel->grants('finance'));

        $narrowed = $svc->updateScope($rel, ['campaigns'], $b['owner']);

        $this->assertFalse($narrowed->grants('finance'));
        $this->assertTrue($narrowed->grants('campaigns'));
        $this->assertSame('active', $narrowed->status, 'التضييق لا يُعطّل التفويض');
    }

    // ===== الإلغاء بلا فقد بيانات =====

    public function test_revoking_ends_access_without_deleting_any_work(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $rel = $svc->approve(
            $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns', 'contracts'], $b['owner']),
            $a['admin'],
        );

        // عمل أُنتج تحت التفويض
        [$campaign, $contract] = TenantContext::withTenant($b['tenant']->id, function () use ($b) {
            $campaign = Campaign::create([
                'tenant_id' => $b['tenant']->id, 'brand_id' => $b['brand']->id, 'client_id' => null,
                'name' => 'حملة تحت التفويض', 'status' => 'active', 'currency' => 'SAR',
                'campaign_number' => 'CM-D-1',
            ]);
            $contract = Contract::create([
                'tenant_id' => $b['tenant']->id, 'title' => 'عقد تحت التفويض',
                'contract_number' => 'CT-D-1', 'status' => 'draft', 'party_type' => 'creator',
            ]);

            return [$campaign, $contract];
        });

        $revoked = $svc->revoke($rel, $b['owner'], 'انتهى التعاقد');

        $this->assertSame('ended', $revoked->status);
        $this->assertNotNull($revoked->ended_at);
        $this->assertFalse($revoked->isLive());
        $this->assertFalse($revoked->grants('campaigns'), 'الوصول انتهى');

        // ولا شيء حُذف
        $this->assertNotNull(Campaign::withoutGlobalScopes()->find($campaign->id));
        $this->assertNotNull(Contract::withoutGlobalScopes()->find($contract->id));
        $this->assertSame($b['tenant']->id, Campaign::withoutGlobalScopes()->find($campaign->id)->tenant_id,
            'العمل يبقى في مساحة العلامة');

        // وسجلّ العلاقة يبقى: هو الدليل على من كان مخوَّلًا ومتى
        $this->assertNotNull(BrandWorkspaceRelationship::find($rel->id));
    }

    public function test_the_ownership_relationship_can_never_be_revoked(): void
    {
        $b = $this->brandSide();
        $owner = $b['brand']->ownerRelationship();

        $this->expectExceptionMessage('لا تُلغى علاقة الملكية');
        app(AgencyDelegationService::class)->revoke($owner, $b['owner']);
    }

    public function test_only_the_brand_owner_may_revoke(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $rel = $svc->approve(
            $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns'], $b['owner']),
            $a['admin'],
        );

        // الوكالة لا تُنهي تفويض نفسها من طرف العلامة
        $this->expectExceptionMessage('لمالك العلامة وحده');
        $svc->revoke($rel, $a['admin']);
    }

    /** بعد الإلغاء يجوز تفويض الوكالة نفسها من جديد — على الصفّ نفسه. */
    public function test_an_agency_can_be_re_delegated_after_revocation(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $rel = $svc->approve(
            $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns'], $b['owner']),
            $a['admin'],
        );
        $svc->revoke($rel, $b['owner']);

        $again = $svc->inviteAgency($b['brand'], $a['tenant']->id, ['content'], $b['owner']);

        $this->assertSame('pending', $again->status);
        $this->assertSame($rel->id, $again->id, 'يُعاد فتح الصفّ نفسه — لا صفّ ثانٍ');
        $this->assertSame(1, BrandWorkspaceRelationship::where('brand_id', $b['brand']->id)
            ->where('relationship_type', BrandWorkspaceRelationship::MANAGING_AGENCY)->count());
    }

    public function test_a_duplicate_pending_invitation_is_refused(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns'], $b['owner']);

        $this->expectExceptionMessage('يوجد تفويض قائم أو بانتظار الموافقة');
        $svc->inviteAgency($b['brand'], $a['tenant']->id, ['content'], $b['owner']);
    }

    public function test_a_brand_cannot_delegate_to_its_own_tenant(): void
    {
        $b = $this->brandSide();

        $this->expectExceptionMessage('لمستأجرها نفسه');
        app(AgencyDelegationService::class)
            ->inviteAgency($b['brand'], $b['tenant']->id, ['campaigns'], $b['owner']);
    }

    /** التفويض لا يمسّ الملكية إطلاقًا. */
    public function test_delegation_never_touches_the_owner_row(): void
    {
        $b = $this->brandSide();
        $a = $this->agencySide();
        $svc = app(AgencyDelegationService::class);

        $ownerBefore = $b['brand']->ownerRelationship();

        $rel = $svc->approve(
            $svc->inviteAgency($b['brand'], $a['tenant']->id, ['campaigns'], $b['owner']),
            $a['admin'],
        );
        $svc->revoke($rel, $b['owner']);

        $ownerAfter = $b['brand']->fresh()->ownerRelationship();

        $this->assertNotNull($ownerAfter);
        $this->assertSame($ownerBefore->id, $ownerAfter->id);
        $this->assertSame('active', $ownerAfter->status);
        $this->assertSame($b['tenant']->id, $ownerAfter->tenant_id);
    }
}
