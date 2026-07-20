<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** صفحات IA الجديدة: الترشيحات (مركز) + مهامي + تصفية القائمة بالصلاحية (nav.can). */
class InertiaIAPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Tenant,2:Client} */
    private function agent(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        return [$u, $t, $client];
    }

    public function test_shortlisting_hub_lists_campaigns(): void
    {
        [$u, $t, $client] = $this->agent();
        TenantContext::set($t->id);
        Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $client->id,
            'name' => 'حملة', 'status' => 'active', 'budget_minor' => 100000, 'currency' => 'SAR']);
        TenantContext::reset();
        $this->actingAs($u)->get('/beta/shortlisting')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Shortlisting/Index')->has('campaigns.data', 1)->where('campaigns.data.0.slLabel', 'لم يبدأ'));
    }

    public function test_my_tasks_aggregates_assigned(): void
    {
        [$u, $t, $client] = $this->agent();
        TenantContext::set($t->id);
        ServiceRequest::create(['tenant_id' => $t->id, 'request_number' => 'SR-' . $t->id, 'requester_type' => 'client',
            'requester_client_id' => $client->id, 'client_id' => $client->id, 'type' => 'content', 'title' => 'مهمة',
            'priority' => 'normal', 'status' => 'in_progress', 'assigned_to' => $u->id, 'requested_by' => $u->id]);
        TenantContext::reset();
        $this->actingAs($u)->get('/beta/my-tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('MyTasks/Index')->has('myRequests', 1)->where('myRequests.0.title', 'مهمة'));
    }

    public function test_nav_can_flags_admin(): void
    {
        [$admin] = $this->agent('agency_admin');
        $this->actingAs($admin)->get('/beta/my-tasks')
            ->assertInertia(fn (Assert $page) => $page->where('nav.can.admin', true)->where('nav.can.reviews', true));
    }

    public function test_nav_can_flags_viewer(): void
    {
        [$viewer] = $this->agent('viewer');
        $this->actingAs($viewer)->get('/beta/my-tasks')
            ->assertInertia(fn (Assert $page) => $page->where('nav.can.admin', false)->where('nav.can.reviews', false));
    }
}
