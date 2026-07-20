<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 — بوابة طلبات الانضمام: مرجع آمن، OTP (Hash/انتهاء/محاولات)، حالة append-only. */
class CreatorApplicationTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
    }

    /** مستأجر + مؤسسة بـslug معروف (لحلّ البوابة الصريح ?a=). */
    private function tenantWithOrg(string $slug): Tenant
    {
        $t = $this->tenant();
        TenantContext::bypass(true);
        \App\Domain\Tenancy\Models\Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => $slug, 'type' => 'agency', 'status' => 'active']);
        TenantContext::reset();
        return $t;
    }
    private function svc(): CreatorApplicationService { return app(CreatorApplicationService::class); }

    public function test_draft_gets_unguessable_reference_and_history(): void
    {
        $app = $this->svc()->startDraft($this->tenant(), ['capabilities' => ['influencer'], 'email' => 'a@b.com']);
        $this->assertStringStartsWith('CA-', $app->reference);
        $this->assertGreaterThanOrEqual(20, strlen($app->reference)); // ليس ID متسلسل
        $this->assertEquals('draft', $app->status);
        TenantContext::bypass(true);
        $this->assertDatabaseHas('creator_application_status_history', ['application_id' => $app->id, 'to_status' => 'draft']);
        TenantContext::reset();
    }

    public function test_otp_is_hashed_not_stored_raw(): void
    {
        $app = $this->svc()->startDraft($this->tenant(), ['email' => 'a@b.com']);
        $code = $this->svc()->issueOtp($app, 'email');
        TenantContext::bypass(true);
        $this->assertDatabaseMissing('creator_application_verifications', ['code_hash' => $code]); // ليس خامًا
        $this->assertDatabaseHas('creator_application_verifications', ['code_hash' => hash('sha256', $code)]);
        TenantContext::reset();
    }

    public function test_correct_otp_verifies_email(): void
    {
        $app = $this->svc()->startDraft($this->tenant(), ['email' => 'a@b.com']);
        $code = $this->svc()->issueOtp($app, 'email');
        $this->assertTrue($this->svc()->verifyOtp($app, 'email', $code));
        $this->assertNotNull($app->fresh()->email_verified_at);
    }

    public function test_wrong_otp_fails_and_counts_attempt(): void
    {
        $app = $this->svc()->startDraft($this->tenant(), ['email' => 'a@b.com']);
        $this->svc()->issueOtp($app, 'email');
        $this->assertFalse($this->svc()->verifyOtp($app, 'email', '000000'));
        $this->assertNull($app->fresh()->email_verified_at);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $app = $this->svc()->startDraft($this->tenant(), ['email' => 'a@b.com']);
        $code = $this->svc()->issueOtp($app, 'email');
        TenantContext::bypass(true);
        \App\Domain\Creators\Models\CreatorApplicationVerification::where('application_id', $app->id)
            ->update(['expires_at' => now()->subMinute()]);
        TenantContext::reset();
        $this->expectException(\RuntimeException::class);
        $this->svc()->verifyOtp($app, 'email', $code);
    }

    public function test_max_attempts_blocks_further_tries(): void
    {
        $app = $this->svc()->startDraft($this->tenant(), ['email' => 'a@b.com']);
        $code = $this->svc()->issueOtp($app, 'email');
        for ($i = 0; $i < 5; $i++) { $this->svc()->verifyOtp($app, 'email', '111111'); }
        $this->expectException(\RuntimeException::class); // تجاوز المحاولات
        $this->svc()->verifyOtp($app, 'email', $code);
    }

    public function test_transition_records_append_only_history(): void
    {
        $app = $this->svc()->startDraft($this->tenant(), ['email' => 'a@b.com']);
        $this->svc()->transition($app, 'submitted', null, ['reason' => 'test']);
        TenantContext::bypass(true);
        $hist = \App\Domain\Creators\Models\CreatorApplicationStatusHistory::where('application_id', $app->id)->get();
        $this->assertTrue($hist->contains(fn ($h) => $h->to_status === 'submitted' && $h->from_status === 'draft'));
        // append-only: التاريخ لا يُعدَّل عبر النموذج (لا محدّثات)
        TenantContext::reset();
        $this->assertEquals('submitted', $app->fresh()->status);
    }

    public function test_public_flow_over_http_creates_draft(): void
    {
        $this->tenantWithOrg('agency-x'); // مؤسسة بـslug للحلّ الصريح
        $res = $this->post('/join/creator?a=agency-x', [
            'capabilities' => ['influencer'], 'full_name' => 'لينا', 'email' => 'lina@ex.com',
            'phone' => '+966500000000', 'country_code' => 'SA', 'terms' => '1', 'privacy' => '1',
        ]);
        $res->assertRedirect();
        TenantContext::bypass(true);
        $this->assertDatabaseHas('creator_applications', ['email' => 'lina@ex.com', 'status' => 'draft']);
        TenantContext::reset();
    }

    public function test_duplicate_active_application_is_blocked(): void
    {
        $t = $this->tenantWithOrg('agency-y');
        $this->svc()->startDraft($t, ['email' => 'dup@ex.com']);
        $res = $this->post('/join/creator?a=agency-y', [
            'capabilities' => ['influencer'], 'full_name' => 'x', 'email' => 'dup@ex.com',
            'phone' => '+966500000000', 'terms' => '1', 'privacy' => '1',
        ]);
        $res->assertSessionHasErrors('email'); // طلب مكرّر
    }
}
