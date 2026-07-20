<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Billing\Services\UsageMeterService;
use App\Domain\Creators\Models\{CreatorApplication, CreatorApplicationPlatform};
use App\Domain\Creators\Services\{CreatorApplicationService, CreatorEntitlementService};
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/** Phase 4 — الحدود الخمسة: ugc/portal/social/storage/monthly. atomic + idempotent. */
class CreatorEntitlementsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** ينشئ org باشتراك يحمل الحدود المعطاة. */
    private function orgWith(array $entitlements): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        foreach ($entitlements as $fk => $val) {
            PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => $fk, 'value' => $val]);
        }
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        return [$t, $org];
    }
    private function svc(): CreatorEntitlementService { return app(CreatorEntitlementService::class); }

    public function test_ugc_disabled_blocks_ugc_selection(): void
    {
        [$t, $org] = $this->orgWith(['ugc_creator.enabled' => 0]);
        $this->expectException(RuntimeException::class);
        $this->svc()->assertUgcAllowed($org, 'ugc_creator');
    }

    public function test_ugc_enabled_allows_both(): void
    {
        [$t, $org] = $this->orgWith(['ugc_creator.enabled' => 1]);
        $this->svc()->assertUgcAllowed($org, 'both'); // لا استثناء
        $this->svc()->assertUgcAllowed($org, 'influencer'); // المؤثّر دائمًا مسموح
        $this->assertTrue(true);
    }

    public function test_portal_disabled_reported(): void
    {
        [$t, $org] = $this->orgWith(['creator_portal.enabled' => 0]);
        $this->assertFalse($this->svc()->portalEnabled($org));
        [$t2, $org2] = $this->orgWith(['creator_portal.enabled' => 1]);
        $this->assertTrue($this->svc()->portalEnabled($org2));
    }

    public function test_social_integrations_max_blocks_extra_platform(): void
    {
        [$t, $org] = $this->orgWith(['social_integrations.max' => 2]);
        $app = app(CreatorApplicationService::class)->startDraft($t, ['email' => 'a@b.com']);
        TenantContext::set($t->id);
        CreatorApplicationPlatform::create(['tenant_id' => $t->id, 'application_id' => $app->id, 'platform' => 'instagram']);
        CreatorApplicationPlatform::create(['tenant_id' => $t->id, 'application_id' => $app->id, 'platform' => 'tiktok']);
        TenantContext::reset();
        $this->expectException(RuntimeException::class); // الثالثة تتجاوز
        $this->svc()->assertSocialWithinLimit($org, $app);
    }

    public function test_storage_limit_blocks_oversize_upload(): void
    {
        [$t, $org] = $this->orgWith(['creator_storage.gb' => 0]); // لا مساحة
        $app = app(CreatorApplicationService::class)->startDraft($t, ['email' => 'a@b.com']);
        $this->expectException(RuntimeException::class);
        $this->svc()->assertStorageAvailable($org, 1024);
    }

    public function test_monthly_max_consumed_once_idempotent(): void
    {
        [$t, $org] = $this->orgWith(['creator_applications.monthly.max' => 5]);
        $app = app(CreatorApplicationService::class)->startDraft($t, ['email' => 'a@b.com']);
        $this->svc()->consumeSubmission($org, $app);
        $this->svc()->consumeSubmission($org, $app); // إعادة إرسال نفس الطلب لا تُحسب مرتين
        TenantContext::set($t->id, $org->id);
        $this->assertEquals(1, app(UsageMeterService::class)->currentUsage($org, 'creator_applications.monthly.max'));
        TenantContext::reset();
    }

    public function test_monthly_max_zero_blocks_submission(): void
    {
        [$t, $org] = $this->orgWith(['creator_applications.monthly.max' => 0]);
        $app = app(CreatorApplicationService::class)->startDraft($t, ['email' => 'a@b.com']);
        $this->expectException(\App\Domain\Billing\Exceptions\EntitlementLimitExceeded::class);
        $this->svc()->consumeSubmission($org, $app);
    }
}
