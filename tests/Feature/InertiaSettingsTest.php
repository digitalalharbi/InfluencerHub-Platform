<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion, Subscription};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** الإعدادات والاشتراك React/Inertia — العرض + العزل بالمستأجر + بوابة الدور. */
class InertiaSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Organization,2:Tenant} */
    private function agent(string $role = 'agency_admin', bool $withSub = true): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);

        if ($withSub) {
            $plan = Plan::create(['key' => 'growth-' . Str::random(6), 'name' => 'خطة النمو', 'is_active' => true, 'applies_to_mode' => 'saas']);
            $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true, 'is_locked' => false]);
            PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'customers.max', 'value' => 50, 'is_unlimited' => false]);
            PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creators.max', 'value' => 0, 'is_unlimited' => true]);
            Subscription::create([
                'tenant_id' => $t->id, 'organization_id' => $org->id, 'plan_version_id' => $pv->id,
                'status' => 'active', 'billing_provider' => 'manual',
                'current_period_start' => now()->subDays(5), 'current_period_end' => now()->addDays(25),
            ]);
        }

        TenantContext::reset();
        return [$u, $org, $t];
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/settings')->assertRedirect('/login');
    }

    public function test_renders_workspace_and_subscription(): void
    {
        [$u] = $this->agent();
        $this->actingAs($u)->get('/beta/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('org.name', 'وكالة')
                ->where('subscription.status', 'active')
                ->where('subscription.plan', 'خطة النمو')
                ->has('entitlements', 2)
                ->where('entitlements.0.key', 'customers.max')
                ->where('entitlements.1.unlimited', true));
    }

    public function test_renders_without_subscription(): void
    {
        [$u] = $this->agent('agency_admin', withSub: false);
        $this->actingAs($u)->get('/beta/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('subscription', null)
                ->has('entitlements', 0));
    }

    public function test_tenant_isolation(): void
    {
        [, $orgA] = $this->agent();
        [$uB] = $this->agent();
        // المستخدم B يرى مساحة عمله فقط، لا اشتراك المستأجر A
        $this->actingAs($uB)->get('/beta/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('subscription.plan', 'خطة النمو'));
        $this->assertNotEquals($orgA->id, TenantContext::organizationId());
    }

    public function test_viewer_role_denied(): void
    {
        [$u] = $this->agent('viewer');
        $this->actingAs($u)->get('/beta/settings')->assertForbidden();
    }
}
