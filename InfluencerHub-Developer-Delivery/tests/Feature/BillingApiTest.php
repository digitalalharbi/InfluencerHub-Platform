<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, Workspace, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase 2 — API الفوترة عبر HTTP مع سياق المستأجر. */
class BillingApiTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function setup_org_user(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $o = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $u = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $o->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        // خطة + اشتراك
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'creators.max', 'value' => 25]);
        (new CreateSubscription)->handle($o, $v);
        TenantContext::reset();
        return [$u, $o];
    }

    public function test_subscription_and_entitlements_endpoints_are_tenant_scoped(): void
    {
        [$u] = $this->setup_org_user();
        Sanctum::actingAs($u);

        $this->getJson('/api/v1/billing/subscription')
            ->assertOk()->assertJsonPath('data.status', 'trialing')->assertJsonPath('data.is_active', true);

        $ent = $this->getJson('/api/v1/billing/entitlements')->assertOk()->json('data');
        $this->assertEquals(25, $ent['creators.max']['limit']);
        $this->assertFalse($ent['advanced_analytics.enabled']['allowed']);

        $this->getJson('/api/v1/billing/usage')
            ->assertOk()->assertJsonStructure(['data' => ['exports.monthly.max' => ['used', 'remaining']]]);
    }

    public function test_non_system_admin_cannot_list_plans(): void
    {
        [$u] = $this->setup_org_user();
        Sanctum::actingAs($u);
        $this->getJson('/api/v1/admin/plans')->assertStatus(403);
    }

    public function test_unauthenticated_billing_is_rejected(): void
    {
        $this->getJson('/api/v1/billing/subscription')->assertStatus(401);
    }
}
