<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion};
use App\Domain\Creators\Models\{Creator, CreatorApplication};
use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** طلبات انضمام المبدعين — مراجعة الوكالة React/Inertia: طابور + تفاصيل + إسناد/رفض/قبول + بوابة الدور. */
class InertiaCreatorApplicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Tenant} */
    private function agent(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'creators.max', 'value' => 50]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        return [$u, $t];
    }

    private function app(Tenant $t): CreatorApplication
    {
        $svc = app(CreatorApplicationService::class);
        $a = $svc->startDraft($t, ['account_type' => 'influencer', 'full_name' => 'ريناد الزهراني', 'email' => Str::random(5) . '@a.com']);
        TenantContext::set($t->id);
        $a->update(['email_verified_at' => now()]);
        TenantContext::reset();
        return $svc->transition($a, 'under_review');
    }

    public function test_index_queue(): void
    {
        [$u, $t] = $this->agent();
        $this->app($t);
        $this->actingAs($u)->get('/beta/creator-applications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('CreatorApplications/Index')->has('applications.data', 1)->where('summary.pending', 1));
    }

    public function test_show_detail(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->get("/beta/creator-applications/{$a->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('CreatorApplications/Show')->where('application.id', $a->id)->where('canApprove', true));
    }

    public function test_reject_requires_reason_and_transitions(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->post("/beta/creator-applications/{$a->id}/reject", [])->assertSessionHasErrors('reason');
        $this->actingAs($u)->post("/beta/creator-applications/{$a->id}/reject", ['reason' => 'بيانات غير مكتملة'])->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame('rejected', $a->fresh()->status);
        TenantContext::reset();
    }

    public function test_approve_creates_creator(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->post("/beta/creator-applications/{$a->id}/approve")->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertGreaterThanOrEqual(1, Creator::count());
        TenantContext::reset();
    }

    public function test_viewer_cannot_review(): void
    {
        [$u, $t] = $this->agent('viewer');
        $a = $this->app($t);
        $this->actingAs($u)->post("/beta/creator-applications/{$a->id}/reject", ['reason' => 'x'])->assertForbidden();
    }

    public function test_tenant_isolation(): void
    {
        [$u1] = $this->agent();
        [, $t2] = $this->agent();
        $aB = $this->app($t2);
        $this->actingAs($u1)->get("/beta/creator-applications/{$aB->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade — الإجراءات الخمسة المنقولة ===== */

    public function test_app_show_exposes_review_surfaces(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->get("/app/creator-applications/{$a->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreatorApplications/Show')->where('base', '/app')
                ->has('documents')->has('messages')->has('notes')->has('verification'));
    }

    public function test_app_internal_note_is_recorded(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->post("/app/creator-applications/{$a->id}/note", ['notes' => 'تحقّقنا من الهوية'])
            ->assertRedirect();

        TenantContext::bypass(true);
        $this->assertDatabaseHas('creator_application_reviews', [
            'application_id' => $a->id, 'decision' => 'note', 'notes' => 'تحقّقنا من الهوية',
        ]);
        TenantContext::reset();
    }

    public function test_app_message_reaches_applicant_thread(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->post("/app/creator-applications/{$a->id}/message", ['body' => 'نحتاج صورة الهوية'])
            ->assertRedirect();

        TenantContext::bypass(true);
        $this->assertDatabaseHas('creator_application_messages', [
            'application_id' => $a->id, 'sender_type' => 'agency', 'body' => 'نحتاج صورة الهوية',
        ]);
        TenantContext::reset();
    }

    public function test_app_mowthooq_and_financial_reviews_update_state(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->post("/app/creator-applications/{$a->id}/mowthooq-review", ['decision' => 'verified'])->assertRedirect();
        $this->actingAs($u)->post("/app/creator-applications/{$a->id}/financial-review", ['decision' => 'rejected'])->assertRedirect();

        TenantContext::bypass(true);
        $fresh = CreatorApplication::find($a->id);
        TenantContext::reset();
        $this->assertSame('verified', $fresh->mowthooq_status);
        $this->assertSame('rejected', $fresh->financial_verification_status);
    }

    public function test_app_mowthooq_rejects_invalid_decision(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->post("/app/creator-applications/{$a->id}/mowthooq-review", ['decision' => 'maybe'])
            ->assertSessionHasErrors('decision');
    }

    public function test_app_suspend_changes_status(): void
    {
        [$u, $t] = $this->agent();
        $a = $this->app($t);
        $this->actingAs($u)->post("/app/creator-applications/{$a->id}/suspend", ['reason' => 'اشتباه'])->assertRedirect();

        TenantContext::bypass(true);
        $this->assertSame('suspended', CreatorApplication::find($a->id)->status);
        TenantContext::reset();
    }

    public function test_app_review_actions_denied_for_viewer(): void
    {
        [, $t] = $this->agent();
        [$viewer] = $this->agentIn($t, 'viewer');
        $a = $this->app($t);
        $this->actingAs($viewer)->post("/app/creator-applications/{$a->id}/note", ['notes' => 'ممنوع'])->assertForbidden();
        $this->actingAs($viewer)->post("/app/creator-applications/{$a->id}/suspend", ['reason' => 'ممنوع'])->assertForbidden();
    }

    /** عضو بدور محدَّد داخل نفس المستأجر (لاختبار البوابات). */
    private function agentIn(Tenant $t, string $role): array
    {
        TenantContext::bypass(true);
        $org = Organization::where('tenant_id', $t->id)->firstOrFail();
        $u = User::create(['name' => 'V', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();

        return [$u];
    }
}
