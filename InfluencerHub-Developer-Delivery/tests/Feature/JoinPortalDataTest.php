<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 — جمع بيانات الطلب من البوابة العامة: منصات/خدمات/أعمال/موثوق/مالية. */
class JoinPortalDataTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private string $token = '';

    private function draft(): CreatorApplication
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $svc = app(CreatorApplicationService::class);
        $app = $svc->startDraft($t, ['email' => 'a@b.com', 'account_type' => 'influencer']);
        $this->token = $svc->issueAccessToken($app); // رمز وصول للاختبار (يحاكي رابط المتقدّم)
        return $app;
    }

    /** يبني مسار endpoint مع رمز الوصول (المرجع وحده لا يكفي بعد التصلّب). */
    private function u(CreatorApplication $a, string $path): string
    {
        return "/join/creator/{$a->reference}/{$path}?t={$this->token}";
    }
    private function reload(CreatorApplication $a): CreatorApplication
    {
        TenantContext::bypass(true); $f = $a->fresh(); TenantContext::reset(); return $f;
    }

    public function test_applicant_adds_platform_service_portfolio(): void
    {
        $a = $this->draft();
        $this->post($this->u($a, "platforms"), ['platform' => 'instagram', 'username' => 'me', 'followers_count' => 1000])->assertRedirect();
        $this->post($this->u($a, "services"), ['service_type' => 'post', 'price' => 1500, 'delivery_days' => 3])->assertRedirect();
        $this->post($this->u($a, "portfolio"), ['type' => 'link', 'url' => 'https://x.co/a', 'previous_brand' => 'Nike'])->assertRedirect();

        TenantContext::bypass(true);
        $this->assertDatabaseHas('creator_application_platforms', ['application_id' => $a->id, 'username' => 'me']);
        $this->assertDatabaseHas('creator_application_services', ['application_id' => $a->id, 'service_type' => 'post', 'price_minor' => 150000]); // بلا float
        $this->assertDatabaseHas('creator_application_portfolios', ['application_id' => $a->id, 'status' => 'submitted']);
        TenantContext::reset();
    }

    public function test_financial_iban_encrypted_and_masked(): void
    {
        $a = $this->draft();
        $this->post($this->u($a, "financial"), ['beneficiary_name' => 'نورة', 'bank_name' => 'الأهلي', 'iban' => 'SA0380000000608010167519'])->assertRedirect();
        $f = $this->reload($a);
        $this->assertEquals('7519', $f->iban_last4);
        $this->assertStringNotContainsString('0380000000', (string) $f->iban_encrypted);
        $this->assertEquals('pending', $f->financial_verification_status);
    }

    public function test_mowthooq_saved_as_pending_not_verified(): void
    {
        $a = $this->draft();
        $this->post($this->u($a, "mowthooq"), ['mowthooq_license_number' => 'LIC-9', 'mowthooq_status' => 'verified'])->assertRedirect();
        $f = $this->reload($a);
        $this->assertEquals('LIC-9', $f->mowthooq_license_number);
        $this->assertNotEquals('verified', $f->mowthooq_status); // المتقدّم لا يعتمد
    }

    public function test_data_survives_reload_from_postgres(): void
    {
        $a = $this->draft();
        $this->post($this->u($a, "services"), ['service_type' => 'reel', 'price' => 2000]);
        // إعادة تحميل صفحة الحالة تعرض الخدمة (من قاعدة البيانات، لا localStorage)
        $this->get($this->u($a, "status"))->assertOk()->assertSee('reel');
    }
}
