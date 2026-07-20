<?php

namespace Tests\Feature;

use App\Domain\Creators\Jobs\SendOtpJob;
use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Creators\Services\Otp\NullSmsSender;
use App\Domain\Creators\Models\CreatorApplicationVerification;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 — تسليم OTP: طابور، cooldown، عدم عرض في الإنتاج، SMS بلا مزوّد. */
class OtpDeliveryTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private string $token = '';
    private function draft(): \App\Domain\Creators\Models\CreatorApplication
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $svc = app(CreatorApplicationService::class);
        $app = $svc->startDraft($t, ['email' => 'a@b.com', 'phone' => '+966500000000']);
        $this->token = $svc->issueAccessToken($app);
        return $app;
    }

    public function test_issuing_otp_dispatches_queued_job(): void
    {
        Queue::fake();
        $app = $this->draft();
        app(CreatorApplicationService::class)->issueOtp($app, 'email');
        Queue::assertPushed(SendOtpJob::class, fn ($job) => $job->channel === 'email' && $job->destination === 'a@b.com');
    }

    public function test_otp_stored_hashed_only(): void
    {
        Queue::fake();
        $app = $this->draft();
        $code = app(CreatorApplicationService::class)->issueOtp($app, 'email');
        TenantContext::bypass(true);
        $this->assertDatabaseMissing('creator_application_verifications', ['code_hash' => $code]);
        $this->assertDatabaseHas('creator_application_verifications', ['code_hash' => hash('sha256', $code)]);
        TenantContext::reset();
    }

    public function test_resend_cooldown_blocks_rapid_reissue(): void
    {
        Queue::fake();
        $svc = app(CreatorApplicationService::class);
        $app = $this->draft();
        $svc->issueOtp($app, 'email');
        $this->expectException(\RuntimeException::class); // خلال cooldown
        $svc->issueOtp($app, 'email');
    }

    public function test_production_does_not_flash_dev_otp(): void
    {
        Queue::fake();
        $app = $this->draft();
        app()->detectEnvironment(fn () => 'production'); // محاكاة الإنتاج
        $this->post("/join/creator/{$app->reference}/verify-email?t={$this->token}")
            ->assertSessionMissing('dev_otp'); // لا يُعرض الرمز في الإنتاج
        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_sms_without_provider_returns_waiting_for_credentials(): void
    {
        $status = (new NullSmsSender())->send('+966500000000', '123456');
        $this->assertEquals('waiting_for_credentials', $status); // لا يدّعي الإرسال
    }

    public function test_dev_flashes_otp_for_local_preview(): void
    {
        Queue::fake();
        $app = $this->draft();
        $this->post("/join/creator/{$app->reference}/verify-email?t={$this->token}")->assertSessionHas('dev_otp');
    }
}
