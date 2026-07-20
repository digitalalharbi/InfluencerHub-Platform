<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Services\PayoutWorkflowService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 11 — المستحقات: حالات صادقة (بلا تنفيذ دفع)، IBAN last4، إشعارات، عزل، وصلاحيات مالية. */
class PayoutTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(string $agencyRole = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $ag = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $ag->id, 'role' => $agencyRole, 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new CreateSubscription)->handle($org, $v);
        $creatorUser = User::create(['name' => 'Cr', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-1', 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active', 'user_id' => $creatorUser->id, 'iban_last4' => '6789']);
        TenantContext::reset();
        return [$t, $org, $ag, $cr, $creatorUser];
    }
    private function wf(): PayoutWorkflowService { return app(PayoutWorkflowService::class); }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }
    private function make(Tenant $t, Creator $cr, int $by, int $amount = 1350000): Payout
    {
        return $this->wf()->create($t->id, ['creator_id' => $cr->id, 'amount_minor' => $amount, 'description' => 'أجر تعاون'], $by);
    }

    public function test_create_snapshots_iban_and_number(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $p = $this->make($t, $cr, $ag->id);
        $this->assertStringStartsWith('PY-', $p->payout_number);
        $this->assertEquals('pending', $p->status);
        $this->assertEquals('6789', $p->iban_last4); // لقطة من الملف
    }

    public function test_rejects_non_positive_amount(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $this->expectException(\RuntimeException::class);
        $this->wf()->create($t->id, ['creator_id' => $cr->id, 'amount_minor' => 0], $ag->id);
    }

    public function test_full_flow_pending_to_paid_with_reference(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        $p = $this->make($t, $cr, $ag->id);
        $p = $this->wf()->approve($p, $ag->id);
        $p = $this->wf()->schedule($p, $ag->id);
        $p = $this->wf()->sendToProvider($p, $ag->id);
        $this->assertEquals('waiting_for_provider', $p->status);
        $p = $this->wf()->markPaid($p, $ag->id, 'TRX-001');
        $this->assertEquals('paid', $p->status);
        $this->assertEquals('TRX-001', $this->fresh($p)->payment_reference);
        $this->assertNotNull($this->fresh($p)->paid_at);
    }

    public function test_cannot_mark_paid_before_provider_stage(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $p = $this->wf()->approve($this->make($t, $cr, $ag->id), $ag->id); // approved
        $this->expectException(\RuntimeException::class); // approved → paid غير مسموح
        $this->wf()->markPaid($p, $ag->id, 'X');
    }

    public function test_failed_can_be_rescheduled(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $p = $this->wf()->sendToProvider($this->wf()->schedule($this->wf()->approve($this->make($t, $cr, $ag->id), $ag->id), $ag->id), $ag->id);
        $p = $this->wf()->markFailed($p, $ag->id, 'IBAN غير صحيح');
        $this->assertEquals('failed', $p->status);
        $p = $this->wf()->schedule($p, $ag->id);
        $this->assertEquals('scheduled', $p->status);
    }

    public function test_paid_notifies_creator(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        $p = $this->wf()->markPaid($this->wf()->sendToProvider($this->wf()->schedule($this->wf()->approve($this->make($t, $cr, $ag->id), $ag->id), $ag->id), $ag->id), $ag->id, 'TRX-9');
        TenantContext::bypass(true);
        $this->assertTrue(\App\Domain\Communications\Models\Notification::where('user_id', $cru->id)->where('type', 'payout.paid')->exists());
        TenantContext::reset();
    }

    public function test_agency_creates_over_http_and_marks_paid(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $this->actingAs($ag)->post('/app/payouts', ['creator_id' => $cr->id, 'amount_minor' => 500000, 'description' => 'أجر'])->assertRedirect();
        TenantContext::bypass(true);
        $p = Payout::where('creator_id', $cr->id)->first();
        TenantContext::reset();
        $this->assertNotNull($p);
        $this->wf()->sendToProvider($this->wf()->schedule($this->wf()->approve($p, $ag->id), $ag->id), $ag->id);
        $this->actingAs($ag)->post("/app/payouts/{$p->id}/mark-paid", ['payment_reference' => 'TRX-HTTP'])->assertRedirect();
        $this->assertEquals('paid', $this->fresh($p)->status);
    }

    public function test_creator_sees_own_payouts(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        $this->make($t, $cr, $ag->id);
        $this->actingAs($cru)->get('/creator/payouts')->assertOk()->assertSee('PY-');
    }

    public function test_viewer_role_cannot_manage_payouts(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx('viewer'); // بلا MANAGE_DOCS
        $this->actingAs($ag)->post('/app/payouts', ['creator_id' => $cr->id, 'amount_minor' => 1000])->assertForbidden();
    }

    public function test_payouts_tenant_isolated(): void
    {
        [$t1, , $ag1, $cr1] = $this->ctx();
        [$t2, , $ag2, $cr2] = $this->ctx();
        $p2 = $this->make($t2, $cr2, $ag2->id);
        $this->actingAs($ag1)->get("/app/payouts/{$p2->id}")->assertNotFound();
    }

    public function test_transition_uses_db_state_not_stale_model(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $p = $this->make($t, $cr, $ag->id); // pending
        // نسخة قديمة في الذاكرة ما زالت "pending"
        TenantContext::set($t->id);
        $stale = Payout::whereKey($p->id)->first();
        // تحوّل حقيقي عبر النسخة الأصلية ⇒ القاعدة الآن "approved"
        $this->wf()->approve($p, $ag->id);
        TenantContext::reset();

        // اعتماد النسخة القديمة يجب أن يُرفض لأن حالة القاعدة = approved (لا pending)
        $this->expectException(\RuntimeException::class);
        $this->wf()->approve($stale, $ag->id);
    }

    // ===== المستحقّ مشتقًّا من التعاون =====

    /**
     * الجدول يحمل روابط التعاون والحملة والعقد ولا مسار يملؤها، فيخرج المستحقّ
     * يتيمًا عن العمل الذي يستحقّ عنه ويُعاد إدخال ما تقرّر فعلًا.
     */
    public function test_payout_from_collaboration_inherits_its_links_and_fee(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        [$col, $contract] = $this->acceptedCollaboration($t, $org, $ag, $cr);

        $p = $this->wf()->createFromCollaboration($col, $ag->id);

        $this->assertSame($col->id, (int) $p->collaboration_id, 'المستحقّ لا يعرف تعاونه');
        $this->assertSame((int) $col->campaign_id, (int) $p->campaign_id, 'المستحقّ لا يعرف حملته');
        $this->assertSame($contract->id, (int) $p->contract_id, 'المستحقّ لا يعرف عقده');
        $this->assertSame((int) $col->fee_minor, (int) $p->amount_minor, 'الأجر أُعيد إدخاله بدل أن يُورَث');
        $this->assertSame('pending', $p->status);
    }

    /** مستحقّان على تعاون واحد = دفع أجر العمل مرّتين. */
    public function test_a_second_payout_for_the_same_collaboration_is_refused(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        [$col] = $this->acceptedCollaboration($t, $org, $ag, $cr);
        $first = $this->wf()->createFromCollaboration($col, $ag->id);

        try {
            $this->wf()->createFromCollaboration($col, $ag->id);
            $this->fail('أُنشئ مستحقّ ثانٍ على التعاون نفسه');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($first->payout_number, $e->getMessage());
        }

        TenantContext::bypass(true);
        $this->assertSame(1, Payout::where('collaboration_id', $col->id)->count());
        TenantContext::reset();
    }

    /** تعاون بلا أجر لا يُنشئ مستحقًّا بصفر — يقول السبب. */
    public function test_a_zero_fee_collaboration_is_refused_with_a_reason(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        [$col] = $this->acceptedCollaboration($t, $org, $ag, $cr, 0);

        $this->expectException(\RuntimeException::class);
        $this->wf()->createFromCollaboration($col, $ag->id);
    }

    /** @return array{0:mixed,1:mixed} */
    private function acceptedCollaboration(Tenant $t, $org, User $ag, Creator $cr, int $fee = 700000): array
    {
        TenantContext::bypass(true);
        $client = \App\Domain\CRM\Models\Client::create(['tenant_id' => $t->id,
            'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'status' => 'active']);
        TenantContext::reset();

        $cw = app(\App\Domain\Campaigns\Services\CampaignWorkflowService::class);
        TenantContext::set($t->id, $org->id);
        $cm = $cw->create($t->id, ['client_id' => $client->id, 'name' => 'حملة'], $ag->id);
        $d = $cw->addDeliverable($cm, ['type' => 'post', 'platform' => 'instagram', 'quantity' => 1, 'fee_minor' => $fee], $ag->id);
        $colSvc = app(\App\Domain\Collaborations\Services\CollaborationWorkflowService::class);
        $col = $colSvc->offerFromDeliverable($d, $cr->id, $ag->id);
        $col = $colSvc->accept($col, $cr->user_id);
        $contract = app(\App\Domain\Contracts\Services\ContractWorkflowService::class)
            ->createFromCollaboration($col, $ag->id);
        TenantContext::reset();

        return [$col, $contract];
    }

    // ===== فصل الصلاحيات المالية: الحارس على الخادم لا في الواجهة =====

    /**
     * مدير الحملات يُنشئ الطلب ولا يعتمده ولا يصرفه — فصل الواجبات.
     * الاختبار عبر HTTP مباشرةً: إخفاء الزرّ ليس حراسة.
     */
    public function test_campaign_manager_cannot_approve_or_mark_paid_over_http(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        $cm = $this->memberWithRole($t, $org, 'campaign_manager');
        $p = $this->make($t, $cr, $ag->id);

        $this->actingAs($cm)->post("/app/payouts/{$p->id}/approve")->assertForbidden();
        $this->assertSame('pending', $this->fresh($p)->status, 'اعتُمد المستحقّ بلا صلاحية');

        // وحتى لو بلغ مرحلة الصرف، التسجيل ممنوع عليه
        $this->wf()->approve($p, $ag->id);
        $this->wf()->schedule($p, $ag->id);
        $this->wf()->sendToProvider($p, $ag->id);
        $this->actingAs($cm)->post("/app/payouts/{$p->id}/mark-paid", ['reference' => 'TRX-1'])->assertForbidden();
        $this->assertNotSame('paid', $this->fresh($p)->status, 'سُجّل الصرف بلا صلاحية');
    }

    /** مدير النظام مشرف للقراءة — لا يكتب في مساحات المستأجرين. */
    public function test_system_admin_cannot_approve_or_mark_paid_over_http(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        TenantContext::bypass(true);
        $admin = User::create(['name' => 'SA', 'email' => Str::random(6) . '@ex.com',
            'password' => bcrypt('x'), 'is_active' => true]);
        $admin->forceFill(['is_system_admin' => true])->save();
        TenantContext::reset();
        $p = $this->make($t, $cr, $ag->id);

        $this->actingAs($admin)->post("/app/payouts/{$p->id}/approve")->assertForbidden();
        $this->actingAs($admin)->post("/app/payouts/{$p->id}/mark-paid", ['reference' => 'TRX-1'])->assertForbidden();
        $this->assertSame('pending', $this->fresh($p)->status, 'كتب مدير النظام في مساحة مستأجر');
    }

    /** المالية تعتمد وتصرف — الوجه الموجب للفصل. */
    public function test_finance_may_approve_and_mark_paid(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        $fin = $this->memberWithRole($t, $org, 'finance');
        $p = $this->make($t, $cr, $ag->id);

        $this->actingAs($fin)->post("/app/payouts/{$p->id}/approve")->assertRedirect();
        $this->assertSame('approved', $this->fresh($p)->status);
    }

    /** الاعتماد يصل المبدع — كان يمرّ صامتًا عنه رغم أنه خبر أجره. */
    public function test_approval_reaches_the_creator(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        $p = $this->make($t, $cr, $ag->id);
        $this->wf()->approve($p, $ag->id);

        TenantContext::bypass(true);
        $n = \App\Domain\Communications\Models\Notification::where('tenant_id', $t->id)
            ->where('user_id', $cru->id)->where('type', 'payout.approved')->first();
        TenantContext::reset();

        $this->assertNotNull($n, 'اعتُمد المستحقّ ولم يعلم به صاحبه');
    }

    private function memberWithRole(Tenant $t, $org, string $role): User
    {
        TenantContext::bypass(true);
        $u = User::create(['name' => $role, 'email' => Str::random(6) . '@ex.com',
            'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id,
            'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();

        return $u;
    }

    /**
     * المستحقّ المعتمَد كان يقف بلا مخرج في الواجهة.
     *
     * الصفحة ربطت شريط الإجراءات بـ`canManage` وهي صلاحية *التعديل* المقصورة
     * على «قيد الانتظار»، فاختفت الجدولة والصرف عن المالية فور الاعتماد رغم
     * أن المتحكّم يفحص كل فعل بقاعدته ويرسله في `actions`.
     */
    public function test_an_approved_payout_still_offers_its_next_step_to_finance(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        $fin = $this->memberWithRole($t, $org, 'finance');
        $p = $this->make($t, $cr, $ag->id);
        $this->wf()->approve($p, $ag->id);

        $this->actingAs($fin)->get("/app/payouts/{$p->id}")->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Payouts/Show')
                ->where('payout.status', 'approved')
                ->where('actions.0.0', 'schedule'));
    }

    /** ومدير الحملات لا يرى الجدولة على المستحقّ نفسه. */
    public function test_campaign_manager_sees_no_actions_on_an_approved_payout(): void
    {
        [$t, $org, $ag, $cr, $cru] = $this->ctx();
        $cm = $this->memberWithRole($t, $org, 'campaign_manager');
        $p = $this->make($t, $cr, $ag->id);
        $this->wf()->approve($p, $ag->id);

        $this->actingAs($cm)->get("/app/payouts/{$p->id}")->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('actions', []));
    }
}
