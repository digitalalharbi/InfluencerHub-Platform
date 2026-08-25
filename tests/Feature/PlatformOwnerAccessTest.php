<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * حوكمة مساحة مالك المنصّة (/platform): لا يدخلها إلّا من يملك قدرة platform.owner
 * (is_system_admin). كل الأدوار الأخرى تُرفض (403)، والزائر يُحوَّل للدخول. الوصول
 * مُدقَّق. المركز يعرض أعدادًا فعلية عابرة للمستأجرين.
 */
class PlatformOwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        // مالك المنصّة: العلامة المخصّصة (+is_system_admin للهرمية). لا روابط مستأجر.
        $u = User::create(['name' => 'Owner', 'email' => 'owner@platform.test', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true, 'is_platform_owner' => true])->save();
        return $u;
    }

    /** system admin عادي (ليس مالك منصّة) — يجب أن يبقى سلوكه كما هو ويُمنع من /platform. */
    private function systemAdminOnly(): User
    {
        $u = User::create(['name' => 'Sys', 'email' => 'sys@platform.test', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true, 'is_platform_owner' => false])->save();
        return $u;
    }

    /** ينشئ مستأجرًا+مؤسسة+مدير وكالة (ليس مالك منصّة) — لبيانات فعلية واختبار الرفض. */
    private function agencyAdmin(): User
    {
        $t = Tenant::create(['name' => 'A', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'A', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'AA', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();
        return $u;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/platform')->assertRedirect('/login');
    }

    public function test_agency_admin_is_forbidden(): void
    {
        $this->actingAs($this->agencyAdmin())->get('/platform')->assertForbidden();
    }

    public function test_plain_authenticated_user_is_forbidden(): void
    {
        $u = User::create(['name' => 'P', 'email' => 'plain@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $this->actingAs($u)->get('/platform')->assertForbidden();
    }

    public function test_system_admin_without_platform_owner_is_forbidden(): void
    {
        // الفصل الجوهري: مالك المنصّة ≠ كل system admin.
        $this->actingAs($this->systemAdminOnly())->get('/platform')->assertForbidden();
    }

    public function test_system_admin_only_still_reaches_its_own_admin_area(): void
    {
        // عدم انحدار: سلوك system admin القائم (/beta/admin) يبقى كما هو.
        $this->actingAs($this->systemAdminOnly())->get('/beta/admin')->assertOk();
    }

    public function test_platform_owner_sees_control_center_with_real_counts(): void
    {
        $this->agencyAdmin();   // يزرع مستأجرًا ومؤسسة ومستخدمًا فعليًّا
        $this->actingAs($this->owner())->get('/platform')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Platform/ControlCenter')
                ->where('stats.tenants', fn ($v) => (int) $v >= 1)
                ->where('stats.organizations', fn ($v) => (int) $v >= 1)
                ->has('recentTenants')->has('recentActivity')->has('securityEvents')->has('links'));
    }

    public function test_platform_owner_access_is_audited(): void
    {
        $this->actingAs($this->owner())->get('/platform')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.control_center.view']);
    }
}
