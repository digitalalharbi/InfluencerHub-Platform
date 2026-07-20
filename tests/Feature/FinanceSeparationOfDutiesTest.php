<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\{Invoice, Payout};
use App\Domain\Finance\Services\{InvoiceService, PayoutWorkflowService};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * فصل الواجبات المالية.
 *
 * كانت المستحقات كلها تحت `MANAGE_DOCS`: شخص واحد يطلب ويعتمد ويسجّل الصرف في
 * ثلاث نقرات بلا شاهد. هذه الاختبارات تحرس التقسيم — ولأن الفعل الأخير إخراج
 * مال لا يُسترجَع، فالحراسة هنا ليست تشدّدًا.
 */
class FinanceSeparationOfDutiesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'و', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $this->org = Organization::create(['tenant_id' => $this->tenant->id, 'name' => 'و',
            'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /**
     * المساعِد يستعيد السياق ولا يمحوه: `reset()` كان يمسح المستأجر المضبوط
     * قبل الاستدعاء فيعود الدور فارغًا وتفشل الصلاحية لسبب لا علاقة له بها.
     */
    private function user(string $role): User
    {
        $prevTenant = TenantContext::tenantId();
        $prevOrg = TenantContext::organizationId();

        TenantContext::bypass(true);
        $u = User::create(['name' => $role, 'email' => Str::random(8) . '@ex.com',
            'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $this->tenant->id, 'organization_id' => $this->org->id,
            'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::bypass(false);
        TenantContext::set($prevTenant, $prevOrg);

        return $u;
    }

    private function payout(User $creator, string $status = 'pending'): Payout
    {
        TenantContext::bypass(true);
        $c = Creator::create(['tenant_id' => $this->tenant->id, 'creator_number' => 'CR-' . Str::random(4),
            'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active']);
        $p = Payout::create(['tenant_id' => $this->tenant->id, 'payout_number' => 'PO-' . Str::random(4),
            'creator_id' => $c->id, 'description' => 'مستحق', 'amount_minor' => 500000,
            'currency' => 'SAR', 'status' => $status, 'created_by' => $creator->id]);
        TenantContext::reset();

        return $p;
    }

    private function invoice(?User $actor = null): Invoice
    {
        $actor ??= $this->user('finance');
        TenantContext::bypass(true);
        $client = Client::create(['tenant_id' => $this->tenant->id, 'client_number' => 'CL-' . Str::random(4),
            'display_name' => 'عميل', 'status' => 'active']);
        TenantContext::reset();
        TenantContext::set($this->tenant->id, $this->org->id);
        $inv = app(InvoiceService::class)->create($this->tenant->id, ['client_id' => $client->id],
            [['description' => 'بند', 'quantity' => 1, 'unit_price_minor' => 100000]], $actor->id);
        TenantContext::reset();

        return $inv;
    }

    // ===== المستحقات: مَن يطلب لا يعتمد =====

    /** مدير الحملة يعرف ما أُنجز فيطلب — وهذا حدّ دوره. */
    public function test_campaign_manager_may_request_a_payout(): void
    {
        $cm = $this->user('campaign_manager');
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertTrue($cm->can('create', Payout::class));
    }

    public function test_campaign_manager_cannot_approve_a_payout(): void
    {
        $cm = $this->user('campaign_manager');
        $p = $this->payout($cm);
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertFalse($cm->can('approve', $p), 'اعتمد مدير الحملة مستحقًّا');
        $this->assertFalse($cm->can('act', [$p, 'approve']));
    }

    /** الصرف هو الفعل الذي لا رجعة فيه — لا يُقرّه إلا صاحب صلاحية صريحة. */
    public function test_campaign_manager_cannot_mark_a_payout_paid(): void
    {
        $cm = $this->user('campaign_manager');
        $p = $this->payout($cm, 'waiting_for_provider');
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertFalse($cm->can('markPaid', $p), 'سجّل مدير الحملة صرفًا');
    }

    /** حتى مدير العمليات لا يُقرّ الصرف: مقصور على المالية والإدارة العليا. */
    public function test_operations_manager_can_approve_but_not_mark_paid(): void
    {
        $ops = $this->user('operations_manager');
        $requester = $this->user('campaign_manager');
        $p = $this->payout($requester);
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertTrue($ops->can('approve', $p));
        $this->assertFalse($ops->can('markPaid', $p), 'سجّل مدير العمليات صرفًا');
    }

    public function test_finance_can_approve_and_mark_paid(): void
    {
        $fin = $this->user('finance');
        $requester = $this->user('campaign_manager');
        $p = $this->payout($requester);
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertTrue($fin->can('approve', $p));
        $this->assertTrue($fin->can('markPaid', $p));
    }

    /** جوهر فصل الواجبات: لا يعتمد المرءُ ما طلبه. */
    public function test_finance_cannot_approve_a_payout_they_created_themselves(): void
    {
        $fin = $this->user('finance');
        $p = $this->payout($fin);
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertFalse($fin->can('approve', $p), 'اعتمد الطالبُ طلبَه');
    }

    /** الاستثناء صريح لا ضمني: الإدارة العليا حين لا يوجد غيرها. */
    public function test_agency_admin_may_approve_their_own_request(): void
    {
        $admin = $this->user('agency_admin');
        $p = $this->payout($admin);
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertTrue($admin->can('approve', $p));
    }

    public function test_viewer_can_read_but_never_act(): void
    {
        $viewer = $this->user('viewer');
        $requester = $this->user('campaign_manager');
        $p = $this->payout($requester);
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertTrue($viewer->can('view', $p));
        $this->assertFalse($viewer->can('create', Payout::class));
        $this->assertFalse($viewer->can('approve', $p));
        $this->assertFalse($viewer->can('markPaid', $p));
    }

    /** الفعل المجهول يُرفض: لا يُسمح بما لم يُصرَّح به. */
    public function test_an_unknown_action_is_refused_even_for_finance(): void
    {
        $fin = $this->user('finance');
        $p = $this->payout($this->user('campaign_manager'));
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertFalse($fin->can('act', [$p, 'transfer-to-my-account']));
    }

    // ===== عبر HTTP =====

    public function test_http_refuses_approval_by_the_requester(): void
    {
        $fin = $this->user('finance');
        $p = $this->payout($fin);

        $this->actingAs($fin)->post("/app/payouts/{$p->id}/approve")->assertForbidden();
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_http_refuses_mark_paid_without_finance_permission(): void
    {
        $cm = $this->user('campaign_manager');
        $p = $this->payout($cm, 'waiting_for_provider');

        $this->actingAs($cm)->post("/app/payouts/{$p->id}/mark-paid", ['payment_reference' => 'TRX-1'])
            ->assertForbidden();
        $this->assertNull($p->fresh()->paid_at, 'سُجّل صرف بلا صلاحية');
    }

    /** الواجهة لا تعرض ما لا يسمح به الخادم. */
    public function test_actions_shown_are_filtered_per_ability(): void
    {
        $cm = $this->user('campaign_manager');
        $p = $this->payout($this->user('finance'));

        $this->actingAs($cm)->get("/app/payouts/{$p->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('actions', []));
    }

    // ===== التدقيق =====

    /** «حُدّث» لا يكفي حين يكون الفعل صرف مال: لكل فعل سجلّه. */
    public function test_each_financial_action_writes_its_own_audit_entry(): void
    {
        $requester = $this->user('campaign_manager');
        $fin = $this->user('finance');
        $p = $this->payout($requester);

        $this->actingAs($fin)->post("/app/payouts/{$p->id}/approve")->assertRedirect();

        TenantContext::bypass(true);
        $actions = AuditLog::where('auditable_id', $p->id)
            ->where('action', 'like', 'payout.%')->pluck('action')->all();
        TenantContext::reset();

        // سير العمل يكتب سجلًّا واحدًا لكل انتقال باسم الحالة الجديدة
        $this->assertContains('payout.approved', $actions);
        // ولا يُكتب سجلّان لفعل واحد
        $this->assertSame(1, collect($actions)->filter(fn ($a) => str_contains($a, 'approv'))->count(),
            'كُتب أكثر من سجلّ تدقيق للفعل نفسه');
    }

    // ===== الفواتير =====

    public function test_recording_a_payment_needs_its_own_permission(): void
    {
        $cm = $this->user('campaign_manager');
        $inv = $this->invoice();
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertFalse($cm->can('recordPayment', $inv), 'قيّد مدير الحملة تحصيلًا');
        $this->assertTrue($this->user('finance')->can('recordPayment', $inv));
    }

    public function test_campaign_manager_can_read_finance_but_not_issue(): void
    {
        $cm = $this->user('campaign_manager');
        $inv = $this->invoice();
        TenantContext::set($this->tenant->id, $this->org->id);

        $this->assertTrue($cm->can('view', $inv));
        $this->assertFalse($cm->can('manage', $inv));
    }
}
