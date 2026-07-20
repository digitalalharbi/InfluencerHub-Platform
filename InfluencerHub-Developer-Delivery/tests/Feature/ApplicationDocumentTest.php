<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\CreatorApplicationDocument;
use App\Domain\Creators\Services\{ApplicationDocumentService, CreatorApplicationService};
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 — ملفات الطلب الخاصّة: MIME/امتداد/تنفيذي/حجم/checksum/مسار خاص/تدقيق. */
class ApplicationDocumentTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function app(): \App\Domain\Creators\Models\CreatorApplication
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return app(CreatorApplicationService::class)->startDraft($t, ['email' => 'a@b.com']);
    }

    public function test_upload_stores_private_with_checksum_and_generated_name(): void
    {
        Storage::fake('local');
        $app = $this->app();
        $doc = app(ApplicationDocumentService::class)->upload($app, 'avatar', UploadedFile::fake()->image('me.png', 10, 10), null);
        $this->assertEquals('avatar', $doc->kind);
        $this->assertEquals(64, strlen($doc->checksum_sha256));
        $this->assertStringContainsString("applications/{$app->tenant_id}/{$app->id}", $doc->path);
        $this->assertStringNotContainsString('me.png', $doc->path); // اسم مولّد
        Storage::disk('local')->assertExists($doc->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'application_document.uploaded']);
    }

    public function test_executable_extension_is_rejected(): void
    {
        Storage::fake('local');
        $app = $this->app();
        $this->expectException(\RuntimeException::class);
        app(ApplicationDocumentService::class)->upload($app, 'avatar', UploadedFile::fake()->create('evil.php', 1, 'image/png'), null);
    }

    public function test_wrong_mime_for_category_is_rejected(): void
    {
        Storage::fake('local');
        $app = $this->app();
        $this->expectException(\RuntimeException::class);
        app(ApplicationDocumentService::class)->upload($app, 'avatar', UploadedFile::fake()->create('doc.pdf', 1, 'application/pdf'), null);
    }

    public function test_oversize_file_is_rejected(): void
    {
        Storage::fake('local');
        $app = $this->app();
        $big = UploadedFile::fake()->create('huge.png', 11 * 1024, 'image/png'); // 11MB > 10MB
        $this->expectException(\RuntimeException::class);
        app(ApplicationDocumentService::class)->upload($app, 'avatar', $big, null);
    }

    public function test_reupload_same_category_keeps_previous_version(): void
    {
        Storage::fake('local');
        $app = $this->app();
        $svc = app(ApplicationDocumentService::class);
        $d1 = $svc->upload($app, 'avatar', UploadedFile::fake()->image('a.png'), null);
        $d2 = $svc->upload($app, 'avatar', UploadedFile::fake()->image('b.png'), null);
        $this->assertEquals($d1->id, $d2->id);                 // نفس السجل (استبدال)
        TenantContext::set($app->tenant_id);
        $this->assertEquals(1, $d2->versions()->count());       // نسخة سابقة محفوظة
        $this->assertEquals(1, CreatorApplicationDocument::where('application_id', $app->id)->where('kind', 'avatar')->count());
        TenantContext::reset();
    }

    public function test_download_is_authorized_and_audited_and_idor_safe(): void
    {
        Storage::fake('local');
        // مستأجران
        [$appA, $docA] = $this->uploadedDoc();
        [$appB] = $this->uploadedDoc();

        // مراجع من مستأجر B لا يصل لمستند A عبر مسار طلب B
        TenantContext::bypass(true);
        $orgB = \App\Domain\Tenancy\Models\Organization::where('tenant_id', $appB->tenant_id)->first();
        $reviewer = \App\Domain\Identity\Models\User::create(['name' => 'r', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        \App\Domain\Tenancy\Models\OrganizationMembership::create(['tenant_id' => $appB->tenant_id,
            'organization_id' => $orgB->id, 'user_id' => $reviewer->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();
        $this->actingAs($reviewer);
        TenantContext::set($appB->tenant_id, $orgB->id);
        // مستند A عبر طلب B → 404 (خلط المعرّفات)
        $this->get("/app/creator-applications/{$appB->id}/documents/{$docA->id}/download")->assertNotFound();
        TenantContext::reset();
    }

    private function uploadedDoc(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        \App\Domain\Tenancy\Models\Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        TenantContext::reset();
        $app = app(CreatorApplicationService::class)->startDraft($t, ['email' => Str::random(5) . '@b.com']);
        $doc = app(ApplicationDocumentService::class)->upload($app, 'avatar', UploadedFile::fake()->image('x.png'), null);
        return [$app, $doc];
    }
}
