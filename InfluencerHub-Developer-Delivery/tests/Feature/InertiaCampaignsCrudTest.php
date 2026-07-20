<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** إنشاء الحملة من واجهة الوكالة React (/beta) — يعيد استخدام CampaignWorkflowService + بوابة الدور + ربط العلامة بالعميل. */
class InertiaCampaignsCrudTest extends TestCase
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

    public function test_index_exposes_create_data(): void
    {
        [$u] = $this->agent('agency_admin');
        $this->actingAs($u)->get('/beta/campaigns')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Campaigns/Index')->where('canCreate', true)->has('clients', 1));
    }

    public function test_admin_can_create_campaign(): void
    {
        [$u, $t, $client] = $this->agent('agency_admin');
        $this->actingAs($u)->post('/beta/campaigns', ['client_id' => $client->id, 'name' => 'حملة الصيف', 'budget_minor' => 500000, 'currency' => 'SAR'])
            ->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame(1, Campaign::where('name', 'حملة الصيف')->where('client_id', $client->id)->count());
        TenantContext::reset();
    }

    public function test_brand_must_belong_to_client(): void
    {
        [$u, $t, $client] = $this->agent('agency_admin');
        TenantContext::set($t->id);
        $otherClient = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-x' . $t->id, 'display_name' => 'آخر', 'type' => 'company', 'status' => 'active']);
        $foreignBrand = Brand::create(['tenant_id' => $t->id, 'client_id' => $otherClient->id, 'name' => 'علامة', 'slug' => 'b-' . $t->id, 'status' => 'draft']);
        TenantContext::reset();
        $this->actingAs($u)->post('/beta/campaigns', ['client_id' => $client->id, 'brand_id' => $foreignBrand->id, 'name' => 'x'])
            ->assertNotFound();
    }

    public function test_viewer_cannot_create(): void
    {
        [$u, , $client] = $this->agent('viewer');
        $this->actingAs($u)->post('/beta/campaigns', ['client_id' => $client->id, 'name' => 'x'])->assertForbidden();
    }

    public function test_create_validates(): void
    {
        [$u, , $client] = $this->agent('agency_admin');
        $this->actingAs($u)->post('/beta/campaigns', ['client_id' => $client->id])->assertSessionHasErrors('name');
        $this->actingAs($u)->post('/beta/campaigns', ['name' => 'x'])->assertSessionHasErrors('client_id');
    }

    public function test_admin_can_update_campaign(): void
    {
        [$u, $t, $client] = $this->agent('agency_admin');
        TenantContext::set($t->id);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $client->id,
            'name' => 'قديم', 'status' => 'draft', 'budget_minor' => 0, 'currency' => 'SAR']);
        TenantContext::reset();
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}", ['name' => 'محدّثة', 'budget_minor' => 900000])->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame('محدّثة', $cm->fresh()->name);
        TenantContext::reset();
    }

    public function test_viewer_cannot_update(): void
    {
        [$u, $t, $client] = $this->agent('viewer');
        TenantContext::set($t->id);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $client->id,
            'name' => 'x', 'status' => 'draft', 'budget_minor' => 0, 'currency' => 'SAR']);
        TenantContext::reset();
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}", ['name' => 'y'])->assertForbidden();
    }
}
