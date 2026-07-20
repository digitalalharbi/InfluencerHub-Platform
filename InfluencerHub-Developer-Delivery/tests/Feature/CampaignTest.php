<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\Campaigns\Services\CampaignWorkflowService;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Requests\Services\ServiceRequestWorkflowService;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Phase 7 — منشئ الحملات: آلة حالة + مخرجات + تحويل من طلب + ميزانية بوحدات صغرى + عزل. */
class CampaignTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-1', 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active']);
        TenantContext::reset();
        return [$t, $org, $u, $c, $cr];
    }
    private function wf(): CampaignWorkflowService { return app(CampaignWorkflowService::class); }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }
    private function make(Tenant $t, Client $c, int $by): Campaign
    {
        return $this->wf()->create($t->id, ['client_id' => $c->id, 'name' => 'حملة الصيف', 'budget_minor' => 5000000, 'currency' => 'SAR'], $by);
    }

    public function test_create_sets_number_and_draft(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $cm = $this->make($t, $c, $u->id);
        $this->assertStringStartsWith('CM-', $cm->campaign_number);
        $this->assertEquals('draft', $cm->status);
        $this->assertEquals(5000000, $cm->budget_minor);
    }

    public function test_cannot_activate_without_deliverables(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $cm = $this->wf()->plan($this->make($t, $c, $u->id), $u->id);
        $this->expectException(\RuntimeException::class); // لا مخرجات
        $this->wf()->activate($cm, $u->id);
    }

    public function test_full_lifecycle_with_deliverable(): void
    {
        [$t, $org, $u, $c, $cr] = $this->ctx();
        $cm = $this->make($t, $c, $u->id);
        $this->wf()->addDeliverable($cm, ['type' => 'reel', 'platform' => 'instagram', 'quantity' => 3, 'creator_id' => $cr->id, 'fee_minor' => 300000], $u->id);
        $cm = $this->wf()->plan($cm, $u->id);
        $cm = $this->wf()->activate($cm, $u->id);
        $this->assertEquals('active', $cm->status);
        $cm = $this->wf()->pause($cm, $u->id, 'مراجعة');
        $cm = $this->wf()->resume($cm, $u->id);
        $cm = $this->wf()->complete($cm, $u->id);
        $this->assertEquals('completed', $cm->status);
    }

    public function test_deliverable_with_creator_is_assigned(): void
    {
        [$t, $org, $u, $c, $cr] = $this->ctx();
        $cm = $this->make($t, $c, $u->id);
        $d = $this->wf()->addDeliverable($cm, ['type' => 'post', 'quantity' => 1, 'creator_id' => $cr->id, 'fee_minor' => 100000], $u->id);
        $this->assertEquals('assigned', $d->status);
        // committed = fee * qty (نحمّل العلاقة ضمن سياق المستأجر كما في العرض الفعلي)
        TenantContext::bypass(true);
        $this->assertEquals(100000, $cm->fresh()->load('deliverables')->committedMinor());
        TenantContext::reset();
    }

    public function test_cannot_add_deliverable_after_active(): void
    {
        [$t, $org, $u, $c, $cr] = $this->ctx();
        $cm = $this->make($t, $c, $u->id);
        $this->wf()->addDeliverable($cm, ['type' => 'post', 'quantity' => 1], $u->id);
        $cm = $this->wf()->activate($this->wf()->plan($cm, $u->id), $u->id);
        $this->expectException(\RuntimeException::class);
        $this->wf()->addDeliverable($cm, ['type' => 'story', 'quantity' => 1], $u->id);
    }

    public function test_invalid_transition_blocked(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $cm = $this->make($t, $c, $u->id); // draft
        $this->expectException(\RuntimeException::class); // draft → active غير مسموح
        $this->wf()->activate($cm, $u->id);
    }

    public function test_convert_from_campaign_request(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $req = app(ServiceRequestWorkflowService::class)->create($t->id, [
            'requester_type' => 'client', 'requester_client_id' => $c->id, 'client_id' => $c->id,
            'type' => 'campaign', 'title' => 'حملة رمضان', 'description' => 'تفاصيل', 'priority' => 'high',
        ], $u->id);
        $cm = $this->wf()->convertFromRequest($req, $u->id);
        $this->assertEquals('حملة رمضان', $cm->name);
        $this->assertEquals($req->id, $cm->source_request_id);
        // idempotent — لا يُنشئ حملة ثانية
        $cm2 = $this->wf()->convertFromRequest($this->fresh($req), $u->id);
        $this->assertEquals($cm->id, $cm2->id);
    }

    public function test_convert_rejects_non_campaign_request(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $req = app(ServiceRequestWorkflowService::class)->create($t->id, [
            'requester_type' => 'client', 'requester_client_id' => $c->id, 'client_id' => $c->id,
            'type' => 'report', 'title' => 'تقرير', 'priority' => 'normal',
        ], $u->id);
        $this->expectException(\RuntimeException::class);
        $this->wf()->convertFromRequest($req, $u->id);
    }

    public function test_agency_creates_campaign_over_http(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        $this->actingAs($u)->post('/app/campaigns', ['client_id' => $c->id, 'name' => 'حملة HTTP', 'budget_minor' => 1000000])
            ->assertRedirect();
        TenantContext::bypass(true);
        $this->assertDatabaseHas('campaigns', ['name' => 'حملة HTTP', 'tenant_id' => $t->id]);
        TenantContext::reset();
    }

    public function test_campaigns_tenant_isolated(): void
    {
        [$t1, , $u1, $c1] = $this->ctx();
        [$t2, , $u2, $c2] = $this->ctx();
        $cm2 = $this->make($t2, $c2, $u2->id);
        $this->actingAs($u1)->get("/app/campaigns/{$cm2->id}")->assertNotFound();
    }

    public function test_client_sees_non_draft_campaign_without_fees(): void
    {
        [$t, $org, $u, $c, $cr] = $this->ctx();
        TenantContext::bypass(true);
        $clientUser = User::create(['name' => 'Cl', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        \App\Domain\CRM\Models\ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $clientUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        $cm = $this->make($t, $c, $u->id);
        $this->wf()->addDeliverable($cm, ['type' => 'reel', 'quantity' => 2, 'creator_id' => $cr->id, 'fee_minor' => 777000], $u->id);
        $cm = $this->wf()->activate($this->wf()->plan($cm, $u->id), $u->id);

        // العميل يرى الحملة النشطة ولا يرى قيمة الأجر
        // بوابة العميل صارت React — نتحقّق من الحمولة: لا تصل قيمة الأجر إطلاقًا
        $this->actingAs($clientUser)->get("/client/campaigns/{$cm->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('campaign.name', 'حملة الصيف')
                ->where('deliverables', fn ($d) => collect($d)->every(
                    fn ($item) => ! array_key_exists('feeMinor', (array) $item) && ! array_key_exists('fee_minor', (array) $item))))
            ->assertDontSee('777000')->assertDontSee('7,770');
    }

    public function test_client_cannot_see_draft_campaign(): void
    {
        [$t, $org, $u, $c] = $this->ctx();
        TenantContext::bypass(true);
        $clientUser = User::create(['name' => 'Cl', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        \App\Domain\CRM\Models\ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $clientUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        $cm = $this->make($t, $c, $u->id); // draft
        $this->actingAs($clientUser)->get("/client/campaigns/{$cm->id}")->assertNotFound();
    }

    /**
     * الإغلاق كان بلا شرط: تُغلَق الحملة وفيها تعاون معروض ومستحقّ لم يُصرف —
     * فيُخفي الإغلاق عملًا قائمًا ومالًا لم يُدفع بدل أن يُنهيهما.
     */
    public function test_a_campaign_with_open_obligations_cannot_be_closed(): void
    {
        [$t, $org, $u, $client] = $this->ctx();
        $wf = app(CampaignWorkflowService::class);
        TenantContext::set($t->id, $org->id);
        $cm = $wf->create($t->id, ['client_id' => $client->id, 'name' => 'حملة'], $u->id);
        $d = $wf->addDeliverable($cm, ['type' => 'post', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => 500000], $u->id);
        $wf->plan($cm, $u->id);
        $wf->activate($cm, $u->id);

        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . Str::random(4),
            'type' => 'influencer', 'display_name' => 'م', 'status' => 'active']);
        app(\App\Domain\Collaborations\Services\CollaborationWorkflowService::class)
            ->offerFromDeliverable($d, $cr->id, $u->id);
        TenantContext::reset();

        try {
            $wf->complete($cm, $u->id);
            $this->fail('أُغلقت الحملة وفيها تعاون مفتوح');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('تعاونًا لم يُغلَق', $e->getMessage(),
                'المنع لا يقول ما المتبقّي');
        }

        $this->assertSame('active', $this->freshCampaign($cm)->status);
    }

    /** وبإغلاق الالتزامات تُغلَق الحملة. */
    public function test_a_campaign_without_open_obligations_closes(): void
    {
        [$t, $org, $u, $client] = $this->ctx();
        $wf = app(CampaignWorkflowService::class);
        TenantContext::set($t->id, $org->id);
        $cm = $wf->create($t->id, ['client_id' => $client->id, 'name' => 'حملة'], $u->id);
        $wf->addDeliverable($cm, ['type' => 'post', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => 500000], $u->id);
        $wf->plan($cm, $u->id);
        $wf->activate($cm, $u->id);
        TenantContext::reset();

        $this->assertSame([], $wf->openObligations($cm));
        $wf->complete($cm, $u->id);
        $this->assertSame('completed', $this->freshCampaign($cm)->status);
    }

    private function freshCampaign($c)
    {
        TenantContext::bypass(true);
        $f = $c->fresh();
        TenantContext::reset();

        return $f;
    }
}
