<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * إدارة فريق الوكالة الفعلية — كانت الصفحة عرضًا فقط (index) بلا تعديل.
 * تُثبت: إضافة عضو موجود، تغيير الدور، تعليق/تفعيل/إزالة، حماية آخر مدير، وبوابة الصلاحية.
 * كل إجراء يُطبَّق من الخادم لكل طلب.
 */
class AgencyTeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Organization $org;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function boot(): void
    {
        $this->tenant = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $this->org = TenantContext::withBypass(fn () => Organization::create([
            'tenant_id' => $this->tenant->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active',
        ]));
        TenantContext::set($this->tenant->id, $this->org->id);
    }

    private function user(string $role, string $status = 'active'): User
    {
        return TenantContext::withBypass(function () use ($role, $status) {
            $u = User::create(['name' => 'م' . Str::random(3), 'email' => Str::random(8) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $this->tenant->id, 'organization_id' => $this->org->id, 'user_id' => $u->id, 'role' => $role, 'status' => $status]);
            return $u;
        });
    }

    private function membershipOf(User $u): ?OrganizationMembership
    {
        return TenantContext::withBypass(fn () => OrganizationMembership::where('organization_id', $this->org->id)->where('user_id', $u->id)->first());
    }

    public function test_admin_adds_existing_user_by_email(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        // ثانٍ حتى لا يكون admin هو المالك الوحيد المتأثر
        $this->user('agency_admin');
        $newcomer = TenantContext::withBypass(fn () => User::create(['name' => 'جديد', 'email' => 'new@ex.com', 'password' => bcrypt('x'), 'is_active' => true]));

        $this->actingAs($admin)->post('/app/team/invite', ['email' => 'NEW@ex.com', 'role' => 'campaign_manager'])
            ->assertRedirect();

        $m = $this->membershipOf($newcomer);
        $this->assertNotNull($m, 'يجب إنشاء عضوية');
        $this->assertSame('campaign_manager', $m->role);
        $this->assertSame('active', $m->status);
    }

    public function test_invite_unknown_email_is_rejected(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $this->actingAs($admin)->post('/app/team/invite', ['email' => 'ghost@nowhere.com', 'role' => 'viewer'])
            ->assertSessionHasErrors('team');
    }

    public function test_invite_duplicate_active_member_is_rejected(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $member = $this->user('viewer');
        $email = $this->membershipOf($member) ? TenantContext::withBypass(fn () => User::find($member->id)->email) : null;

        $this->actingAs($admin)->post('/app/team/invite', ['email' => $email, 'role' => 'finance'])
            ->assertSessionHasErrors('team');
    }

    public function test_change_role_persists(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $member = $this->user('viewer');
        $m = $this->membershipOf($member);

        $this->actingAs($admin)->post("/app/team/{$m->id}/role", ['role' => 'campaign_manager'])
            ->assertRedirect()->assertSessionHas('ok');

        $this->assertSame('campaign_manager', $this->membershipOf($member)->role);
    }

    public function test_cannot_demote_last_owner(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin'); // المالك النشِط الوحيد
        $m = $this->membershipOf($admin);

        $this->actingAs($admin)->post("/app/team/{$m->id}/role", ['role' => 'viewer'])
            ->assertSessionHasErrors('team');
        $this->assertSame('agency_admin', $this->membershipOf($admin)->role);
    }

    public function test_suspend_then_reactivate(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $member = $this->user('campaign_manager');
        $m = $this->membershipOf($member);

        $this->actingAs($admin)->post("/app/team/{$m->id}/status", ['action' => 'suspend'])->assertSessionHas('ok');
        $this->assertSame('suspended', $this->membershipOf($member)->status);

        $this->actingAs($admin)->post("/app/team/{$m->id}/status", ['action' => 'reactivate'])->assertSessionHas('ok');
        $this->assertSame('active', $this->membershipOf($member)->status);
    }

    public function test_remove_deletes_membership(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $member = $this->user('viewer');
        $m = $this->membershipOf($member);

        $this->actingAs($admin)->post("/app/team/{$m->id}/status", ['action' => 'remove'])->assertSessionHas('ok');
        $this->assertNull($this->membershipOf($member));
    }

    public function test_cannot_suspend_last_owner(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $m = $this->membershipOf($admin);
        $this->actingAs($admin)->post("/app/team/{$m->id}/status", ['action' => 'suspend'])
            ->assertSessionHasErrors('team');
        $this->assertSame('active', $this->membershipOf($admin)->status);
    }

    public function test_non_admin_cannot_manage(): void
    {
        $this->boot();
        $this->user('agency_admin'); // owner exists
        $viewer = $this->user('viewer');
        $target = $this->user('campaign_manager');
        $m = $this->membershipOf($target);

        $this->actingAs($viewer)->post("/app/team/{$m->id}/role", ['role' => 'finance'])->assertForbidden();
        $this->actingAs($viewer)->post("/app/team/{$m->id}/status", ['action' => 'suspend'])->assertForbidden();
        $this->actingAs($viewer)->post('/app/team/invite', ['email' => 'x@ex.com', 'role' => 'viewer'])->assertForbidden();
    }

    public function test_super_admin_not_assignable_via_ui(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $this->user('agency_admin'); // second owner
        $member = $this->user('viewer');
        $m = $this->membershipOf($member);

        $this->actingAs($admin)->post("/app/team/{$m->id}/role", ['role' => 'super_admin'])
            ->assertSessionHasErrors('team');
        $this->assertSame('viewer', $this->membershipOf($member)->role);
    }
}
