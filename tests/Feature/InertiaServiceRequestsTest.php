<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** طابور طلبات الخدمة React/Inertia — عرض، مؤشرات SLA، شرائح، عزل مستأجر. */
class InertiaServiceRequestsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(int $requests = 0): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'operations_manager', 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        for ($i = 0; $i < $requests; $i++) {
            ServiceRequest::create(['tenant_id' => $t->id, 'request_number' => 'SR-' . $t->id . '-' . $i,
                'requester_type' => 'client', 'requester_client_id' => $cl->id, 'client_id' => $cl->id,
                'type' => 'consultation', 'title' => 'طلب' . $i, 'priority' => 'normal', 'status' => 'submitted',
                'due_at' => now()->addHours(48)]);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/service-requests')->assertRedirect('/login');
    }

    public function test_renders_queue_with_summary_and_sla(): void
    {
        [, , $u] = $this->agency(3);
        $this->actingAs($u)->get('/beta/service-requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ServiceRequests/Index')
                ->where('summary.open', 3)
                ->has('requests.data', 3)
                ->where('requests.data.0.sla', 'ok')
                ->has('priorityLabels'));
    }

    public function test_queue_is_tenant_isolated(): void
    {
        $this->agency(4);
        [, , $u2] = $this->agency(2);
        $this->actingAs($u2)->get('/beta/service-requests')
            ->assertInertia(fn (Assert $page) => $page->where('summary.open', 2)->has('requests.data', 2));
    }

    private function firstRequest(int $tenantId): ServiceRequest
    {
        TenantContext::bypass(true);
        $s = ServiceRequest::where('tenant_id', $tenantId)->first();
        TenantContext::reset();
        return $s;
    }

    public function test_detail_renders_with_actions_and_history(): void
    {
        [$t, , $u] = $this->agency(1);
        $s = $this->firstRequest($t->id);
        $this->actingAs($u)->get("/beta/service-requests/{$s->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ServiceRequests/Show')
                ->where('request.id', $s->id)
                ->where('canHandle', true)
                ->has('actions')->has('agents')->has('comments')->has('history'));
    }

    public function test_triage_action_transitions_via_workflow(): void
    {
        [$t, , $u] = $this->agency(1);
        $s = $this->firstRequest($t->id); // submitted
        $this->actingAs($u)->post("/beta/service-requests/{$s->id}/triage")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('triage', $s->fresh()->status);
        TenantContext::reset();
    }

    public function test_assign_action_sets_assignee(): void
    {
        [$t, , $u] = $this->agency(1);
        $s = $this->firstRequest($t->id);
        $this->actingAs($u)->post("/beta/service-requests/{$s->id}/assign", ['assigned_to' => $u->id])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame($u->id, $s->fresh()->assigned_to);
        TenantContext::reset();
    }

    public function test_viewer_cannot_handle(): void
    {
        [$t, $org, $u] = $this->agency(1);
        // عضو بدور viewer (VIEW فقط، لا WRITE/handle)
        $viewer = User::create(['name' => 'ع', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        \App\Domain\Tenancy\Models\OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        $s = $this->firstRequest($t->id);
        $this->actingAs($viewer)->get("/beta/service-requests/{$s->id}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canHandle', false)->where('actions', []));
        $this->actingAs($viewer)->post("/beta/service-requests/{$s->id}/triage")->assertForbidden();
    }

    public function test_detail_is_idor_safe(): void
    {
        [$t1] = $this->agency(1);
        $other = $this->firstRequest($t1->id);
        [, , $u2] = $this->agency(0);
        $this->actingAs($u2)->get("/beta/service-requests/{$other->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade ===== */

    /** ينشئ طلب حملة قابلًا للتحويل ويعيد [tenant, user, requestId]. */
    private function campaignRequest(string $role = 'operations_manager'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $sr = ServiceRequest::create(['tenant_id' => $t->id, 'request_number' => 'SR-' . $t->id . '-C',
            'requester_type' => 'client', 'requester_client_id' => $cl->id, 'client_id' => $cl->id,
            'type' => 'campaign', 'title' => 'إطلاق منتج', 'priority' => 'normal', 'status' => 'submitted',
            'due_at' => now()->addHours(48)]);
        TenantContext::reset();

        return [$t, $u, $sr->id];
    }

    public function test_app_service_requests_renders_react(): void
    {
        [, , $u] = $this->agency(2);
        $this->actingAs($u)->get('/app/service-requests')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ServiceRequests/Index')->where('base', '/app'));
    }

    public function test_app_service_request_detail_offers_convert_for_campaign_request(): void
    {
        [, $u, $id] = $this->campaignRequest();
        $this->actingAs($u)->get("/app/service-requests/{$id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ServiceRequests/Show')
                ->where('base', '/app')
                ->where('canConvert', true));
    }

    /** طلب غير حملة لا يعرض زر التحويل — نفس شرط نسخة Blade. */
    public function test_app_non_campaign_request_hides_convert(): void
    {
        [$t, , $u] = $this->agency(1);
        TenantContext::bypass(true);
        $id = ServiceRequest::where('tenant_id', $t->id)->first()->id;
        TenantContext::reset();
        $this->actingAs($u)->get("/app/service-requests/{$id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canConvert', false));
    }

    /** التحويل ينشئ حملة فعليًا ويعيد التوجيه داخل /app لا /beta. */
    public function test_app_convert_creates_campaign_and_redirects_within_app(): void
    {
        [$t, $u, $id] = $this->campaignRequest();
        $res = $this->actingAs($u)->post("/app/service-requests/{$id}/convert-campaign");
        TenantContext::bypass(true);
        $campaign = \App\Domain\Campaigns\Models\Campaign::where('source_request_id', $id)->first();
        TenantContext::reset();
        $this->assertNotNull($campaign, 'لم تُنشأ الحملة من الطلب');
        $res->assertRedirect("/app/campaigns/{$campaign->id}");
    }

    /** نفس الإجراء من /beta يعيد التوجيه داخل /beta — البادئة تتبع مكان الطلب. */
    public function test_beta_convert_redirects_within_beta(): void
    {
        [, $u, $id] = $this->campaignRequest();
        $res = $this->actingAs($u)->post("/beta/service-requests/{$id}/convert-campaign");
        TenantContext::bypass(true);
        $campaign = \App\Domain\Campaigns\Models\Campaign::where('source_request_id', $id)->first();
        TenantContext::reset();
        $res->assertRedirect("/beta/campaigns/{$campaign->id}");
    }

    public function test_app_convert_denied_without_campaign_create_ability(): void
    {
        [, $u, $id] = $this->campaignRequest('viewer');
        $this->actingAs($u)->post("/app/service-requests/{$id}/convert-campaign")->assertForbidden();
    }

    public function test_app_service_requests_guest_redirected(): void
    {
        $this->get('/app/service-requests')->assertRedirect('/login');
    }

    /* ===== موجز الحملة ينتقل من الطلب إلى الحملة (لا إدخال مكرّر) ===== */

    /** @return array{0:Tenant,1:User,2:int} */
    private function briefedRequest(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $brand = \App\Domain\CRM\Models\Brand::create(['tenant_id' => $t->id, 'client_id' => $cl->id, 'name' => 'علامة', 'slug' => Str::random(6), 'status' => 'approved', 'current_version' => 1]);
        $sr = ServiceRequest::create([
            'tenant_id' => $t->id, 'request_number' => 'SR-B-' . $t->id,
            'requester_type' => 'client', 'requester_client_id' => $cl->id, 'client_id' => $cl->id,
            'brand_id' => $brand->id, 'type' => 'campaign', 'title' => 'حملة موجزة',
            'priority' => 'normal', 'status' => 'submitted', 'due_at' => now()->addHours(48),
            'budget_minor' => 5000000, 'currency' => 'SAR',
            'preferred_start_date' => '2026-09-01', 'preferred_end_date' => '2026-09-30',
            'platforms' => ['instagram', 'tiktok'], 'scope_notes' => 'عشرة منشورات',
        ]);
        TenantContext::reset();

        return [$t, $u, $sr->id];
    }

    /** الموجز يظهر للوكالة قبل التحويل حتى تعرف ما سينتقل. */
    public function test_request_detail_exposes_campaign_brief(): void
    {
        [, $u, $id] = $this->briefedRequest();
        $this->actingAs($u)->get("/app/service-requests/{$id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('brief.hasAny', true)
                ->where('brief.budgetMinor', 5000000)
                ->where('brief.startDate', '2026-09-01')
                ->where('brief.brand', 'علامة')
                ->has('brief.platforms', 2)
                ->where('convertedCampaign', null));
    }

    /** جوهر الرحلة: كل ما أدخله العميل ينتقل للحملة بلا إعادة إدخال. */
    public function test_convert_carries_budget_brand_dates_into_campaign(): void
    {
        [$t, $u, $id] = $this->briefedRequest();
        $this->actingAs($u)->post("/app/service-requests/{$id}/convert-campaign")->assertRedirect();

        TenantContext::bypass(true);
        $c = \App\Domain\Campaigns\Models\Campaign::where('source_request_id', $id)->firstOrFail();
        $sr = ServiceRequest::find($id);
        TenantContext::reset();

        $this->assertSame(5000000, (int) $c->budget_minor, 'الميزانية لم تنتقل');
        $this->assertSame($sr->brand_id, $c->brand_id, 'العلامة لم تنتقل');
        $this->assertSame('2026-09-01', $c->start_date?->format('Y-m-d'), 'تاريخ البداية لم ينتقل');
        $this->assertSame('2026-09-30', $c->end_date?->format('Y-m-d'), 'تاريخ النهاية لم ينتقل');
        $this->assertSame('SAR', $c->currency);
    }

    /** التحويل مرّتين لا ينشئ حملة ثانية، والواجهة تعرض الحملة القائمة. */
    public function test_second_convert_returns_same_campaign_and_ui_links_to_it(): void
    {
        [$t, $u, $id] = $this->briefedRequest();
        $this->actingAs($u)->post("/app/service-requests/{$id}/convert-campaign");
        $this->actingAs($u)->post("/app/service-requests/{$id}/convert-campaign");

        TenantContext::bypass(true);
        $count = \App\Domain\Campaigns\Models\Campaign::where('source_request_id', $id)->count();
        TenantContext::reset();
        $this->assertSame(1, $count, 'أُنشئت حملة مكرّرة');

        $this->actingAs($u)->get("/app/service-requests/{$id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('convertedCampaign.id'));
    }

    /** العميل يُدخل الموجز مرّة واحدة من بوابته. */
    public function test_client_can_submit_brief_with_request(): void
    {
        [$t, , ] = $this->briefedRequest();
        TenantContext::bypass(true);
        $cl = Client::where('tenant_id', $t->id)->firstOrFail();
        $cu = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        \App\Domain\CRM\Models\ClientMember::create(['tenant_id' => $t->id, 'client_id' => $cl->id, 'user_id' => $cu->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();

        $this->actingAs($cu)->post('/client/requests', [
            'type' => 'campaign', 'title' => 'حملة من العميل', 'priority' => 'normal',
            'budget' => 12500.50, 'preferred_start_date' => '2026-10-01',
            'platforms' => ['snapchat'], 'scope_notes' => 'ستوري يومي',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $sr = ServiceRequest::where('title', 'حملة من العميل')->firstOrFail();
        TenantContext::reset();
        $this->assertSame(1250050, (int) $sr->budget_minor, 'الريال لم يُحوَّل إلى وحدات صغرى');
        $this->assertSame(['snapchat'], $sr->platforms);
    }
}
