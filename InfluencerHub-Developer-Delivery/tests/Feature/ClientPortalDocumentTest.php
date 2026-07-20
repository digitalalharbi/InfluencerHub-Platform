<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientDocument, ClientMember};
use App\Domain\CRM\Services\ClientDocumentService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 5 — مستندات العميل الخاصّة: رفع/versioning/رؤية/تنزيل مُدقّق/IDOR/مراجعة. */
class ClientPortalDocumentTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(string $role = 'client_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'c', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-1', 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $c, $t];
    }
    private function svc(): ClientDocumentService { return app(ClientDocumentService::class); }

    public function test_upload_private_with_checksum_pending_status(): void
    {
        Storage::fake('local');
        [$u, $c] = $this->ctx();
        $d = $this->svc()->upload($c, 'contract', 'عقد', UploadedFile::fake()->create('c.pdf', 100, 'application/pdf'), $u->id);
        $this->assertEquals('pending', $d->status);
        $this->assertEquals('client_visible', $d->visibility);
        $this->assertEquals(64, strlen($d->checksum_sha256));
        $this->assertStringContainsString("clients/{$c->tenant_id}/{$c->id}/documents", $d->path);
        Storage::disk('local')->assertExists($d->path);
    }

    public function test_reject_executable_and_wrong_mime(): void
    {
        Storage::fake('local');
        [$u, $c] = $this->ctx();
        $this->expectException(\RuntimeException::class);
        $this->svc()->upload($c, 'contract', 'x', UploadedFile::fake()->create('evil.php', 1, 'application/pdf'), $u->id);
    }

    public function test_reupload_creates_new_version_and_resets_review(): void
    {
        Storage::fake('local');
        [$u, $c] = $this->ctx();
        $d = $this->svc()->upload($c, 'contract', 'عقد', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), $u->id);
        $this->svc()->review($d, $u->id, 'approved');
        $d2 = $this->svc()->upload($c, 'contract', 'عقد', UploadedFile::fake()->create('c2.pdf', 10, 'application/pdf'), $u->id, 'client_visible', $d->id);
        $this->assertEquals($d->id, $d2->id);
        TenantContext::bypass(true);
        $fresh = $d2->fresh();
        $this->assertEquals(2, $fresh->version_number);           // إصدار جديد
        $this->assertEquals('pending', $fresh->status);           // أُعيدت المراجعة
        $this->assertEquals(1, $d2->versions()->count());          // النسخة السابقة محفوظة
        TenantContext::reset();
    }

    public function test_agency_internal_not_shown_to_client_and_download_blocked(): void
    {
        Storage::fake('local');
        [$u, $c] = $this->ctx();
        $internal = $this->svc()->upload($c, 'other', 'ZZ-INTERNAL-DOC', UploadedFile::fake()->create('i.pdf', 10, 'application/pdf'), $u->id, 'agency_internal');
        // القائمة لا تعرضه
        $this->actingAs($u)->get('/client/documents')->assertOk()->assertDontSee('ZZ-INTERNAL-DOC');
        // التنزيل من العميل مرفوض (403)
        $this->actingAs($u)->get("/client/documents/{$internal->id}/download")->assertForbidden();
    }

    public function test_download_authorized_and_logged(): void
    {
        Storage::fake('local');
        [$u, $c] = $this->ctx();
        $d = $this->svc()->upload($c, 'contract', 'عقد', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), $u->id);
        $this->actingAs($u)->get("/client/documents/{$d->id}/download")->assertOk();
        TenantContext::bypass(true);
        $this->assertDatabaseHas('client_document_access_logs', ['document_id' => $d->id, 'actor_type' => 'client', 'action' => 'download']);
        TenantContext::reset();
    }

    public function test_idor_other_client_document_404(): void
    {
        Storage::fake('local');
        [$u1, $c1] = $this->ctx();
        [$u2, $c2] = $this->ctx();
        $dOther = $this->svc()->upload($c2, 'contract', 'عقد B', UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'), $u2->id);
        $this->actingAs($u1)->get("/client/documents/{$dOther->id}/download")->assertNotFound();
    }

    public function test_agency_review_changes_status_and_logs_decision(): void
    {
        Storage::fake('local');
        [$u, $c] = $this->ctx();
        $d = $this->svc()->upload($c, 'contract', 'عقد', UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), $u->id);
        $this->svc()->review($d, $u->id, 'changes_requested', 'الرجاء تحديث التاريخ');
        TenantContext::bypass(true);
        $fresh = $d->fresh();
        $this->assertEquals('changes_requested', $fresh->status);
        $this->assertEquals('الرجاء تحديث التاريخ', $fresh->rejection_reason);
        $this->assertDatabaseHas('client_document_reviews', ['document_id' => $d->id, 'decision' => 'changes_requested']);
        TenantContext::reset();
    }

    public function test_member_role_cannot_upload(): void
    {
        [$u, $c] = $this->ctx('client_member');
        $this->actingAs($u)->post('/client/documents', ['category' => 'contract', 'title' => 'x'])->assertForbidden();
    }
}
