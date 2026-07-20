<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyInvitation, ExternalAgencyMember, PartnerClientLink};
use App\Domain\Partners\Services\ExternalAgencyWorkflowService;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Phase 5 — الوكالات الخارجية: قبول بالأحداث + دعوة + روابط مُنطّقة + بوابة شريك fail-closed + عزل. */
class ExternalAgencyTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        return [$t, $org, $u, $c];
    }
    private function wf(): ExternalAgencyWorkflowService { return app(ExternalAgencyWorkflowService::class); }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }
    private function approvedAgency(Tenant $t, User $actor): ExternalAgency
    {
        $a = $this->wf()->createDraft($t->id, ['name' => 'شريك'], $actor->id);
        $a = $this->wf()->submit($a, $actor->id);
        $a = $this->wf()->startReview($a, $actor->id);
        return $this->wf()->approve($a, $actor->id);
    }

    public function test_full_onboarding_happy_path(): void
    {
        [$t, $org, $u] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'وكالة الرياض'], $u->id);
        $this->assertEquals('draft', $a->status);
        $this->assertStringStartsWith('PA-', $a->agency_number);
        $a = $this->wf()->submit($a, $u->id);
        $this->assertEquals('submitted', $a->status);
        $a = $this->wf()->startReview($a, $u->id);
        $a = $this->wf()->approve($a, $u->id, 'موثّقة');
        $this->assertEquals('approved', $a->status);
        TenantContext::bypass(true);
        $this->assertEquals(4, \App\Domain\Partners\Models\ExternalAgencyStatusHistory::where('external_agency_id', $a->id)->count());
        TenantContext::reset();
    }

    public function test_invalid_transition_blocked(): void
    {
        [$t, $org, $u] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'x'], $u->id);
        $this->expectException(\RuntimeException::class); // draft → approved غير مسموح
        $this->wf()->approve($a, $u->id);
    }

    public function test_changes_requested_then_resubmit(): void
    {
        [$t, $org, $u] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'x'], $u->id);
        $a = $this->wf()->submit($a, $u->id);
        $a = $this->wf()->startReview($a, $u->id);
        $a = $this->wf()->requestChanges($a, $u->id, 'أكمل بيانات الاتصال');
        $this->assertEquals('changes_requested', $this->fresh($a)->status);
        $a = $this->wf()->submit($this->fresh($a), $u->id);
        $this->assertEquals('submitted', $a->status);
    }

    public function test_cannot_edit_after_submit(): void
    {
        [$t, $org, $u] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'x'], $u->id);
        $a = $this->wf()->submit($a, $u->id);
        $this->expectException(\RuntimeException::class);
        $this->wf()->updateDraft($this->fresh($a), ['name' => 'y'], $u->id);
    }

    public function test_invite_member_stores_hashed_token(): void
    {
        [$t, $org, $u] = $this->ctx();
        $a = $this->approvedAgency($t, $u);
        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/invite", ['email' => 'p@ex.com', 'role' => 'partner_admin'])
            ->assertRedirect()->assertSessionHas('invite_token');
        TenantContext::bypass(true);
        $inv = ExternalAgencyInvitation::where('email', 'p@ex.com')->first();
        TenantContext::reset();
        $this->assertEquals(64, strlen($inv->token_hash));
    }

    public function test_cannot_link_before_approved(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'x'], $u->id); // draft
        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/links", ['client_id' => $c->id])->assertStatus(422);
    }

    public function test_link_client_with_scopes(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $a = $this->approvedAgency($t, $u);
        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/links", [
            'client_id' => $c->id, 'scopes' => ['view_briefs', 'submit_content'],
        ])->assertRedirect();
        TenantContext::bypass(true);
        $link = PartnerClientLink::where('external_agency_id', $a->id)->where('client_id', $c->id)->first();
        TenantContext::reset();
        $this->assertEqualsCanonicalizing(['view_briefs', 'submit_content'], $link->scopes);
    }

    public function test_link_brand_must_belong_to_client(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $a = $this->approvedAgency($t, $u);
        // علامة لعميل آخر
        TenantContext::bypass(true);
        $c2 = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-x', 'display_name' => 'ع2', 'type' => 'company', 'status' => 'active']);
        $brandOfC2 = Brand::create(['tenant_id' => $t->id, 'client_id' => $c2->id, 'name' => 'ب', 'status' => 'draft', 'slug' => Str::random(6)]);
        TenantContext::reset();
        // ربط علامة c2 تحت العميل c → يفشل (لا تنتمي)
        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/links", ['client_id' => $c->id, 'brand_id' => $brandOfC2->id])->assertNotFound();
    }

    public function test_partner_portal_denies_unapproved_agency(): void
    {
        [$t, $org, $u] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'x'], $u->id); // draft, غير معتمدة
        TenantContext::bypass(true);
        $partnerUser = User::create(['name' => 'P', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $a->id, 'user_id' => $partnerUser->id, 'role' => 'partner_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        $this->actingAs($partnerUser)->get('/partner/dashboard')->assertForbidden(); // fail-closed: غير معتمدة
    }

    public function test_partner_sees_only_own_links(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $a = $this->approvedAgency($t, $u);
        // ربط + عضو شريك
        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/links", ['client_id' => $c->id, 'scopes' => ['view_briefs']]);
        TenantContext::bypass(true);
        $partnerUser = User::create(['name' => 'P', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $a->id, 'user_id' => $partnerUser->id, 'role' => 'partner_admin', 'status' => 'active', 'accepted_at' => now()]);
        // وكالة أخرى بعميل آخر (لا يجب أن يراها)
        $a2 = $this->approvedAgency($t, $u);
        $c2 = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-z', 'display_name' => 'عميل مخفي ZZZ', 'type' => 'company', 'status' => 'active']);
        PartnerClientLink::create(['tenant_id' => $t->id, 'external_agency_id' => $a2->id, 'client_id' => $c2->id, 'scopes' => ['view_briefs'], 'status' => 'active']);
        TenantContext::reset();
        // بوابة الشريك صارت React — العزل يُتحقّق من الحمولة لا من HTML
        $this->actingAs($partnerUser)->get('/partner/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PartnerPortal/Dashboard')
                ->where('base', '/partner')
                ->where('links', fn ($links) => collect($links)->pluck('client')->doesntContain('عميل مخفي ZZZ')));
    }

    public function test_suspended_partner_member_denied(): void
    {
        [$t, $org, $u] = $this->ctx();
        $a = $this->approvedAgency($t, $u);
        TenantContext::bypass(true);
        $partnerUser = User::create(['name' => 'P', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $a->id, 'user_id' => $partnerUser->id, 'role' => 'partner_admin', 'status' => 'suspended']);
        TenantContext::reset();
        $this->actingAs($partnerUser)->get('/partner/dashboard')->assertForbidden();
    }

    /* ===== /app بعد التحويل من Blade — الصفحات وسير العمل ===== */

    public function test_app_partners_index_renders_react(): void
    {
        [$t, , $u] = $this->ctx();
        $this->wf()->createDraft($t->id, ['name' => 'شريك أول'], $u->id);

        $this->actingAs($u)->get('/app/partner-agencies')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partners/Index')->where('base', '/app')
                ->has('agencies.data', 1)->where('summary.draft', 1)->where('canCreate', true));
    }

    public function test_app_partner_detail_renders_react(): void
    {
        [$t, , $u] = $this->ctx();
        $a = $this->approvedAgency($t, $u);

        $this->actingAs($u)->get("/app/partner-agencies/{$a->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Partners/Show')->where('base', '/app')
                ->where('agency.isActivePartner', true)
                ->where('can.manage', true)
                ->has('links')->has('members')->has('scopeOptions'));
    }

    public function test_app_store_creates_draft_and_opens_it(): void
    {
        [$t, , $u] = $this->ctx();
        $res = $this->actingAs($u)->post('/app/partner-agencies', ['name' => 'وكالة جديدة']);

        TenantContext::bypass(true);
        $a = ExternalAgency::where('tenant_id', $t->id)->where('name', 'وكالة جديدة')->first();
        TenantContext::reset();
        $this->assertNotNull($a, 'لم تُنشأ الوكالة');
        $this->assertSame('draft', $a->status);
        $res->assertRedirect("/app/partner-agencies/{$a->id}");
    }

    public function test_app_workflow_transitions_change_status(): void
    {
        [$t, , $u] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'شريك'], $u->id);

        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/submit")->assertRedirect();
        $this->assertSame('submitted', $this->fresh($a)->status);

        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/start")->assertRedirect();
        $this->assertSame('under_review', $this->fresh($a)->status);

        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/approve")->assertRedirect();
        $this->assertSame('approved', $this->fresh($a)->status);
    }

    public function test_app_request_changes_requires_reason(): void
    {
        [$t, , $u] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'شريك'], $u->id);
        $a = $this->wf()->submit($a, $u->id);
        $this->wf()->startReview($a, $u->id);

        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/request-changes", [])
            ->assertSessionHasErrors('reason');
    }

    public function test_app_unknown_action_is_not_found(): void
    {
        [$t, , $u] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'شريك'], $u->id);
        $this->actingAs($u)->post("/app/partner-agencies/{$a->id}/destroy-everything")->assertNotFound();
    }

    /** الاعتماد قرار يفتح وصولًا لطرف خارجي — بوابته manage لا update. */
    public function test_app_approve_denied_without_manage_ability(): void
    {
        [$t, , $admin] = $this->ctx();
        $a = $this->wf()->createDraft($t->id, ['name' => 'شريك'], $admin->id);
        $a = $this->wf()->submit($a, $admin->id);
        $this->wf()->startReview($a, $admin->id);

        // عضو بدور عرض داخل نفس المستأجر — يرى الوكالة ولا يعتمدها
        TenantContext::bypass(true);
        $org = Organization::where('tenant_id', $t->id)->firstOrFail();
        $viewer = User::create(['name' => 'V', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        TenantContext::reset();

        $this->actingAs($viewer)->post("/app/partner-agencies/{$a->id}/approve")->assertForbidden();
    }

    public function test_app_partners_guest_redirected(): void
    {
        $this->get('/app/partner-agencies')->assertRedirect('/login');
    }
}
