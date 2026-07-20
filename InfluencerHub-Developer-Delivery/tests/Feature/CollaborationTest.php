<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\Campaigns\Services\CampaignWorkflowService;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Collaborations\Services\{CollaborationWorkflowService, CreatorMatchingService};
use App\Domain\Communications\Models\Notification;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 8 — التعاونات + المطابقة: عرض/قبول/تسليم/اعتماد + عزل + إشعارات + اقتراح مبدعين. */
class CollaborationTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $ag = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $ag->id, 'role' => 'agency_admin', 'status' => 'active']);
        // اشتراك يُفعّل بوابة المبدع (تتحقّق منه EnsureCreator)
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new CreateSubscription)->handle($org, $v);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $creatorUser = User::create(['name' => 'Cr', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-1', 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active',
            'primary_platform' => 'instagram', 'followers_count' => 80000, 'content_categories' => ['fashion', 'beauty'], 'user_id' => $creatorUser->id]);
        TenantContext::reset();
        return [$t, $org, $ag, $c, $cr, $creatorUser];
    }
    private function wf(): CollaborationWorkflowService { return app(CollaborationWorkflowService::class); }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }
    private function offer(Tenant $t, Creator $cr, int $by): Collaboration
    {
        return $this->wf()->offer($t->id, ['creator_id' => $cr->id, 'title' => 'تعاون رمضان', 'fee_minor' => 250000], $by);
    }

    public function test_offer_creates_and_notifies_creator(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $col = $this->offer($t, $cr, $ag->id);
        $this->assertStringStartsWith('CO-', $col->collaboration_number);
        $this->assertEquals('offered', $col->status);
        TenantContext::bypass(true);
        $this->assertTrue(Notification::where('user_id', $cu->id)->exists()); // أُشعِر المبدع
        TenantContext::reset();
    }

    public function test_full_lifecycle_accept_start_submit_approve_complete(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $col = $this->offer($t, $cr, $ag->id);
        $col = $this->wf()->accept($col, $cu->id);
        $this->assertEquals('accepted', $col->status);
        $col = $this->wf()->startWork($col, $cu->id);
        $col = $this->wf()->submit($col, $cu->id, 'رابط المحتوى');
        $this->assertEquals('submitted', $col->status);
        $col = $this->wf()->approve($col, $ag->id);
        $col = $this->wf()->complete($col, $ag->id);
        $this->assertEquals('completed', $col->status);
        $this->assertNotNull($this->fresh($col)->completed_at);
    }

    public function test_creator_decline_records_reason(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $col = $this->wf()->decline($this->offer($t, $cr, $ag->id), $cu->id, 'مشغول حاليًا');
        $this->assertEquals('declined', $col->status);
        $this->assertEquals('مشغول حاليًا', $this->fresh($col)->decline_reason);
    }

    public function test_agency_request_revision_from_submitted(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $col = $this->wf()->submit($this->wf()->startWork($this->wf()->accept($this->offer($t, $cr, $ag->id), $cu->id), $cu->id), $cu->id);
        $col = $this->wf()->requestRevision($col, $ag->id, 'أضِف شعار العلامة');
        $this->assertEquals('in_progress', $col->status);
    }

    public function test_invalid_transition_blocked(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $col = $this->offer($t, $cr, $ag->id); // offered
        $this->expectException(\RuntimeException::class); // offered → submitted غير مسموح
        $this->wf()->submit($col, $cu->id);
    }

    public function test_offer_rejects_unknown_creator(): void
    {
        [$t, $org, $ag] = $this->ctx();
        $this->expectException(\RuntimeException::class);
        $this->wf()->offer($t->id, ['creator_id' => 999999, 'title' => 'x', 'fee_minor' => 0], $ag->id);
    }

    public function test_offer_from_deliverable_inherits_fee(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $cw = app(CampaignWorkflowService::class);
        $cm = $cw->create($t->id, ['client_id' => $c->id, 'name' => 'حملة'], $ag->id);
        $d = $cw->addDeliverable($cm, ['type' => 'reel', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => 500000], $ag->id);
        $col = $this->wf()->offerFromDeliverable($d, $cr->id, $ag->id);
        $this->assertEquals(500000, $col->fee_minor);
        $this->assertEquals($cm->id, $col->campaign_id);
        $this->assertEquals($c->id, $col->client_id);
    }

    /** تعاونان على مخرَج واحد = أجران ومستحقّان على عمل واحد. */
    public function test_second_offer_on_the_same_deliverable_is_refused(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $cw = app(CampaignWorkflowService::class);
        $cm = $cw->create($t->id, ['client_id' => $c->id, 'name' => 'حملة'], $ag->id);
        $d = $cw->addDeliverable($cm, ['type' => 'reel', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => 500000], $ag->id);
        $first = $this->wf()->offerFromDeliverable($d, $cr->id, $ag->id);

        try {
            $this->wf()->offerFromDeliverable($d, $cr->id, $ag->id);
            $this->fail('أُنشئ تعاون ثانٍ على المخرَج نفسه');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($first->collaboration_number, $e->getMessage(),
                'المنع لا يدلّ على التعاون القائم');
        }

        TenantContext::bypass(true);
        $this->assertSame(1, Collaboration::where('deliverable_id', $d->id)->count());
        TenantContext::reset();
    }

    /** المبدع متعدّد القدرات يأخذ مخرجين في حملة واحدة — عملان لا تكرار. */
    public function test_the_same_creator_may_take_two_deliverables_in_one_campaign(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $cw = app(CampaignWorkflowService::class);
        $cm = $cw->create($t->id, ['client_id' => $c->id, 'name' => 'حملة'], $ag->id);
        $post = $cw->addDeliverable($cm, ['type' => 'post', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => 300000], $ag->id);
        $ugc = $cw->addDeliverable($cm, ['type' => 'ugc', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => 200000], $ag->id);

        $this->wf()->offerFromDeliverable($post, $cr->id, $ag->id);
        $this->wf()->offerFromDeliverable($ugc, $cr->id, $ag->id);

        TenantContext::bypass(true);
        $this->assertSame(2, Collaboration::where('campaign_id', $cm->id)->where('creator_id', $cr->id)->count(),
            'المنع بالحملة حجب مخرَجًا مشروعًا');
        TenantContext::reset();
    }

    /** الاعتذار يُنهي العرض ولا يشغل المخرَج — فإعادة العرض مشروعة. */
    public function test_a_declined_offer_frees_the_deliverable(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $cw = app(CampaignWorkflowService::class);
        $cm = $cw->create($t->id, ['client_id' => $c->id, 'name' => 'حملة'], $ag->id);
        $d = $cw->addDeliverable($cm, ['type' => 'reel', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => 500000], $ag->id);
        $first = $this->wf()->offerFromDeliverable($d, $cr->id, $ag->id);
        $this->wf()->decline($first, $cu->id);

        $second = $this->wf()->offerFromDeliverable($d, $cr->id, $ag->id);
        $this->assertNotSame($first->id, $second->id, 'الاعتذار أقفل المخرَج على المبدع للأبد');
    }

    public function test_creator_acts_on_own_collaboration_over_http(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $col = $this->offer($t, $cr, $ag->id);
        $this->actingAs($cu)->post("/creator/collaborations/{$col->id}/accept")->assertRedirect();
        $this->assertEquals('accepted', $this->fresh($col)->status);
    }

    public function test_creator_cannot_act_on_others_collaboration(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        // مبدع آخر بحساب مستقل
        TenantContext::bypass(true);
        $otherUser = User::create(['name' => 'O', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $otherCreator = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-2', 'type' => 'influencer', 'display_name' => 'آخر', 'status' => 'active', 'user_id' => $otherUser->id]);
        TenantContext::reset();
        $col = $this->offer($t, $cr, $ag->id); // للمبدع الأول
        $this->actingAs($otherUser)->post("/creator/collaborations/{$col->id}/accept")->assertNotFound(); // IDOR
        $this->assertEquals('offered', $this->fresh($col)->status);
    }

    public function test_matching_ranks_by_platform_and_reach(): void
    {
        [$t, $org, $ag, $c, $cr, $cu] = $this->ctx();
        $cw = app(CampaignWorkflowService::class);
        $cm = $cw->create($t->id, ['client_id' => $c->id, 'name' => 'حملة'], $ag->id);
        $d = $cw->addDeliverable($cm, ['type' => 'reel', 'platform' => 'instagram', 'quantity' => 1], $ag->id);
        $suggestions = app(CreatorMatchingService::class)->suggestForDeliverable($d);
        $this->assertGreaterThan(0, $suggestions->count());
        // مبدعنا (إنستغرام + 80k) يحصل على درجة موجبة
        $top = $suggestions->first();
        $this->assertGreaterThanOrEqual(50, $top['score']);
    }

    public function test_collaborations_tenant_isolated(): void
    {
        [$t1, , $ag1, , $cr1, $cu1] = $this->ctx();
        [$t2, , $ag2, , $cr2, $cu2] = $this->ctx();
        $col2 = $this->offer($t2, $cr2, $ag2->id);
        // مبدع المستأجر 1 لا يصل لتعاون المستأجر 2
        $this->actingAs($cu1)->get("/creator/collaborations/{$col2->id}")->assertNotFound();
    }
}
