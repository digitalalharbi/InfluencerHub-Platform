<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Onboarding\WorkspaceSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * سلسلة تشغيل الوكالة: مساحة فارغة ← عميل ← علامة معتمدة ← طلب ← حملة.
 *
 * كل اختبار هنا يحرس انقطاعًا وُجد فعلًا أثناء تشغيل الرحلة في المتصفح، لا
 * حالة متخيَّلة. الانقطاعات كانت صامتة: صفحات تَعِد بإجراء لا تتيحه، وحالات
 * خارج مسار الاعتماد، وشروط جاهزية لا سبيل إلى رفعها.
 */
class AgencyJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:Organization,2:User} */
    private function agency(): array
    {
        $t = Tenant::create(['name' => 'و', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مالك', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id,
            'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $org, $u];
    }

    private function client(Tenant $t, string $status = 'lead'): Client
    {
        TenantContext::bypass(true);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . Str::random(4),
            'display_name' => 'عميل', 'status' => $status]);
        TenantContext::reset();

        return $c;
    }

    // ===== تهيئة المساحة =====

    /** مساحة فارغة تُعلن أنها قيد التهيئة ولها خطوة تالية محدّدة. */
    public function test_empty_workspace_reports_setup_with_a_next_step(): void
    {
        [$t, $org] = $this->agency();

        $setup = WorkspaceSetup::for($t->id, $org->id);

        $this->assertTrue($setup['isSettingUp'], 'مساحة فارغة اعتُبرت جاهزة');
        $this->assertSame('client', $setup['next']['key'], 'الخطوة التالية ليست إضافة عميل');
    }

    /** الخطوة الإلزامية تسبق الاختيارية: لا نطلب دعوة فريق قبل وجود عميل. */
    public function test_optional_steps_never_jump_ahead_of_required_ones(): void
    {
        [$t, $org] = $this->agency();

        $setup = WorkspaceSetup::for($t->id, $org->id);
        $this->assertNotSame('team', $setup['next']['key']);
    }

    public function test_setup_banner_disappears_once_required_steps_are_done(): void
    {
        [$t, $org, $u] = $this->agency();
        $c = $this->client($t, 'active');
        TenantContext::bypass(true);
        Brand::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'name' => 'ع', 'slug' => Str::random(6), 'status' => 'approved']);
        \App\Domain\Creators\Models\Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-1',
            'type' => 'influencer', 'display_name' => 'م', 'status' => 'active']);
        Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-1', 'client_id' => $c->id,
            'name' => 'ح', 'status' => 'draft', 'budget_minor' => 1000, 'currency' => 'SAR']);
        TenantContext::reset();

        $setup = WorkspaceSetup::for($t->id, $org->id);
        $this->assertFalse($setup['isSettingUp'], 'بقيت قائمة التهيئة بعد اكتمال الإلزامي');
    }

    // ===== الطلب نيابةً عن العميل =====

    /** الطابور كان بلا مدخل: القدرة غير معرَّفة والنظام يرفض افتراضيًّا. */
    public function test_agency_can_record_a_request_on_behalf_of_a_client(): void
    {
        [$t, , $u] = $this->agency();
        $c = $this->client($t, 'active');

        $this->actingAs($u)->post('/app/service-requests', [
            'client_id' => $c->id, 'type' => 'campaign', 'title' => 'حملة الشتاء',
            'priority' => 'normal', 'budget_riyals' => '120000',
            'preferred_start_date' => '2026-09-01', 'preferred_end_date' => '2026-09-30',
            'platforms' => ['instagram', 'tiktok'], 'scope_notes' => '6 منشورات',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $sr = ServiceRequest::where('title', 'حملة الشتاء')->first();
        TenantContext::reset();

        $this->assertNotNull($sr, 'لم يُسجَّل الطلب');
        // الريالات تُخزَّن وحداتٍ صغرى، والموجز كاملًا لينتقل إلى الحملة
        $this->assertSame(12000000, (int) $sr->budget_minor);
        $this->assertEqualsCanonicalizing(['instagram', 'tiktok'], $sr->platforms);
        // مُقدِّم الطلب هو العميل وإن سجّلته الوكالة نيابةً عنه
        $this->assertSame('client', $sr->requester_type);
        $this->assertSame($c->id, $sr->requester_client_id);
    }

    public function test_request_rejects_a_brand_that_belongs_to_another_client(): void
    {
        [$t, , $u] = $this->agency();
        $mine = $this->client($t, 'active');
        $other = $this->client($t, 'active');
        TenantContext::bypass(true);
        $foreign = Brand::create(['tenant_id' => $t->id, 'client_id' => $other->id,
            'name' => 'غريبة', 'slug' => Str::random(6), 'status' => 'draft']);
        TenantContext::reset();

        $this->actingAs($u)->post('/app/service-requests', [
            'client_id' => $mine->id, 'brand_id' => $foreign->id, 'type' => 'campaign',
            'title' => 'خلط', 'priority' => 'normal',
        ])->assertStatus(422);
    }

    public function test_request_creation_requires_write_permission(): void
    {
        [$t, $org] = $this->agency();
        $c = $this->client($t, 'active');
        TenantContext::bypass(true);
        $viewer = User::create(['name' => 'قارئ', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id,
            'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        TenantContext::reset();

        $this->actingAs($viewer)->post('/app/service-requests', [
            'client_id' => $c->id, 'type' => 'campaign', 'title' => 'ممنوع', 'priority' => 'normal',
        ])->assertForbidden();
    }

    // ===== العلامة تدخل مسار الاعتماد =====

    /**
     * كانت العلامة تُنشأ بحالة «active» وهي خارج مفردات المراجعة، فلا تظهر في
     * طابور الاعتماد ولا تصير «معتمدة» أبدًا — فتبقى الحملة محجوبة بلا مخرج.
     */
    public function test_brand_created_from_client_page_enters_the_approval_flow(): void
    {
        [$t, , $u] = $this->agency();
        $c = $this->client($t, 'active');

        $this->actingAs($u)->post("/app/clients/{$c->id}/brands", ['name' => 'علامة', 'sector' => 'تجزئة'])
            ->assertRedirect();

        TenantContext::bypass(true);
        $b = Brand::where('client_id', $c->id)->firstOrFail();
        TenantContext::reset();

        $this->assertSame('draft', $b->status, 'أُنشئت العلامة خارج مسار الاعتماد');
    }

    /** المسوّدة كانت بلا إجراء على جهة الوكالة فتبقى عالقة إن لم يكن للعميل بوابة. */
    public function test_agency_can_submit_and_approve_a_brand_end_to_end(): void
    {
        [$t, , $u] = $this->agency();
        $c = $this->client($t, 'active');
        $this->actingAs($u)->post("/app/clients/{$c->id}/brands", ['name' => 'علامة']);

        TenantContext::bypass(true);
        $b = Brand::where('client_id', $c->id)->firstOrFail();
        TenantContext::reset();

        $this->actingAs($u)->post("/app/brands/{$b->id}/submit")->assertRedirect();
        $this->assertSame('submitted', $b->fresh()->status);

        $this->actingAs($u)->post("/app/brands/{$b->id}/start")->assertRedirect();
        $this->actingAs($u)->post("/app/brands/{$b->id}/approve")->assertRedirect();
        $this->assertSame('approved', $b->fresh()->status);
    }

    // ===== حالة العميل =====

    /** لم يكن للعميل مسار تحديث أصلًا، فيبقى «مهتمًّا» وتُحجب الحملة بلا مخرج. */
    public function test_client_status_can_be_changed_so_the_campaign_gate_can_clear(): void
    {
        [$t, , $u] = $this->agency();
        $c = $this->client($t, 'lead');

        $this->actingAs($u)->post("/app/clients/{$c->id}/update", ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame('active', $c->fresh()->status);
    }

    public function test_client_status_must_be_a_known_value(): void
    {
        [$t, , $u] = $this->agency();
        $c = $this->client($t, 'lead');

        $this->actingAs($u)->from("/app/clients/{$c->id}")
            ->post("/app/clients/{$c->id}/update", ['status' => 'invented'])
            ->assertSessionHasErrors('status');

        $this->assertSame('lead', $c->fresh()->status);
    }

    /** عزل: لا يُحدَّث عميل مستأجر آخر عبر تخمين المُعرّف. */
    public function test_client_of_another_tenant_cannot_be_updated(): void
    {
        [, , $u] = $this->agency();
        [$otherTenant] = $this->agency();
        $foreign = $this->client($otherTenant, 'lead');

        $this->actingAs($u)->post("/app/clients/{$foreign->id}/update", ['status' => 'active'])
            ->assertNotFound();

        $this->assertSame('lead', $foreign->fresh()->status);
    }
}
