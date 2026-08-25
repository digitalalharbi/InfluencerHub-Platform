<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorEntitlementService;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember};
use App\Domain\Platform\Services\PlatformPortalEligibilityService;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * المصدر الواحد لأهلية البوّابات — يعكس شروط الحرّاس الفعلية لكل بوّابة، ويعيد كل
 * سياقات المستخدم (لا أول عضوية فقط)، ولا يُعلن بوّابة غير مؤهَّلة.
 */
class PlatformPortalEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOrg(): array
    {
        $t = Tenant::create(['name' => 'T', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'Org', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        return [$t, $org];
    }

    private function user(string $name = 'U'): User
    {
        return User::create(['name' => $name, 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
    }

    private function svc(): PlatformPortalEligibilityService
    {
        return app(PlatformPortalEligibilityService::class);
    }

    /** يُفعّل بوّابة المبدع بلا اشتراك حقيقي — نُبدّل خدمة الأهلية بمزيّف. */
    private function enableCreatorPortal(): void
    {
        $this->mock(CreatorEntitlementService::class, function ($m) {
            $m->shouldReceive('orgForTenant')->andReturnUsing(fn ($tid) => Organization::withoutGlobalScopes()->where('tenant_id', $tid)->first());
            $m->shouldReceive('portalEnabled')->andReturn(true);
        });
    }

    public function test_organization_only_user_gets_agency_context(): void
    {
        [$t, $org] = $this->tenantOrg();
        $u = $this->user();
        TenantContext::withBypass(fn () => OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']));

        $ctx = $this->svc()->contextsForUser($u);
        $this->assertCount(1, $ctx);
        $this->assertSame('agency', $ctx[0]['portal']);
        $this->assertSame($t->id, $ctx[0]['tenantId']);
        $this->assertSame($org->id, $ctx[0]['organizationId']);
    }

    public function test_portal_role_membership_is_not_agency_eligible(): void
    {
        [$t, $org] = $this->tenantOrg();
        $u = $this->user();
        TenantContext::withBypass(fn () => OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'influencer', 'status' => 'active']));

        $this->assertSame([], $this->svc()->contextsForUser($u));   // دور بوابة لا يفتح الوكالة
    }

    public function test_client_only_user_gets_client_context(): void
    {
        [$t] = $this->tenantOrg();
        $u = $this->user();
        TenantContext::withBypass(function () use ($t, $u) {
            $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'عميل', 'status' => 'active']);
            ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active']);
        });

        $ctx = $this->svc()->contextsForUser($u);
        $this->assertCount(1, $ctx);
        $this->assertSame('client', $ctx[0]['portal']);
        $this->assertSame($t->id, $ctx[0]['tenantId']);
    }

    public function test_creator_only_user_gets_creator_context_when_portal_enabled(): void
    {
        $this->enableCreatorPortal();
        [$t] = $this->tenantOrg();
        $u = $this->user();
        TenantContext::withBypass(fn () => Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-1', 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active', 'user_id' => $u->id]));

        $ctx = $this->svc()->contextsForUser($u);
        $this->assertCount(1, $ctx);
        $this->assertSame('creator', $ctx[0]['portal']);
    }

    public function test_partner_only_user_gets_partner_context_when_agency_approved(): void
    {
        [$t] = $this->tenantOrg();
        $u = $this->user();
        TenantContext::withBypass(function () use ($t, $u) {
            $agency = ExternalAgency::create(['tenant_id' => $t->id, 'agency_number' => 'EA-1', 'name' => 'شريك', 'status' => 'approved']);
            ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $agency->id, 'user_id' => $u->id, 'role' => 'external_agency_admin', 'status' => 'active']);
        });

        $ctx = $this->svc()->contextsForUser($u);
        $this->assertCount(1, $ctx);
        $this->assertSame('partner', $ctx[0]['portal']);
    }

    public function test_partner_context_absent_when_agency_not_approved(): void
    {
        [$t] = $this->tenantOrg();
        $u = $this->user();
        TenantContext::withBypass(function () use ($t, $u) {
            $agency = ExternalAgency::create(['tenant_id' => $t->id, 'agency_number' => 'EA-2', 'name' => 'شريك', 'status' => 'submitted']);
            ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $agency->id, 'user_id' => $u->id, 'role' => 'external_agency_admin', 'status' => 'active']);
        });

        $this->assertSame([], $this->svc()->contextsForUser($u));
    }

    public function test_multi_tenant_user_returns_every_eligible_context(): void
    {
        [$t1, $org1] = $this->tenantOrg();
        [$t2, $org2] = $this->tenantOrg();
        $u = $this->user();
        TenantContext::withBypass(function () use ($t1, $org1, $t2, $org2, $u) {
            OrganizationMembership::create(['tenant_id' => $t1->id, 'organization_id' => $org1->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            OrganizationMembership::create(['tenant_id' => $t2->id, 'organization_id' => $org2->id, 'user_id' => $u->id, 'role' => 'campaign_manager', 'status' => 'active']);
        });

        $ctx = collect($this->svc()->contextsForUser($u));
        $this->assertCount(2, $ctx);
        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], $ctx->pluck('tenantId')->all());
    }

    public function test_tenant_portals_do_not_advertise_absent_portals(): void
    {
        [$t, $org] = $this->tenantOrg();
        $u = $this->user();
        TenantContext::withBypass(fn () => OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']));

        $portals = $this->svc()->tenantPortals($t->id);
        $this->assertTrue($portals['agency']);
        $this->assertFalse($portals['client']);
        $this->assertFalse($portals['creator']);
        $this->assertFalse($portals['partner']);
    }
}
