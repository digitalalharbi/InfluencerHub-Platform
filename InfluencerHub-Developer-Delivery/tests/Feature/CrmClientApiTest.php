<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\CRM\Actions\CreateClient;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase 3 — CRM API عبر HTTP: enforcement (422) + عزل (404) + إنشاء. */
class CrmClientApiTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function actor(int $max = 2): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $u = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'customers.max', 'value' => $max]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        return [$u, $org, $t];
    }

    public function test_create_client_via_api_and_appears_in_list(): void
    {
        [$u] = $this->actor(3);
        Sanctum::actingAs($u);
        $this->postJson('/api/v1/clients', ['display_name' => 'عميل API', 'status' => 'active', 'type' => 'company'])
            ->assertCreated()->assertJsonPath('data.display_name', 'عميل API');
        $this->getJson('/api/v1/clients')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_create_over_customers_max_returns_422(): void
    {
        [$u] = $this->actor(1);
        Sanctum::actingAs($u);
        $this->postJson('/api/v1/clients', ['display_name' => 'A', 'status' => 'active'])->assertCreated();
        $this->postJson('/api/v1/clients', ['display_name' => 'B', 'status' => 'active'])
            ->assertStatus(422)->assertJsonPath('error', 'entitlement_limit');
    }

    public function test_client_is_isolated_across_tenants_over_http(): void
    {
        [$uA, $orgA] = $this->actor(5);
        TenantContext::set($orgA->tenant_id, $orgA->id);
        $clientA = app(CreateClient::class)->handle($orgA, ['display_name' => 'A', 'status' => 'active', 'type' => 'company'], $uA);
        TenantContext::reset();

        [$uB] = $this->actor(5);
        Sanctum::actingAs($uB);
        // مستأجر آخر لا يصل لعميل A (IDOR)
        $this->getJson("/api/v1/clients/{$clientA->id}")->assertNotFound();
        $this->putJson("/api/v1/clients/{$clientA->id}", ['display_name' => 'hack'])->assertNotFound();
        $this->getJson('/api/v1/clients')->assertOk()->assertJsonPath('data', []);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/clients')->assertStatus(401);
    }
}
