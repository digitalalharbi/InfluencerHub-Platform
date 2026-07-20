<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\ContentWorkflowService;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 9 — المحتوى والموافقات: مبدع→مراجعة الوكالة→موافقة العميل→نشر + إصدارات + عزل. */
class ContentTest extends TestCase
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
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new CreateSubscription)->handle($org, $v);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $clientUser = User::create(['name' => 'Cl', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $clientUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        $creatorUser = User::create(['name' => 'Cr', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-1', 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active', 'user_id' => $creatorUser->id]);
        TenantContext::reset();
        return [$t, $org, $ag, $c, $clientUser, $cr, $creatorUser];
    }
    private function wf(): ContentWorkflowService { return app(ContentWorkflowService::class); }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }
    private function make(Tenant $t, Client $c, Creator $cr, int $by): ContentItem
    {
        return $this->wf()->create($t->id, ['creator_id' => $cr->id, 'client_id' => $c->id, 'title' => 'ريل المدارس', 'type' => 'reel', 'media_url' => 'https://ex.com/v1'], $by, 'creator');
    }

    public function test_create_and_number(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->make($t, $c, $cr, $cru->id);
        $this->assertStringStartsWith('CN-', $item->content_number);
        $this->assertEquals('draft', $item->status);
        $this->assertEquals(1, $item->version);
    }

    public function test_full_pipeline_to_published(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->make($t, $c, $cr, $cru->id);
        $item = $this->wf()->submit($item, $cru->id);
        $item = $this->wf()->startAgencyReview($item, $ag->id);
        $item = $this->wf()->sendToClient($item, $ag->id);
        $this->assertEquals('client_review', $item->status);
        $item = $this->wf()->clientApprove($item, $cu->id);
        $this->assertEquals('approved', $item->status);
        $item = $this->wf()->publish($item, $ag->id);
        $this->assertEquals('published', $item->status);
        $this->assertNotNull($this->fresh($item)->published_at);
        // قراران: agency approved + client approved
        TenantContext::bypass(true);
        $this->assertEquals(2, \App\Domain\Content\Models\ContentApproval::where('content_item_id', $item->id)->where('decision', 'approved')->count());
        TenantContext::reset();
    }

    public function test_agency_request_changes_then_resubmit_bumps_version(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->wf()->startAgencyReview($this->wf()->submit($this->make($t, $c, $cr, $cru->id), $cru->id), $ag->id);
        $item = $this->wf()->requestChanges($item, $ag->id, 'agency', 'حسّن الإضاءة');
        $this->assertEquals('changes_requested', $item->status);
        $item = $this->wf()->submit($this->fresh($item), $cru->id);
        $this->assertEquals('submitted', $item->status);
        $this->assertEquals(2, $item->version); // إصدار جديد
    }

    public function test_client_request_changes(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->wf()->sendToClient($this->wf()->startAgencyReview($this->wf()->submit($this->make($t, $c, $cr, $cru->id), $cru->id), $ag->id), $ag->id);
        $item = $this->wf()->requestChanges($item, $cu->id, 'client', 'غيّر الأغنية');
        $this->assertEquals('changes_requested', $this->fresh($item)->status);
    }

    public function test_invalid_transition_blocked(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->make($t, $c, $cr, $cru->id); // draft
        $this->expectException(\RuntimeException::class); // draft → approved غير مسموح
        $this->wf()->clientApprove($item, $cu->id);
    }

    public function test_creator_cannot_edit_after_submit(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->wf()->submit($this->make($t, $c, $cr, $cru->id), $cru->id);
        $this->expectException(\RuntimeException::class);
        $this->wf()->updateDraft($this->fresh($item), ['title' => 'x'], $cru->id);
    }

    public function test_creator_submits_over_http(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $this->actingAs($cru)->post('/creator/content', ['title' => 'ستوري', 'type' => 'story', 'media_url' => 'https://ex.com/s'])->assertRedirect('/creator/content');
        TenantContext::bypass(true);
        $item = ContentItem::where('title', 'ستوري')->first();
        TenantContext::reset();
        $this->assertNotNull($item);
        $this->assertEquals($cr->id, $item->creator_id);
        $this->actingAs($cru)->post("/creator/content/{$item->id}/submit")->assertRedirect();
        $this->assertEquals('submitted', $this->fresh($item)->status);
    }

    public function test_client_approves_over_http(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->wf()->sendToClient($this->wf()->startAgencyReview($this->wf()->submit($this->make($t, $c, $cr, $cru->id), $cru->id), $ag->id), $ag->id);
        $this->actingAs($cu)->post("/client/content/{$item->id}/approve")->assertRedirect();
        $this->assertEquals('approved', $this->fresh($item)->status);
    }

    public function test_client_cannot_see_content_before_client_review(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->wf()->startAgencyReview($this->wf()->submit($this->make($t, $c, $cr, $cru->id), $cru->id), $ag->id); // agency_review
        $this->actingAs($cu)->get("/client/content/{$item->id}")->assertNotFound(); // ليس بعد
    }

    public function test_creator_cannot_access_others_content(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        TenantContext::bypass(true);
        $otherUser = User::create(['name' => 'O', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $otherCr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-2', 'type' => 'influencer', 'display_name' => 'آخر', 'status' => 'active', 'user_id' => $otherUser->id]);
        TenantContext::reset();
        $item = $this->make($t, $c, $cr, $cru->id);
        $this->actingAs($otherUser)->get("/creator/content/{$item->id}")->assertNotFound();
    }

    public function test_content_tenant_isolated(): void
    {
        [$t1, , $ag1, $c1, $cu1, $cr1, $cru1] = $this->ctx();
        [$t2, , $ag2, $c2, $cu2, $cr2, $cru2] = $this->ctx();
        $item2 = $this->make($t2, $c2, $cr2, $cru2->id);
        $this->actingAs($cru1)->get("/creator/content/{$item2->id}")->assertNotFound();
    }

    // ===== الإشعارات: كل انتظار يُعلَن لمن يُنتظَر منه =====

    /**
     * البحث عن المستقبِل كان يجري بعد أن يُعيد `transition()` ضبط سياق
     * المستأجر، وTenantScope مغلق افتراضيًا — فيعود فارغًا ويُتخطّى الإشعار
     * بصمت. ثلاثة مسارات كانت ميّتة: المبدع والوكالة والعميل.
     */
    public function test_submitting_content_reaches_the_campaign_owner(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->campaignContent($t, $org, $ag, $c, $cr, $cru);

        $this->wf()->submit($item, $cru->id);

        $this->assertNotNull($this->notificationFor($t, $ag->id, 'content.submitted'),
            'المحتوى صار «مُقدَّمًا» ولم تعلم به الوكالة');
    }

    /** والتمرير للعميل ينتظر قراره — فيُبلَّغ. */
    public function test_sending_content_to_the_client_reaches_the_client(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->campaignContent($t, $org, $ag, $c, $cr, $cru);
        $this->wf()->submit($item, $cru->id);
        $this->wf()->startAgencyReview($item, $ag->id);

        $this->wf()->sendToClient($item, $ag->id);

        $this->assertNotNull($this->notificationFor($t, $cu->id, 'content.client_review'),
            'المحتوى بانتظار اعتماد العميل ولم يعلم به');
    }

    /** وطلب التعديل يصل المبدع — كان يسقط لنفس السبب. */
    public function test_requesting_changes_reaches_the_creator(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->campaignContent($t, $org, $ag, $c, $cr, $cru);
        $this->wf()->submit($item, $cru->id);
        $this->wf()->startAgencyReview($item, $ag->id);

        $this->wf()->requestChanges($item, $ag->id, 'agency', 'الإضاءة ضعيفة');

        $this->assertNotNull($this->notificationFor($t, $cru->id, 'content.update'),
            'طُلب تعديل ولم يعلم به المبدع');
    }

    // ===== إثبات النشر والنتائج =====

    /** الإثبات هو رابط المنشور الحيّ، ولا يُقبل قبل وقوع النشر. */
    public function test_publish_proof_is_refused_before_the_content_is_published(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->campaignContent($t, $org, $ag, $c, $cr, $cru);

        $this->expectException(\RuntimeException::class);
        $this->wf()->recordPublishProof($item, 'https://x.com/p/1', null, $ag->id);
    }

    public function test_publish_proof_is_recorded_with_its_author(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->publishedContent($t, $org, $ag, $c, $cr, $cru, $cu);

        $this->wf()->recordPublishProof($item, 'https://instagram.com/p/winter1', 'منشور ثابت', $ag->id);

        $f = $this->fresh($item);
        $this->assertSame('https://instagram.com/p/winter1', $f->published_url);
        $this->assertSame($ag->id, (int) $f->proof_by);
        $this->assertNotNull($f->proof_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.proof_recorded', 'auditable_id' => $item->id]);
    }

    /** النتائج تُنسَب إلى منشور معروف — فلا تُقبل بلا إثبات. */
    public function test_results_are_refused_without_publish_proof(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->publishedContent($t, $org, $ag, $c, $cr, $cru, $cu);

        $this->expectException(\RuntimeException::class);
        $this->wf()->recordResults($item, ['reach' => 1000], $ag->id);
    }

    /** ما لم يُقَس يبقى فارغًا لا صفرًا، والمصدر يُوسَم. */
    public function test_unmeasured_metrics_stay_null_and_the_source_is_stamped(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->publishedContent($t, $org, $ag, $c, $cr, $cru, $cu);
        $this->wf()->recordPublishProof($item, 'https://instagram.com/p/winter1', null, $ag->id);

        $this->wf()->recordResults($item, ['reach' => 12000, 'clicks' => null], $ag->id);

        $f = $this->fresh($item);
        $this->assertSame(12000, (int) $f->reach);
        $this->assertNull($f->clicks, 'ما لم يُقَس كُتب صفرًا فصار ادّعاءً');
        $this->assertSame('manual', $f->results_source);
    }

    /** إدخال فارغ بالكامل يُرفض بدل أن يُسجَّل «قياس» بلا أرقام. */
    public function test_empty_results_are_refused(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $item = $this->publishedContent($t, $org, $ag, $c, $cr, $cru, $cu);
        $this->wf()->recordPublishProof($item, 'https://instagram.com/p/winter1', null, $ag->id);

        $this->expectException(\RuntimeException::class);
        $this->wf()->recordResults($item, ['reach' => null, 'clicks' => ''], $ag->id);
    }

    private function publishedContent(Tenant $t, $org, User $ag, Client $c, Creator $cr, User $cru, User $cu): ContentItem
    {
        $item = $this->campaignContent($t, $org, $ag, $c, $cr, $cru);
        $this->wf()->submit($item, $cru->id);
        $this->wf()->startAgencyReview($item, $ag->id);
        $this->wf()->sendToClient($item, $ag->id);
        $this->wf()->clientApprove($item, $cu->id);
        $this->wf()->publish($item, $ag->id);

        return $item;
    }

    private function campaignContent(Tenant $t, $org, User $ag, Client $c, Creator $cr, User $cru): ContentItem
    {
        $cw = app(\App\Domain\Campaigns\Services\CampaignWorkflowService::class);
        TenantContext::set($t->id, $org->id);
        $cm = $cw->create($t->id, ['client_id' => $c->id, 'name' => 'حملة'], $ag->id);
        TenantContext::reset();

        return $this->wf()->create($t->id, [
            'creator_id' => $cr->id, 'client_id' => $c->id, 'campaign_id' => $cm->id,
            'title' => 'ريل المدارس', 'type' => 'reel', 'media_url' => 'https://ex.com/v1',
        ], $cru->id, 'creator');
    }

    private function notificationFor(Tenant $t, int $userId, string $type)
    {
        TenantContext::bypass(true);
        $n = \App\Domain\Communications\Models\Notification::where('tenant_id', $t->id)
            ->where('user_id', $userId)->where('type', $type)->first();
        TenantContext::reset();

        return $n;
    }
}
