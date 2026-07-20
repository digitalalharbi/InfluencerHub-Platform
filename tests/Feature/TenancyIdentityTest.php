<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, Workspace, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 1 — عزل المستأجر + العضوية + الأدوار (PostgreSQL مصدر الحقيقة). */
class TenancyIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create(['name' => $slug, 'slug' => $slug, 'deployment_mode' => 'saas', 'status' => 'active']);
    }

    public function test_organization_is_isolated_by_tenant(): void
    {
        $a = $this->tenant('t-a');
        $b = $this->tenant('t-b');

        TenantContext::set($a->id);
        Organization::create(['name' => 'Org A', 'slug' => 'org-a', 'type' => 'agency']);
        TenantContext::reset();

        TenantContext::set($b->id);
        Organization::create(['name' => 'Org B', 'slug' => 'org-b', 'type' => 'agency']);

        // ضمن سياق B: نرى مؤسسة B فقط
        $this->assertEquals(1, Organization::count());
        $this->assertEquals('Org B', Organization::first()->name);

        // ضمن سياق A: نرى مؤسسة A فقط
        TenantContext::set($a->id);
        $this->assertEquals(1, Organization::count());
        $this->assertEquals('Org A', Organization::first()->name);
    }

    public function test_no_tenant_context_returns_no_data_fail_closed(): void
    {
        $a = $this->tenant('t-x');
        TenantContext::set($a->id);
        Organization::create(['name' => 'X', 'slug' => 'x', 'type' => 'agency']);
        TenantContext::reset(); // لا سياق

        $this->assertEquals(0, Organization::count()); // fail-closed
    }

    public function test_creating_scoped_model_without_context_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        TenantContext::reset();
        Organization::create(['name' => 'Y', 'slug' => 'y', 'type' => 'agency']);
    }

    public function test_user_can_belong_to_multiple_workspaces_with_different_roles(): void
    {
        $tenant = $this->tenant('t-multi');
        TenantContext::set($tenant->id);
        $org = Organization::create(['name' => 'Agency', 'slug' => 'agency', 'type' => 'agency']);
        $ws1 = Workspace::create(['organization_id' => $org->id, 'name' => 'WS1', 'slug' => 'ws1']);
        $ws2 = Workspace::create(['organization_id' => $org->id, 'name' => 'WS2', 'slug' => 'ws2']);

        $user = User::create(['name' => 'U', 'email' => 'u@ex.com', 'password' => 'Secret123!', 'is_active' => true]);

        OrganizationMembership::create(['organization_id' => $org->id, 'workspace_id' => $ws1->id, 'user_id' => $user->id, 'role' => 'campaign_manager']);
        OrganizationMembership::create(['organization_id' => $org->id, 'workspace_id' => $ws2->id, 'user_id' => $user->id, 'role' => 'finance']);

        $this->assertEquals('campaign_manager', $user->roleIn($org->id, $ws1->id));
        $this->assertEquals('finance', $user->roleIn($org->id, $ws2->id));
        $this->assertTrue($user->hasRoleIn($org->id, ['finance'], $ws2->id));
        $this->assertFalse($user->hasRoleIn($org->id, ['finance'], $ws1->id));
    }

    public function test_system_admin_bypass_sees_all_tenants(): void
    {
        $a = $this->tenant('t-1');
        $b = $this->tenant('t-2');
        TenantContext::set($a->id);
        Organization::create(['name' => 'A', 'slug' => 'a', 'type' => 'agency']);
        TenantContext::reset();
        TenantContext::set($b->id);
        Organization::create(['name' => 'B', 'slug' => 'b', 'type' => 'agency']);
        TenantContext::reset();

        TenantContext::bypass(true); // system_admin
        $this->assertEquals(2, Organization::count());
    }
}
