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

/** قائمة الحملات React/Inertia — عرض، عدّادات، عزل مستأجر. */
class InertiaCampaignsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(int $campaigns = 0): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        for ($i = 0; $i < $campaigns; $i++) {
            Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id . '-' . $i, 'client_id' => $cl->id,
                'name' => 'حملة' . $i, 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR']);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/campaigns')->assertRedirect('/login');
    }

    public function test_renders_campaigns_with_summary(): void
    {
        [, , $u] = $this->agency(3);
        $this->actingAs($u)->get('/beta/campaigns')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Index')
                ->where('summary.total', 3)
                ->where('summary.active', 3)
                ->has('campaigns.data', 3));
    }

    public function test_campaigns_are_tenant_isolated(): void
    {
        $this->agency(4);
        [, , $u2] = $this->agency(2);
        $this->actingAs($u2)->get('/beta/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('summary.total', 2)->has('campaigns.data', 2));
    }

    public function test_detail_renders_command_center(): void
    {
        [$t, , $u] = $this->agency(1);
        TenantContext::bypass(true);
        $cm = Campaign::where('tenant_id', $t->id)->first();
        TenantContext::reset();
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show')
                ->where('campaign.id', $cm->id)
                ->has('command.stages')->has('command.next_action')
                ->has('readiness.items')->has('timeline')
                ->has('deliverables')->has('collaborations')->has('content'));
    }

    public function test_detail_is_idor_safe_across_tenants(): void
    {
        [$t1] = $this->agency(1);
        TenantContext::bypass(true);
        $other = Campaign::where('tenant_id', $t1->id)->first();
        TenantContext::reset();
        [, , $u2] = $this->agency(0);
        $this->actingAs($u2)->get("/beta/campaigns/{$other->id}")->assertNotFound();
    }
}
