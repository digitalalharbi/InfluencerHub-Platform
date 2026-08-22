<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** لوحة مدير النظام (SaaS) React/Inertia — إشراف عبر المستأجرين، محميّة بـ is_system_admin. */
class InertiaAdminPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function admin(): User
    {
        TenantContext::bypass(true);
        $u = User::create(['name' => 'مالك', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true])->save();
        TenantContext::reset();
        return $u;
    }

    private function seedTenant(): Tenant
    {
        $t = Tenant::create(['name' => 'وكالة ' . Str::random(3), 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'خطة', 'is_active' => true]);
        $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'customers.max', 'value' => 50]);
        (new CreateSubscription)->handle($org, $pv);
        TenantContext::reset();
        return $t;
    }

    public function test_non_admin_denied(): void
    {
        TenantContext::bypass(true);
        $u = User::create(['name' => 'x', 'email' => Str::random(5) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        TenantContext::reset();
        $this->actingAs($u)->get('/beta/admin')->assertForbidden();
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/admin')->assertRedirect('/login');
    }

    public function test_dashboard_cross_tenant_stats(): void
    {
        $this->seedTenant();
        $this->seedTenant();
        $u = $this->admin();
        $this->actingAs($u)->get('/beta/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.tenants', 2)
                ->where('stats.activeSubs', 2)
                ->has('recentTenants', 2));
    }

    public function test_tenants_list(): void
    {
        $this->seedTenant();
        $u = $this->admin();
        $this->actingAs($u)->get('/beta/admin/tenants')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Tenants')->has('tenants.data', 1)->where('tenants.data.0.sub', true));
    }

    public function test_plans_and_subscriptions_and_audit(): void
    {
        $this->seedTenant();
        $u = $this->admin();
        $this->actingAs($u)->get('/beta/admin/plans')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Plans')->has('plans'));
        $this->actingAs($u)->get('/beta/admin/subscriptions')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Subscriptions')->has('subs.data', 1));
        $this->actingAs($u)->get('/beta/admin/audit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Audit')->has('logs'));
    }

    /** التعديل والاعتماد: مستأجر/خطة/اشتراك — لمدير النظام وحده، ويثبت في القاعدة. */
    public function test_admin_can_edit_tenant_plan_and_subscription(): void
    {
        $t = $this->seedTenant();
        $u = $this->admin();

        TenantContext::bypass(true);
        $plan = Plan::firstOrFail();
        $sub = \App\Domain\Billing\Models\Subscription::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        TenantContext::reset();

        // مستأجر
        $this->actingAs($u)->from('/beta/admin/tenants')
            ->post("/beta/admin/tenants/{$t->id}", ['name' => 'وكالة معدّلة', 'status' => 'suspended', 'deployment_mode' => 'dedicated'])
            ->assertRedirect();
        TenantContext::bypass(true); $t->refresh(); TenantContext::reset();
        $this->assertSame('وكالة معدّلة', $t->name);
        $this->assertSame('suspended', $t->status);

        // خطة
        $this->actingAs($u)->post("/beta/admin/plans/{$plan->id}", ['name' => 'خطة مطوّرة', 'is_active' => false])->assertRedirect();
        TenantContext::bypass(true); $plan->refresh(); TenantContext::reset();
        $this->assertSame('خطة مطوّرة', $plan->name);
        $this->assertFalse((bool) $plan->is_active);

        // اشتراك
        $this->actingAs($u)->post("/beta/admin/subscriptions/{$sub->id}", ['status' => 'active', 'billing_provider' => 'moyasar'])->assertRedirect();
        TenantContext::bypass(true); $sub->refresh(); TenantContext::reset();
        $this->assertSame('active', $sub->status);
        $this->assertSame('moyasar', $sub->billing_provider);

        // غير المدير ممنوع
        TenantContext::bypass(true);
        $plain = User::create(['name' => 'x', 'email' => Str::random(5) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        TenantContext::reset();
        $this->actingAs($plain)->post("/beta/admin/tenants/{$t->id}", ['name' => 'y', 'status' => 'active', 'deployment_mode' => 'saas'])->assertForbidden();
    }
}
