<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\Creators\Actions\ApproveCreatorApplication;
use App\Domain\Creators\Jobs\FinalizeCreatorFilesJob;
use App\Domain\Creators\Models\{Creator, CreatorApplicationDocument};
use App\Domain\Creators\Services\{ApplicationDocumentService, CreatorApplicationService};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 hardening — إتمام الملفات post-commit: idempotent، checksum، الأصل يبقى، reconcile. */
class FileFinalizationTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function approvedWithAvatar(): array
    {
        Storage::fake('local');
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $actor = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $actor->id, 'role' => 'agency_admin', 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'creators.max', 'value' => 5]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        $svc = app(CreatorApplicationService::class);
        $app = $svc->startDraft($t, ['account_type' => 'influencer', 'full_name' => 'ريناد', 'email' => Str::random(5) . '@a.com']);
        app(ApplicationDocumentService::class)->upload($app, 'avatar', UploadedFile::fake()->image('a.png'), null);
        $svc->transition($app->fresh(), 'under_review');
        $creator = app(ApproveCreatorApplication::class)->handle($org, $app->fresh(), $actor);
        return [$t, $org, $app->fresh(), $creator, $actor];
    }

    public function test_original_file_kept_after_finalization(): void
    {
        [$t, $org, $app, $creator] = $this->approvedWithAvatar();
        TenantContext::bypass(true);
        $doc = CreatorApplicationDocument::where('application_id', $app->id)->first();
        TenantContext::reset();
        Storage::disk('local')->assertExists($doc->path);          // الأصل باقٍ
        Storage::disk('local')->assertExists($doc->transferred_path); // النسخة موجودة
        $this->assertEquals('completed', $doc->transfer_status);
    }

    public function test_rerun_finalization_is_idempotent(): void
    {
        [$t, $org, $app, $creator] = $this->approvedWithAvatar();
        // إعادة التشغيل لا تُنشئ نسخة مكررة ولا تكسر
        FinalizeCreatorFilesJob::dispatchSync($app->id);
        TenantContext::bypass(true);
        $this->assertEquals(1, CreatorApplicationDocument::where('application_id', $app->id)->where('kind', 'avatar')->count());
        $this->assertEquals('completed', CreatorApplicationDocument::where('application_id', $app->id)->first()->transfer_status);
        TenantContext::reset();
    }

    public function test_reconcile_command_reprocesses_pending(): void
    {
        [$t, $org, $app, $creator] = $this->approvedWithAvatar();
        // خرّب الحالة إلى failed ثم صالِح
        TenantContext::bypass(true);
        CreatorApplicationDocument::where('application_id', $app->id)->update(['transfer_status' => 'failed']);
        TenantContext::reset();
        $this->artisan('creators:reconcile-files')->assertSuccessful();
        TenantContext::bypass(true);
        $this->assertEquals('completed', CreatorApplicationDocument::where('application_id', $app->id)->first()->transfer_status);
        TenantContext::reset();
    }

    public function test_missing_original_marks_failed_not_lost(): void
    {
        [$t, $org, $app, $creator] = $this->approvedWithAvatar();
        TenantContext::bypass(true);
        $doc = CreatorApplicationDocument::where('application_id', $app->id)->first();
        // احذف النسخة والأصل معًا ثم أعد الجدولة → failed (لا انهيار)
        Storage::disk('local')->delete([$doc->path, $doc->transferred_path]);
        $doc->update(['transfer_status' => 'pending']);
        TenantContext::reset();
        FinalizeCreatorFilesJob::dispatchSync($app->id);
        TenantContext::bypass(true);
        $this->assertEquals('failed', CreatorApplicationDocument::where('application_id', $app->id)->first()->transfer_status);
        TenantContext::reset();
    }
}
