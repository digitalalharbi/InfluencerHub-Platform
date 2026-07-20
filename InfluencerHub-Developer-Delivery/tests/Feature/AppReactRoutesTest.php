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
 * مسارات /app المُحوَّلة إلى React/Inertia.
 *
 * تحرس ثلاثة أمور أثناء التحويل التدريجي من Blade:
 * 1. أن المسار يُقدّم مكوّن React الصحيح تحت /app (لا صفحة Blade ولا 404).
 * 2. أن `base` المشتركة تساوي بادئة التركيب الفعلية — عليها تُبنى كل روابط الصفحة،
 *    فلو عادت `/beta` تحت `/app` لخرج المستخدم من مجموعته عند أول نقرة.
 * 3. أن الصلاحيات وعزل المستأجر يسريان كما في نسخة Blade.
 */
class AppReactRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: Organization, 2: User} */
    private function agency(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();

        return [$t, $org, $u];
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function migratedRoutes(): array
    {
        return [
            'reports' => ['/app/reports', 'Reports/Index'],
            'my-tasks' => ['/app/my-tasks', 'MyTasks/Index'],
            'shortlisting' => ['/app/shortlisting', 'Shortlisting/Index'],
            'integrations' => ['/app/integrations', 'Integrations/Index'],
            'team' => ['/app/team', 'Team/Index'],
            'settings' => ['/app/settings', 'Settings/Index'],
            'publishers' => ['/app/publishers', 'Publishers/Index'],
        ];
    }

    /** @dataProvider migratedRoutes */
    public function test_route_renders_react_component_under_app(string $url, string $component): void
    {
        [, , $u] = $this->agency();
        $this->actingAs($u)->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    /**
     * البادئة المشتركة تتبع مكان التقديم الفعلي — وهي أساس كل روابط الصفحة.
     * @dataProvider migratedRoutes
     */
    public function test_shared_base_matches_mount_prefix(string $url, string $component): void
    {
        [, , $u] = $this->agency();
        $this->actingAs($u)->get($url)
            ->assertInertia(fn (Assert $page) => $page->where('base', '/app'));
    }

    public function test_beta_twin_still_serves_same_component_with_beta_base(): void
    {
        [, , $u] = $this->agency();
        $this->actingAs($u)->get('/beta/reports')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('base', '/beta'));
    }

    /** @dataProvider migratedRoutes */
    public function test_guest_is_redirected(string $url, string $component): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    /** الإعدادات بوابة إدارية — الأدوار العرضية لا تصل إليها تحت /app كما تحت /beta. */
    public function test_settings_denied_for_viewer_role(): void
    {
        [, , $u] = $this->agency('viewer');
        $this->actingAs($u)->get('/app/settings')->assertForbidden();
    }

    public function test_team_denied_for_viewer_role(): void
    {
        [, , $u] = $this->agency('viewer');
        $this->actingAs($u)->get('/app/team')->assertForbidden();
    }
}
