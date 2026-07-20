<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember, PartnerClientLink};
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Requests\Services\ServiceRequestWorkflowService;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Phase 6 — طلبات الخدمة: آلة حالة + SLA + مصادر (عميل/شريك مُنطّق) + رؤية التعليقات + عزل. */
class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $agency = User::create(['name' => 'Ag', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $agency->id, 'role' => 'agency_admin', 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $clientUser = User::create(['name' => 'Cl', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $clientUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$t, $org, $agency, $c, $clientUser];
    }
    private function wf(): ServiceRequestWorkflowService { return app(ServiceRequestWorkflowService::class); }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }
    private function make(Tenant $t, Client $c, int $by, string $priority = 'normal'): ServiceRequest
    {
        return $this->wf()->create($t->id, ['requester_type' => 'client', 'requester_client_id' => $c->id, 'client_id' => $c->id,
            'type' => 'campaign', 'title' => 'حملة صيفية', 'priority' => $priority], $by);
    }

    public function test_create_sets_number_and_sla_due(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $urgent = $this->make($t, $c, $cu->id, 'urgent');
        $low = $this->make($t, $c, $cu->id, 'low');
        $this->assertStringStartsWith('SR-', $urgent->request_number);
        $this->assertEquals('submitted', $urgent->status);
        // SLA: urgent=4h, low=168h — due_at للعاجل أقرب
        $this->assertTrue($urgent->due_at->lt($low->due_at));
    }

    public function test_full_workflow_path(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $s = $this->make($t, $c, $cu->id);
        $s = $this->wf()->triage($s, $ag->id);
        $this->assertEquals('triage', $s->status);
        $s = $this->wf()->startWork($s, $ag->id);
        $s = $this->wf()->resolve($s, $ag->id, 'تم');
        $this->assertEquals('resolved', $s->status);
        $this->assertNotNull($this->fresh($s)->resolved_at);
        $s = $this->wf()->close($s, $ag->id);
        $this->assertEquals('closed', $s->status);
    }

    public function test_invalid_transition_blocked(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $s = $this->make($t, $c, $cu->id); // submitted
        $this->expectException(\RuntimeException::class); // submitted → resolved غير مسموح
        $this->wf()->resolve($s, $ag->id);
    }

    public function test_transition_uses_db_state_not_stale_model(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $s = $this->make($t, $c, $cu->id); // submitted
        TenantContext::set($t->id);
        $stale = ServiceRequest::whereKey($s->id)->first(); // نسخة submitted في الذاكرة
        TenantContext::reset();
        $this->wf()->triage($s, $ag->id); // القاعدة الآن triage
        // فرز النسخة القديمة يجب أن يُرفض (triage → triage غير مسموح)
        $this->expectException(\RuntimeException::class);
        $this->wf()->triage($stale, $ag->id);
    }

    public function test_reopen_from_resolved(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $s = $this->wf()->resolve($this->wf()->startWork($this->wf()->triage($this->make($t, $c, $cu->id), $ag->id), $ag->id), $ag->id);
        $s = $this->wf()->reopen($s, $ag->id, 'ناقص');
        $this->assertEquals('in_progress', $s->status);
        $this->assertNull($this->fresh($s)->resolved_at);
    }

    public function test_client_creates_request_over_http(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $this->actingAs($cu)->post('/client/requests', ['type' => 'content', 'title' => 'فيديو UGC', 'priority' => 'high'])->assertRedirect('/client/requests');
        TenantContext::bypass(true);
        $s = ServiceRequest::where('title', 'فيديو UGC')->first();
        TenantContext::reset();
        $this->assertNotNull($s);
        $this->assertEquals('client', $s->requester_type);
        $this->assertEquals($c->id, $s->requester_client_id);
    }

    public function test_client_cannot_see_internal_comments(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $s = $this->make($t, $c, $cu->id);
        $this->wf()->comment($s, $ag->id, 'agency', 'ملاحظة داخلية سرية ZZZ', true);
        $this->wf()->comment($s, $ag->id, 'agency', 'رد ظاهر للعميل', false);
        // الحمولة نفسها يجب ألا تحمل التعليق الداخلي — لا إخفاءً في الواجهة فقط
        $this->actingAs($cu)->get("/client/requests/{$s->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comments', fn ($c) => collect($c)->pluck('body')->contains('رد ظاهر للعميل')
                    && ! collect($c)->pluck('body')->contains('ملاحظة داخلية سرية ZZZ')))
            ->assertDontSee('ملاحظة داخلية سرية ZZZ');
    }

    public function test_client_cannot_view_other_clients_request(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        TenantContext::bypass(true);
        $c2 = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-x', 'display_name' => 'ع2', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        $other = $this->wf()->create($t->id, ['requester_type' => 'client', 'requester_client_id' => $c2->id, 'client_id' => $c2->id, 'type' => 'report', 'title' => 'سرّي', 'priority' => 'normal'], $ag->id);
        $this->actingAs($cu)->get("/client/requests/{$other->id}")->assertNotFound();
    }

    public function test_partner_can_only_request_for_linked_client(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        // شريك معتمد + عضو + رابط للعميل c
        $wfA = app(\App\Domain\Partners\Services\ExternalAgencyWorkflowService::class);
        $agency = $wfA->approve($wfA->startReview($wfA->submit($wfA->createDraft($t->id, ['name' => 'شريك'], $ag->id), $ag->id), $ag->id), $ag->id);
        TenantContext::bypass(true);
        $pu = User::create(['name' => 'P', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $agency->id, 'user_id' => $pu->id, 'role' => 'partner_admin', 'status' => 'active', 'accepted_at' => now()]);
        PartnerClientLink::create(['tenant_id' => $t->id, 'external_agency_id' => $agency->id, 'client_id' => $c->id, 'scopes' => ['submit_content'], 'status' => 'active']);
        $unlinked = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-u', 'display_name' => 'غير مرتبط', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        // طلب لعميل مرتبط → ينجح
        $this->actingAs($pu)->post('/partner/requests', ['client_id' => $c->id, 'type' => 'content', 'title' => 'محتوى', 'priority' => 'normal'])->assertRedirect('/partner/requests');
        // طلب لعميل غير مرتبط → 422 fail-closed
        $this->actingAs($pu)->post('/partner/requests', ['client_id' => $unlinked->id, 'type' => 'content', 'title' => 'ممنوع', 'priority' => 'normal'])->assertStatus(422);
    }

    public function test_agency_assign_and_transition_over_http(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $s = $this->make($t, $c, $cu->id);
        $this->actingAs($ag)->post("/app/service-requests/{$s->id}/assign", ['assigned_to' => $ag->id])->assertRedirect();
        $this->assertEquals($ag->id, $this->fresh($s)->assigned_to);
        $this->actingAs($ag)->post("/app/service-requests/{$s->id}/triage")->assertRedirect();
        $this->assertEquals('triage', $this->fresh($s)->status);
    }

    public function test_requests_are_tenant_isolated(): void
    {
        [$t1, , $ag1, $c1, $cu1] = $this->ctx();
        [$t2, , $ag2, $c2, $cu2] = $this->ctx();
        $s2 = $this->make($t2, $c2, $cu2->id);
        // عضو المستأجر 1 لا يرى طلب المستأجر 2
        $this->actingAs($cu1)->get("/client/requests/{$s2->id}")->assertNotFound();
    }
}
