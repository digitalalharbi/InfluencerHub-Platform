<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** التقارير React/Inertia — عرض تجميعات حقيقية + عزل مستأجر. */
class InertiaReportsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(int $clients = 0, int $campaigns = 0, string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        for ($i = 1; $i < $clients; $i++) {
            Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-' . $i, 'display_name' => 'ع' . $i, 'type' => 'company', 'status' => 'active']);
        }
        for ($i = 0; $i < $campaigns; $i++) {
            Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id . '-' . $i, 'client_id' => $cl->id,
                'name' => 'حملة' . $i, 'status' => 'active', 'budget_minor' => 1000000, 'currency' => 'SAR']);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/reports')->assertRedirect('/login');
    }

    public function test_renders_aggregates(): void
    {
        [, , $u] = $this->agency(3, 2);
        $this->actingAs($u)->get('/beta/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('kpis.clients', 3)->where('kpis.campaigns', 2)
                ->has('financial')->has('breakdowns.campaigns')->has('creatorsByType'));
    }

    public function test_reports_are_tenant_isolated(): void
    {
        $this->agency(5, 4);
        [, , $u2] = $this->agency(2, 1);
        $this->actingAs($u2)->get('/beta/reports')
            ->assertInertia(fn (Assert $page) => $page->where('kpis.clients', 2)->where('kpis.campaigns', 1));
    }
}
