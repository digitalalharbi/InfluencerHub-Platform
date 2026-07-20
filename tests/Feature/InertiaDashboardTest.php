<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** طبقة React/Inertia — /beta تعرض مكوّن Dashboard ببيانات المستأجر فقط، والضيف يُحوَّل للدخول. */
class InertiaDashboardTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(int $clients = 0, string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        for ($i = 0; $i < $clients; $i++) {
            Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-' . $i, 'display_name' => 'ع' . $i, 'type' => 'company', 'status' => 'active']);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/beta')->assertRedirect('/login');
    }

    public function test_manager_sees_operational_overview_and_team(): void
    {
        [, , $u] = $this->agency(3, 'agency_admin');
        $this->actingAs($u)->get('/beta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('canSeeTeam', true)
                ->has('brief')->has('myWork')->has('team')
                ->where('overview.kpis.clientsTotal', 3)
                ->where('auth.user.name', 'أحمد')
                ->where('workspace', 'وكالة'));
    }

    public function test_non_manager_gets_personal_only_no_financials(): void
    {
        // مراجع محتوى: يرى مساحة عمله فقط — لا نظرة مالية ولا فريق (البيانات لا تُرسَل أصلًا)
        [, , $u] = $this->agency(4, 'content_reviewer');
        $this->actingAs($u)->get('/beta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('canSeeTeam', false)
                ->has('brief')->has('myWork')
                ->where('team', null)
                ->missing('overview'));
    }

    public function test_dashboard_is_tenant_isolated(): void
    {
        $this->agency(5);            // مستأجر آخر لديه 5 عملاء
        [, , $u2] = $this->agency(2); // المستأجر الحالي لديه 2
        $this->actingAs($u2)->get('/beta')
            ->assertInertia(fn (Assert $page) => $page->where('overview.kpis.clientsTotal', 2));
    }
}
