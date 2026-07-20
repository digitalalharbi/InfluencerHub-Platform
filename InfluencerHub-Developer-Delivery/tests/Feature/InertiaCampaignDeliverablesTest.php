<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** مخرجات الحملة من واجهة الوكالة React — إضافة/حذف عبر CampaignWorkflowService + بوابة الدور + عزل. */
class InertiaCampaignDeliverablesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Tenant,2:Campaign} */
    private function agent(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $client->id,
            'name' => 'حملة', 'status' => 'draft', 'budget_minor' => 100000, 'currency' => 'SAR']);
        TenantContext::reset();
        return [$u, $t, $cm];
    }

    public function test_show_exposes_manage_flag(): void
    {
        [$u, , $cm] = $this->agent('agency_admin');
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Campaigns/Show')->where('canManage', true)->has('deliverableTypes'));
    }

    public function test_admin_can_add_and_remove_deliverable(): void
    {
        [$u, $t, $cm] = $this->agent('agency_admin');
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/deliverables", ['type' => 'reel', 'quantity' => 3, 'platform' => 'instagram'])
            ->assertRedirect();
        TenantContext::set($t->id);
        $d = CampaignDeliverable::where('campaign_id', $cm->id)->first();
        $this->assertNotNull($d);
        TenantContext::reset();

        $this->actingAs($u)->delete("/beta/campaigns/{$cm->id}/deliverables/{$d->id}")->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame(0, CampaignDeliverable::where('campaign_id', $cm->id)->count());
        TenantContext::reset();
    }

    public function test_add_validates_type(): void
    {
        [$u, , $cm] = $this->agent('agency_admin');
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/deliverables", ['type' => 'bogus', 'quantity' => 1])->assertSessionHasErrors('type');
    }

    public function test_viewer_cannot_manage_deliverables(): void
    {
        [$u, , $cm] = $this->agent('viewer');
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/deliverables", ['type' => 'reel', 'quantity' => 1])->assertForbidden();
    }

    public function test_deliverable_idor_across_campaigns(): void
    {
        [$u1] = $this->agent('agency_admin');
        [, , $cmB] = $this->agent('agency_admin');
        $this->actingAs($u1)->post("/beta/campaigns/{$cmB->id}/deliverables", ['type' => 'reel', 'quantity' => 1])->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade: انتقالات + مطابقة مبدعين ===== */

    private function deliverable(Campaign $cm, ?string $platform = 'tiktok'): CampaignDeliverable
    {
        TenantContext::bypass(true);
        $d = CampaignDeliverable::create([
            'tenant_id' => $cm->tenant_id, 'campaign_id' => $cm->id, 'type' => 'post',
            'platform' => $platform, 'quantity' => 1, 'status' => 'pending', 'fee_minor' => 50000, 'currency' => 'SAR',
        ]);
        TenantContext::reset();

        return $d;
    }

    private function creator(int $tenantId, string $platform = 'tiktok', string $name = 'مبدع مطابق'): \App\Domain\Creators\Models\Creator
    {
        TenantContext::bypass(true);
        $c = \App\Domain\Creators\Models\Creator::create([
            'tenant_id' => $tenantId, 'creator_number' => 'CR-' . Str::random(5), 'type' => 'influencer',
            'display_name' => $name, 'status' => 'active', 'primary_platform' => $platform, 'followers_count' => 60000,
        ]);
        TenantContext::reset();

        return $c;
    }

    public function test_app_campaign_show_exposes_state_actions(): void
    {
        [$u, , $cm] = $this->agent();
        $this->actingAs($u)->get("/app/campaigns/{$cm->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show')->where('base', '/app')
                // حملة مسودة → الإجراء المتاح هو النقل للتخطيط
                ->where('actions.0.0', 'plan'));
    }

    public function test_app_campaign_transition_changes_status(): void
    {
        [$u, , $cm] = $this->agent();
        $this->actingAs($u)->post("/app/campaigns/{$cm->id}/plan")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('planning', Campaign::find($cm->id)->status);
        TenantContext::reset();
    }

    public function test_app_campaign_transition_rejects_unknown_action(): void
    {
        [$u, , $cm] = $this->agent();
        $this->actingAs($u)->post("/app/campaigns/{$cm->id}/self-destruct")->assertNotFound();
    }

    public function test_app_campaign_transition_denied_for_viewer(): void
    {
        [$u, , $cm] = $this->agent('viewer');
        $this->actingAs($u)->post("/app/campaigns/{$cm->id}/plan")->assertForbidden();
    }

    public function test_app_suggest_ranks_matching_creator(): void
    {
        [$u, $t, $cm] = $this->agent();
        $d = $this->deliverable($cm);
        $this->creator($t->id); // نفس المنصّة → درجة أعلى

        $this->actingAs($u)->get("/app/campaigns/{$cm->id}/deliverables/{$d->id}/suggest")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Suggest')->where('base', '/app')
                ->where('suggestions.0.name', 'مبدع مطابق')
                ->where('suggestions.0.alreadyOffered', false)
                ->where('canOffer', true));
    }

    /**
     * الوكالة تصل هذه الشاشة من إشعار «أنشئ التعاون مع المعتمَدين» — فيجب أن
     * تعرف من اعتمده العميل، وأن يتصدّر القائمة بدل أن يُدفن بدرجة مطابقة أدنى.
     */
    public function test_app_suggest_marks_and_leads_with_the_client_approved_creator(): void
    {
        [$u, $t, $cm] = $this->agent();
        $d = $this->deliverable($cm);
        $matching = $this->creator($t->id);          // درجة مطابقة أعلى
        $approved = $this->creator($t->id, 'instagram', 'معتمَد من العميل');

        $svc = app(\App\Domain\Campaigns\Services\ShortlistService::class);
        TenantContext::set($t->id);
        $sl = $svc->getOrCreate($cm);
        $svc->addCreator($sl->currentVersion(), $approved);
        $svc->submit($sl);
        $svc->clientDecision($sl->fresh()->currentVersion()->items()->first(), 'approved');
        TenantContext::reset();

        $this->actingAs($u)->get("/app/campaigns/{$cm->id}/deliverables/{$d->id}/suggest")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Suggest')
                // المعتمَد يتصدّر رغم أن مطابقته أدنى
                ->where('suggestions.0.name', 'معتمَد من العميل')
                ->where('suggestions.0.clientApproved', true)
                ->where('suggestions.1.clientApproved', false));
    }

    /** مخرَج حملة أخرى لا يُفتح بتبديل الرقم في الرابط. */
    public function test_app_suggest_rejects_deliverable_of_another_campaign(): void
    {
        [$u, , $cm] = $this->agent();
        [, , $other] = $this->agent();
        $foreign = $this->deliverable($other);
        $this->actingAs($u)->get("/app/campaigns/{$cm->id}/deliverables/{$foreign->id}/suggest")->assertNotFound();
    }

    public function test_app_offer_creates_collaboration_and_marks_offered(): void
    {
        [$u, $t, $cm] = $this->agent();
        $d = $this->deliverable($cm);
        $cr = $this->creator($t->id);

        $this->actingAs($u)->post("/app/campaigns/{$cm->id}/deliverables/{$d->id}/offer", ['creator_id' => $cr->id])
            ->assertRedirect();

        TenantContext::bypass(true);
        $collab = \App\Domain\Collaborations\Models\Collaboration::where('campaign_id', $cm->id)->first();
        TenantContext::reset();
        $this->assertNotNull($collab, 'لم يُنشأ التعاون من المخرَج');
        $this->assertSame($cr->id, (int) $collab->creator_id);

        // العرض الثاني يظهر كمعروض عليه فلا يتكرّر دون قصد
        $this->actingAs($u)->get("/app/campaigns/{$cm->id}/deliverables/{$d->id}/suggest")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('suggestions.0.alreadyOffered', true));
    }

    public function test_app_offer_denied_for_viewer(): void
    {
        [$u, $t, $cm] = $this->agent('viewer');
        $d = $this->deliverable($cm);
        $cr = $this->creator($t->id);
        $this->actingAs($u)->post("/app/campaigns/{$cm->id}/deliverables/{$d->id}/offer", ['creator_id' => $cr->id])
            ->assertForbidden();
    }
}
