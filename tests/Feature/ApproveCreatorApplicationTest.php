<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Exceptions\EntitlementLimitExceeded;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Billing\Services\UsageMeterService;
use App\Domain\Creators\Actions\ApproveCreatorApplication;
use App\Domain\Creators\Models\{Creator, CreatorApplication, CreatorApplicationPlatform};
use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 — قبول الطلب: معاملة ذرّية، entitlement، idempotent، نقل المنصات، منع القبول المزدوج. */
class ApproveCreatorApplicationTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(int $creatorsMax = 5): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $actor = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $actor->id, 'role' => 'agency_admin', 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'creators.max', 'value' => $creatorsMax]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        return [$t, $org, $actor];
    }

    private function submittedApp(Tenant $t, array $platforms = []): CreatorApplication
    {
        $svc = app(CreatorApplicationService::class);
        $app = $svc->startDraft($t, ['account_type' => 'influencer', 'full_name' => 'ريناد', 'email' => Str::random(5) . '@a.com']);
        TenantContext::set($t->id);
        $app->update(['email_verified_at' => now()]);
        foreach ($platforms as $p) {
            CreatorApplicationPlatform::create(['tenant_id' => $t->id, 'application_id' => $app->id] + $p);
        }
        TenantContext::reset();
        return $svc->transition($app, 'under_review');
    }

    public function test_approve_creates_user_creator_membership_and_transfers_platforms(): void
    {
        [$t, $org, $actor] = $this->ctx();
        $app = $this->submittedApp($t, [['platform' => 'instagram', 'username' => 'renad', 'followers_count' => 1000]]);
        $creator = app(ApproveCreatorApplication::class)->handle($org, $app, $actor);

        TenantContext::set($t->id, $org->id);
        $this->assertEquals('active', $creator->status);
        $this->assertNotNull($creator->user_id);
        $this->assertEquals('approved', $app->fresh()->status);
        $this->assertEquals($creator->id, $app->fresh()->creator_id);
        $this->assertEquals(1, $creator->platforms()->count());               // نُقلت المنصة
        $this->assertDatabaseHas('organization_memberships', ['user_id' => $creator->user_id, 'role' => 'influencer']);
        $this->assertEquals(1, app(UsageMeterService::class)->currentUsage($org, 'creators.max')); // استُهلك
        TenantContext::reset();
    }

    public function test_double_approve_is_blocked_and_usage_not_double_consumed(): void
    {
        [$t, $org, $actor] = $this->ctx();
        $app = $this->submittedApp($t);
        app(ApproveCreatorApplication::class)->handle($org, $app, $actor);

        try {
            app(ApproveCreatorApplication::class)->handle($org, $app->fresh(), $actor);
            $this->fail('كان يجب رفض القبول المزدوج');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('مقبول', $e->getMessage());
        }
        TenantContext::set($t->id, $org->id);
        $this->assertEquals(1, Creator::count());                             // مبدع واحد فقط
        $this->assertEquals(1, app(UsageMeterService::class)->currentUsage($org, 'creators.max'));
        TenantContext::reset();
    }

    public function test_approve_transfers_documents_to_creator_paths(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$t, $org, $actor] = $this->ctx();
        $app = $this->submittedApp($t);
        // ارفع صورة شخصية للطلب
        app(\App\Domain\Creators\Services\ApplicationDocumentService::class)
            ->upload($app, 'avatar', \Illuminate\Http\UploadedFile::fake()->image('me.png'), null);
        $creator = app(ApproveCreatorApplication::class)->handle($org, $app->fresh(), $actor);

        TenantContext::set($t->id, $org->id);
        $this->assertNotNull($creator->fresh()->avatar_path);
        $this->assertStringContainsString("creators/{$t->id}/{$creator->id}", $creator->fresh()->avatar_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($creator->fresh()->avatar_path);
        // ملف الطلب الأصلي يبقى (لم يُحذف)، وحالته transferred
        $this->assertDatabaseHas('creator_application_documents', ['application_id' => $app->id, 'kind' => 'avatar', 'transfer_status' => 'completed']);
        TenantContext::reset();
    }

    public function test_approve_over_creators_max_rolls_back_completely(): void
    {
        [$t, $org, $actor] = $this->ctx(creatorsMax: 0); // لا حصص
        $app = $this->submittedApp($t);
        $this->expectException(EntitlementLimitExceeded::class);
        try {
            app(ApproveCreatorApplication::class)->handle($org, $app, $actor);
        } finally {
            TenantContext::set($t->id, $org->id);
            $this->assertEquals(0, Creator::count());                         // لا مبدع
            $this->assertEquals('under_review', $app->fresh()->status);       // لم تتغيّر الحالة
            $this->assertEquals(0, User::where('email', $app->email)->count()); // لا مستخدم يتيم
            TenantContext::reset();
        }
    }
}
