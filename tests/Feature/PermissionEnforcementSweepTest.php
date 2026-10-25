<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Navigation\NavigationBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CORE-01 — تعزيز الصلاحيات (سدّ الفجوات فوق التغطية القائمة). العزل عبر المستأجرين/البوابات
 * وIDOR مغطّى بالفعل في TenantIsolationSweep / TenantHttpIsolation / BrandWorkspaceIsolation /
 * LoginPortalAccess / CrmPolicy / PlatformOwnerAccess. هنا نقفل فجوتين:
 *   1) عدّادات التنقّل المخزّنة مؤقتًا (PERF-01) لا تتسرّب عبر المستأجرين (مفتاح يشمل المستأجر+المؤسسة).
 *   2) بوّابة الوكالة محميّة: الضيف يُحوَّل، وعضو الوكالة لا يعبر إلى بوّابات أخرى.
 */
class PermissionEnforcementSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        NavigationBadges::flush();
        TenantContext::reset();
        parent::tearDown();
    }

    private function tenant(string $slug): array
    {
        $t = Tenant::create(['name' => $slug, 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة '.$slug, 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $org, $u];
    }

    /** PERF-01 cache: عدّاد «مراجعات العلامات» لمستأجر لا يظهر لمستأجر آخر (مفتاح مُنطّق). */
    public function test_navigation_badges_cache_is_tenant_scoped(): void
    {
        [$tA, $oA] = $this->tenant('A');
        [$tB, $oB] = $this->tenant('B');

        // مستأجر A: علامة معلّقة للمراجعة
        TenantContext::bypass(true);
        $clientA = Client::create(['tenant_id' => $tA->id, 'client_number' => 'CL-A', 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        Brand::create(['tenant_id' => $tA->id, 'client_id' => $clientA->id, 'name' => 'علامة', 'slug' => 'b-'.$tA->id, 'status' => 'submitted']);
        TenantContext::reset();

        // سياق A: يُحسب ويُخزَّن بمفتاح A
        NavigationBadges::flush();
        TenantContext::set($tA->id, $oA->id);
        $a = NavigationBadges::all();
        TenantContext::reset();
        $this->assertSame(1, $a['brand_reviews'] ?? 0, 'المستأجر A يجب أن يرى علامته المعلّقة');

        // سياق B: يجب أن يعيد الحساب بمفتاح B (0) — لا يقرأ قيمة A المخزّنة
        NavigationBadges::flush();
        TenantContext::set($tB->id, $oB->id);
        $b = NavigationBadges::all();
        TenantContext::reset();
        $this->assertArrayNotHasKey('brand_reviews', $b, 'تسرّب عدّاد العلامات من المستأجر A إلى B (مفتاح غير مُنطّق)');
    }

    /** الضيف يُحوَّل لتسجيل الدخول على أسطح الوكالة الأساسية. */
    public function test_guest_is_redirected_from_agency_surfaces(): void
    {
        foreach (['/app', '/app/creators', '/app/campaigns', '/app/reports', '/app/payouts', '/app/automation'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    /** عضو الوكالة لا يعبر إلى بوّابات أخرى (creator/client/platform) — الوسيط يمنع. */
    public function test_agency_member_cannot_cross_into_other_portals(): void
    {
        [, , $agency] = $this->tenant('X');
        foreach (['/creator/dashboard', '/client/dashboard', '/partner/dashboard', '/platform'] as $path) {
            $status = $this->actingAs($agency)->get($path)->status();
            $this->assertNotSame(200, $status, "عضو الوكالة عبر إلى {$path} (status {$status}) — يجب أن يُمنع");
        }
    }
}
