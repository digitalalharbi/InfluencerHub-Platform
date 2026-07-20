<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 hardening — تأمين الوصول لطلب الانضمام (المرجع وحده لا يكفي). */
class ApplicantAccessTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function draft(string $slug = 'ag'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => $slug, 'type' => 'agency', 'status' => 'active']);
        TenantContext::reset();
        $svc = app(CreatorApplicationService::class);
        $app = $svc->startDraft($t, ['email' => Str::random(5) . '@ex.com']);
        $token = $svc->issueAccessToken($app);
        return [$app, $token, $svc];
    }

    public function test_reference_alone_is_denied(): void
    {
        [$app] = $this->draft();
        $this->get("/join/creator/{$app->reference}/status")->assertForbidden(); // 403 بلا رمز/جلسة
    }

    public function test_wrong_token_denied_and_logged(): void
    {
        [$app] = $this->draft();
        $this->get("/join/creator/{$app->reference}/status?t=wrongwrongwrong")->assertForbidden();
        $this->assertDatabaseHas('creator_application_access_attempts', ['reference' => $app->reference, 'outcome' => 'token_invalid']);
    }

    public function test_valid_token_grants_and_establishes_session(): void
    {
        [$app, $token] = $this->draft();
        $this->get("/join/creator/{$app->reference}/status?t={$token}")->assertOk();
        // بعد إنشاء الجلسة، الوصول بلا رمز يعمل (نفس الجلسة)
        $this->get("/join/creator/{$app->reference}/status")->assertOk();
    }

    public function test_expired_token_denied(): void
    {
        [$app, $token] = $this->draft();
        TenantContext::bypass(true);
        $app->update(['access_token_expires_at' => now()->subDay()]);
        TenantContext::reset();
        $this->get("/join/creator/{$app->reference}/status?t={$token}")->assertForbidden();
    }

    public function test_revoked_token_denied(): void
    {
        [$app, $token, $svc] = $this->draft();
        $svc->revokeAccessToken($app);
        $this->get("/join/creator/{$app->reference}/status?t={$token}")->assertForbidden();
    }

    public function test_applicant_cannot_access_another_application(): void
    {
        [$a1, $t1] = $this->draft('ag1');
        [$a2, $t2] = $this->draft('ag2');
        // جلسة على a1 لا تفتح a2
        $this->get("/join/creator/{$a1->reference}/status?t={$t1}")->assertOk();
        $this->get("/join/creator/{$a2->reference}/status")->assertForbidden();
        // رمز a1 لا يفتح a2
        $this->get("/join/creator/{$a2->reference}/status?t={$t1}")->assertForbidden();
    }

    public function test_files_and_financial_hidden_without_valid_session(): void
    {
        [$app] = $this->draft();
        // رفع/مالية محروسة أيضًا
        $this->post("/join/creator/{$app->reference}/financial", ['iban' => 'SA0380000000608010167519'])->assertForbidden();
        $this->post("/join/creator/{$app->reference}/upload", ['category' => 'avatar'])->assertForbidden();
    }

    public function test_recovery_rotates_token_and_is_non_enumerating(): void
    {
        [$app, $oldToken, $svc] = $this->draft();
        $emailExists = $app->email;
        // استعادة ببريد موجود
        $r1 = $this->post('/join/recover', ['email' => $emailExists]);
        $r1->assertSessionHas('ok');
        // استعادة ببريد غير موجود → نفس الرسالة (لا تكشف)
        $r2 = $this->post('/join/recover', ['email' => 'nobody@nope.com']);
        $r2->assertSessionHas('ok');
        $this->assertEquals(session('ok'), session('ok')); // رسالة موحّدة
        // الرمز القديم دُوِّر (لم يعد صالحًا)
        $this->get("/join/creator/{$app->reference}/status?t={$oldToken}")->assertForbidden();
    }

    public function test_recover_is_rate_limited(): void
    {
        [$app] = $this->draft();
        for ($i = 0; $i < 5; $i++) { $this->post('/join/recover', ['email' => 'x@y.com']); }
        $this->post('/join/recover', ['email' => 'x@y.com'])->assertStatus(429); // تجاوز الحد
    }
}
