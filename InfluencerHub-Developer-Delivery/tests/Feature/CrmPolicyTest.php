<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 3 — سياسات CRM عبر 12 دورًا: view/create/update/delete/managePortal. */
class CrmPolicyTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private Tenant $t;
    private Organization $org;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $this->org = Organization::create(['tenant_id' => $this->t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $this->client = Client::create(['tenant_id' => $this->t->id, 'client_number' => 'CL-1', 'display_name' => 'C', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        TenantContext::set($this->t->id, $this->org->id);
    }

    private function userWithRole(string $role): User
    {
        TenantContext::bypass(true);
        $u = User::create(['name' => $role, 'email' => Str::random(8) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $this->t->id, 'organization_id' => $this->org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();
        TenantContext::set($this->t->id, $this->org->id);
        return $u;
    }

    /** الأدوار الاثنا عشر ومصفوفة القدرات المتوقعة: [view, create, delete, managePortal]. */
    public static function roleMatrix(): array
    {
        return [
            'super_admin'        => ['super_admin', true, true, true, true],
            'agency_admin'       => ['agency_admin', true, true, true, true],
            'operations_manager' => ['operations_manager', true, true, true, true],
            'campaign_manager'   => ['campaign_manager', true, true, false, false],
            'agency_employee'    => ['agency_employee', true, false, false, false],
            'creator_manager'    => ['creator_manager', true, false, false, false],
            'content_reviewer'   => ['content_reviewer', true, false, false, false],
            'finance'            => ['finance', true, false, false, false],
            'viewer'             => ['viewer', true, false, false, false],
            'influencer'         => ['influencer', false, false, false, false],
            'brand_member'       => ['brand_member', false, false, false, false],
            'external_agency_member' => ['external_agency_member', false, false, false, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roleMatrix')]
    public function test_role_capabilities(string $role, bool $view, bool $create, bool $delete, bool $portal): void
    {
        $u = $this->userWithRole($role);
        $this->assertSame($view, Gate::forUser($u)->allows('view', $this->client), "$role view");
        $this->assertSame($create, Gate::forUser($u)->allows('create', Client::class), "$role create");
        $this->assertSame($delete, Gate::forUser($u)->allows('delete', $this->client), "$role delete");
        $this->assertSame($portal, Gate::forUser($u)->allows('managePortal', $this->client), "$role managePortal");
    }

    public function test_system_admin_bypasses_all_policies(): void
    {
        TenantContext::bypass(true);
        $admin = User::create(['name' => 'sa', 'email' => Str::random(8) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        $admin->forceFill(['is_system_admin' => true])->save(); // ليس fillable (منع تصعيد)
        TenantContext::reset();
        TenantContext::set($this->t->id, $this->org->id);
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $this->client));
        $this->assertTrue(Gate::forUser($admin)->allows('managePortal', $this->client));
    }

    public function test_no_org_context_denies_non_admin(): void
    {
        $u = $this->userWithRole('agency_admin');
        TenantContext::reset(); // لا سياق مؤسسة → لا دور → رفض
        $this->assertFalse(Gate::forUser($u)->allows('view', $this->client));
    }
}
