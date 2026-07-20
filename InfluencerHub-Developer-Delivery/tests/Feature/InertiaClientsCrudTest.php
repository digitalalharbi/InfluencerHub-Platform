<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** إنشاء/أرشفة العميل من واجهة الوكالة React (/beta) — يعيد استخدام CreateClient/ArchiveClient + بوابة الدور. */
class InertiaClientsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Tenant} */
    private function agent(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $plan = \App\Domain\Billing\Models\Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = \App\Domain\Billing\Models\PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        \App\Domain\Billing\Models\PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'customers.max', 'value' => 100]);
        (new \App\Domain\Billing\Actions\CreateSubscription)->handle($org, $pv);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();
        return [$u, $t];
    }

    public function test_admin_can_create_client(): void
    {
        [$u, $t] = $this->agent('agency_admin');
        $this->actingAs($u)->post('/beta/clients', ['display_name' => 'عميل جديد', 'type' => 'company', 'status' => 'active'])
            ->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame(1, Client::where('display_name', 'عميل جديد')->count());
        TenantContext::reset();
    }

    public function test_create_validates(): void
    {
        [$u] = $this->agent('agency_admin');
        $this->actingAs($u)->post('/beta/clients', ['display_name' => ''])->assertSessionHasErrors('display_name');
    }

    public function test_viewer_cannot_create(): void
    {
        [$u] = $this->agent('viewer');
        $this->actingAs($u)->post('/beta/clients', ['display_name' => 'x'])->assertForbidden();
    }

    public function test_admin_can_archive_client(): void
    {
        [$u, $t] = $this->agent('agency_admin');
        TenantContext::set($t->id);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'للأرشفة', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        $this->actingAs($u)->delete("/beta/clients/{$c->id}")->assertRedirect('/beta/clients');
        TenantContext::set($t->id);
        $this->assertSame('archived', $c->fresh()->status);
        TenantContext::reset();
    }

    public function test_viewer_cannot_archive(): void
    {
        [$u, $t] = $this->agent('viewer');
        TenantContext::set($t->id);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'x', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        $this->actingAs($u)->delete("/beta/clients/{$c->id}")->assertForbidden();
    }
}
