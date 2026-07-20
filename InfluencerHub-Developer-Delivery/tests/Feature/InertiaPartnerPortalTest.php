<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember, PartnerClientLink};
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** بوابة الشريك React/Inertia — لوحة بالنطاقات + طلبات مقيّدة بالعملاء المرتبطين. */
class InertiaPartnerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:ExternalAgency,2:Tenant,3:Client} */
    private function partner(string $status = 'approved'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'شريك', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $a = ExternalAgency::create(['tenant_id' => $t->id, 'agency_number' => 'PA-' . $t->id, 'name' => 'وكالة شريكة', 'status' => $status]);
        ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $a->id, 'user_id' => $u->id, 'role' => 'partner_admin', 'status' => 'active', 'accepted_at' => now()]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل مرتبط', 'type' => 'company', 'status' => 'active']);
        PartnerClientLink::create(['tenant_id' => $t->id, 'external_agency_id' => $a->id, 'client_id' => $client->id, 'scopes' => ['view_briefs', 'view_reports'], 'status' => 'active']);
        TenantContext::reset();
        return [$u, $a, $t, $client];
    }

    public function test_unapproved_agency_blocked(): void
    {
        [$u] = $this->partner('pending');
        $this->actingAs($u)->get('/beta/partner')->assertForbidden();
    }

    public function test_dashboard_shows_scoped_links(): void
    {
        [$u] = $this->partner();
        $this->actingAs($u)->get('/beta/partner')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PartnerPortal/Dashboard')
                ->where('stats.clients', 1)
                ->where('links.0.client', 'عميل مرتبط')
                ->has('links.0.scopes', 2));
    }

    public function test_create_request_for_linked_client(): void
    {
        [$u, $a, $t, $client] = $this->partner();
        $this->actingAs($u)->post('/beta/partner/requests', [
            'client_id' => $client->id, 'type' => 'content', 'title' => 'طلب شريك', 'priority' => 'normal',
        ])->assertRedirect('/beta/partner/requests');
        TenantContext::set($t->id);
        $this->assertSame(1, ServiceRequest::where('requester_agency_id', $a->id)->count());
        TenantContext::reset();
    }

    public function test_cannot_create_for_unlinked_client(): void
    {
        [$u, , $t] = $this->partner();
        TenantContext::set($t->id);
        $other = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-x' . $t->id, 'display_name' => 'غير مرتبط', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        $this->actingAs($u)->post('/beta/partner/requests', [
            'client_id' => $other->id, 'type' => 'content', 'title' => 'x', 'priority' => 'normal',
        ])->assertStatus(422);
    }

    public function test_request_idor_safe(): void
    {
        [$u1] = $this->partner();
        [, $a2, $t2, $c2] = $this->partner();
        TenantContext::set($t2->id);
        $srB = ServiceRequest::create(['tenant_id' => $t2->id, 'request_number' => 'SR-B', 'requester_type' => 'partner',
            'requester_agency_id' => $a2->id, 'client_id' => $c2->id, 'type' => 'other', 'title' => 'B', 'priority' => 'normal',
            'status' => 'submitted', 'requested_by' => $u1->id]);
        TenantContext::reset();
        $this->actingAs($u1)->get("/beta/partner/requests/{$srB->id}")->assertNotFound();
    }
}
