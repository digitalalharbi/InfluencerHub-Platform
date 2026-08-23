<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion, Subscription};
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * منح/إلغاء «قاعدة المؤثرين» من إدارة النظام — عبر آلية overrides القائمة، بلا معرّفات مضمّنة.
 */
class CreatorDatabaseAdminGrantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Organization,1:Subscription} مؤسسة على خطة لا تمنح قاعدة المؤثرين */
    private function orgWithoutAccess(): array
    {
        return TenantContext::withBypass(function () {
            $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
            $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
            PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_database.access', 'value' => 0]);
            (new CreateSubscription)->handle($org, $pv);
            $sub = Subscription::where('organization_id', $org->id)->firstOrFail();

            return [$org, $sub];
        });
    }

    private function systemAdmin(): User
    {
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true])->save(); // ليس ضمن fillable

        return $u;
    }

    public function test_system_admin_can_grant_then_revoke(): void
    {
        [$org, $sub] = $this->orgWithoutAccess();
        $admin = $this->systemAdmin();
        $ent = app(EntitlementService::class);

        // قبل المنح: لا وصول
        $this->assertFalse(TenantContext::withBypass(fn () => $ent->allows($org, 'creator_database.access')));

        // منح
        $this->actingAs($admin)->post("/beta/admin/subscriptions/{$sub->id}/creator-database", ['granted' => true])->assertRedirect();
        $this->assertTrue(TenantContext::withBypass(fn () => $ent->allows($org->fresh(), 'creator_database.access')));

        // إلغاء
        $this->actingAs($admin)->post("/beta/admin/subscriptions/{$sub->id}/creator-database", ['granted' => false])->assertRedirect();
        $this->assertFalse(TenantContext::withBypass(fn () => $ent->allows($org->fresh(), 'creator_database.access')));
    }

    public function test_non_admin_cannot_grant(): void
    {
        [, $sub] = $this->orgWithoutAccess();
        $u = User::create(['name' => 'x', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        $res = $this->actingAs($u)->post("/beta/admin/subscriptions/{$sub->id}/creator-database", ['granted' => true]);
        $this->assertContains($res->status(), [403, 302]); // ممنوع (وسيط system_admin)

        $this->assertFalse(TenantContext::withBypass(fn () => app(EntitlementService::class)->allows(
            Organization::withoutGlobalScopes()->find($sub->organization_id), 'creator_database.access')));
    }
}
