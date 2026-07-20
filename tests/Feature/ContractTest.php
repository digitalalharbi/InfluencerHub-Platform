<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractWorkflowService;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 10 — العقود: إصدار/إرسال/قبول الطرف/تفعيل/إنهاء + عزل + قبول داخل البوابة. */
class ContractTest extends TestCase
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
    private function wf(): ContractWorkflowService { return app(ContractWorkflowService::class); }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }
    private function creatorContract(Tenant $t, Creator $cr, int $by): Contract
    {
        return $this->wf()->create($t->id, ['party_type' => 'creator', 'creator_id' => $cr->id, 'title' => 'عقد تعاون', 'value_minor' => 900000, 'currency' => 'SAR', 'terms' => 'بنود'], $by);
    }

    public function test_create_creator_contract(): void
    {
        [$t, $org, $ag, $c, $cu, $cr] = $this->ctx();
        $ct = $this->creatorContract($t, $cr, $ag->id);
        $this->assertStringStartsWith('CT-', $ct->contract_number);
        $this->assertEquals('draft', $ct->status);
        $this->assertEquals(900000, $ct->value_minor);
    }

    /**
     * العقد من التعاون يرث ما تقرّر فعلًا بدل إعادة إدخاله — والعمودان
     * collaboration_id/campaign_id كانا يبقيان فارغين في كل عقد يُنشأ من الواجهة.
     */
    public function test_contract_issued_from_collaboration_inherits_its_links_and_value(): void
    {
        [$t, $org, $ag, $c, $cu, $cr] = $this->ctx();
        $col = $this->acceptedCollaboration($t, $org, $ag, $c, $cr);

        $ct = $this->wf()->createFromCollaboration($col, $ag->id);

        $this->assertSame($col->id, (int) $ct->collaboration_id, 'العقد لا يعرف تعاونه');
        $this->assertSame((int) $col->campaign_id, (int) $ct->campaign_id, 'العقد لا يعرف حملته');
        $this->assertSame((int) $col->creator_id, (int) $ct->creator_id);
        $this->assertSame((int) $col->fee_minor, (int) $ct->value_minor, 'الأجر أُعيد إدخاله بدل أن يُورَث');
        $this->assertSame('draft', $ct->status);
    }

    /** عقدان على تعاون واحد = التزامان ماليّان على عمل واحد. */
    public function test_a_second_contract_for_the_same_collaboration_is_refused(): void
    {
        [$t, $org, $ag, $c, $cu, $cr] = $this->ctx();
        $col = $this->acceptedCollaboration($t, $org, $ag, $c, $cr);
        $first = $this->wf()->createFromCollaboration($col, $ag->id);

        $this->expectException(\RuntimeException::class);
        $this->wf()->createFromCollaboration($col, $ag->id);
    }

    /** العقد يُصدَر بعد قبول المبدع — لا على عرض ما زال معلّقًا. */
    public function test_contract_cannot_be_issued_before_the_creator_accepts(): void
    {
        [$t, $org, $ag, $c, $cu, $cr] = $this->ctx();
        TenantContext::set($t->id);
        $col = app(\App\Domain\Collaborations\Services\CollaborationWorkflowService::class)
            ->offer($t->id, ['creator_id' => $cr->id, 'client_id' => $c->id, 'title' => 'تعاون', 'fee_minor' => 700000], $ag->id);
        TenantContext::reset();

        $this->expectException(\RuntimeException::class);
        $this->wf()->createFromCollaboration($col, $ag->id);
    }

    private function acceptedCollaboration(Tenant $t, $org, User $ag, Client $c, Creator $cr)
    {
        $cw = app(\App\Domain\Campaigns\Services\CampaignWorkflowService::class);
        TenantContext::set($t->id, $org->id);
        $cm = $cw->create($t->id, ['client_id' => $c->id, 'name' => 'حملة'], $ag->id);
        $d = $cw->addDeliverable($cm, ['type' => 'post', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => 700000], $ag->id);
        $col = app(\App\Domain\Collaborations\Services\CollaborationWorkflowService::class)
            ->offerFromDeliverable($d, $cr->id, $ag->id);
        $col = app(\App\Domain\Collaborations\Services\CollaborationWorkflowService::class)
            ->accept($col, $cr->user_id);
        TenantContext::reset();

        return $col;
    }

    /**
     * إرسال العقد كان لا يُبلّغ أحدًا: البحث عن الطرف يجري بعد إعادة ضبط سياق
     * المستأجر، وTenantScope مغلق افتراضيًا فيعود فارغًا ويُتخطّى الإشعار بصمت.
     */
    public function test_sending_a_creator_contract_reaches_the_creator(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $creatorUser] = $this->ctx();
        $ct = $this->creatorContract($t, $cr, $ag->id);
        $this->wf()->send($ct, $ag->id);

        TenantContext::bypass(true);
        $n = \App\Domain\Communications\Models\Notification::where('tenant_id', $t->id)
            ->where('user_id', $creatorUser->id)->where('type', 'contract.sent')->first();
        TenantContext::reset();

        $this->assertNotNull($n, 'العقد صار «مُرسَلًا» ولم يعلم به المبدع');
    }

    /** ونفس العطل كان يصيب عقد العميل — الطرفان معًا. */
    public function test_sending_a_client_contract_reaches_the_client_members(): void
    {
        [$t, $org, $ag, $c, $clientUser] = $this->ctx();
        $ct = $this->wf()->create($t->id, ['party_type' => 'client', 'client_id' => $c->id,
            'title' => 'عقد عميل', 'value_minor' => 500000, 'currency' => 'SAR'], $ag->id);
        $this->wf()->send($ct, $ag->id);

        TenantContext::bypass(true);
        $n = \App\Domain\Communications\Models\Notification::where('tenant_id', $t->id)
            ->where('user_id', $clientUser->id)->where('type', 'contract.sent')->first();
        TenantContext::reset();

        $this->assertNotNull($n, 'العقد صار «مُرسَلًا» ولم يعلم به العميل');
    }

    public function test_create_requires_party(): void
    {
        [$t, $org, $ag] = $this->ctx();
        $this->expectException(\RuntimeException::class);
        $this->wf()->create($t->id, ['party_type' => 'creator', 'title' => 'x'], $ag->id); // بلا creator_id
    }

    public function test_full_lifecycle_send_sign_activate_complete(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $ct = $this->creatorContract($t, $cr, $ag->id);
        $ct = $this->wf()->send($ct, $ag->id);
        $this->assertEquals('sent', $ct->status);
        $ct = $this->wf()->sign($ct, $cru->id, 'مبدع', 'creator');
        $this->assertEquals('signed', $ct->status);
        $this->assertEquals('مبدع', $this->fresh($ct)->signed_by_name);
        $ct = $this->wf()->activate($ct, $ag->id);
        $ct = $this->wf()->complete($ct, $ag->id);
        $this->assertEquals('completed', $ct->status);
    }

    public function test_cannot_edit_after_send(): void
    {
        [$t, $org, $ag, $c, $cu, $cr] = $this->ctx();
        $ct = $this->wf()->send($this->creatorContract($t, $cr, $ag->id), $ag->id);
        $this->expectException(\RuntimeException::class);
        $this->wf()->updateDraft($this->fresh($ct), ['title' => 'x'], $ag->id);
    }

    public function test_invalid_transition_blocked(): void
    {
        [$t, $org, $ag, $c, $cu, $cr] = $this->ctx();
        $ct = $this->creatorContract($t, $cr, $ag->id); // draft
        $this->expectException(\RuntimeException::class); // draft → active غير مسموح
        $this->wf()->activate($ct, $ag->id);
    }

    public function test_creator_signs_over_http(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $ct = $this->wf()->send($this->creatorContract($t, $cr, $ag->id), $ag->id);
        $this->actingAs($cru)->post("/creator/contracts/{$ct->id}/sign", ['signer_name' => 'رينـاد', 'agree' => '1'])->assertRedirect();
        $this->assertEquals('signed', $this->fresh($ct)->status);
        $this->assertEquals('رينـاد', $this->fresh($ct)->signed_by_name);
    }

    public function test_creator_cannot_see_draft_contract(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        $ct = $this->creatorContract($t, $cr, $ag->id); // draft — لم يُرسَل
        $this->actingAs($cru)->get("/creator/contracts/{$ct->id}")->assertNotFound();
    }

    public function test_client_signs_over_http(): void
    {
        [$t, $org, $ag, $c, $cu] = $this->ctx();
        $ct = $this->wf()->send($this->wf()->create($t->id, ['party_type' => 'client', 'client_id' => $c->id, 'title' => 'عقد خدمة', 'value_minor' => 100000], $ag->id), $ag->id);
        $this->actingAs($cu)->post("/client/contracts/{$ct->id}/sign", ['signer_name' => 'مدير العميل', 'agree' => '1'])->assertRedirect();
        $this->assertEquals('signed', $this->fresh($ct)->status);
    }

    public function test_creator_cannot_sign_others_contract(): void
    {
        [$t, $org, $ag, $c, $cu, $cr, $cru] = $this->ctx();
        TenantContext::bypass(true);
        $otherUser = User::create(['name' => 'O', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-2', 'type' => 'influencer', 'display_name' => 'آخر', 'status' => 'active', 'user_id' => $otherUser->id]);
        TenantContext::reset();
        $ct = $this->wf()->send($this->creatorContract($t, $cr, $ag->id), $ag->id);
        $this->actingAs($otherUser)->post("/creator/contracts/{$ct->id}/sign", ['signer_name' => 'x', 'agree' => '1'])->assertNotFound();
    }

    public function test_contracts_tenant_isolated(): void
    {
        [$t1, , $ag1, , , $cr1, $cru1] = $this->ctx();
        [$t2, , $ag2, , , $cr2, $cru2] = $this->ctx();
        $ct2 = $this->wf()->send($this->creatorContract($t2, $cr2, $ag2->id), $ag2->id);
        $this->actingAs($cru1)->get("/creator/contracts/{$ct2->id}")->assertNotFound();
    }
}
