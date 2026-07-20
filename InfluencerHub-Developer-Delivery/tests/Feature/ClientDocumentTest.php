<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\CRM\Actions\{CreateClient, UploadClientDocument};
use App\Domain\CRM\Models\{Client, ClientDocument};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase 3 — مستندات العميل: MIME/حجم/checksum + عزل IDOR + تدقيق تنزيل + تخزين خاص. */
class ClientDocumentTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(int $max = 5): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $u = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'customers.max', 'value' => $max]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        TenantContext::set($t->id, $org->id);
        return [$org, $u];
    }

    public function test_upload_stores_on_private_disk_with_checksum(): void
    {
        Storage::fake('local');
        [$org, $u] = $this->ctx();
        $client = app(CreateClient::class)->handle($org, ['display_name' => 'C', 'status' => 'active', 'type' => 'company'], $u);
        $file = UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf');
        $doc = app(UploadClientDocument::class)->handle($client, $file, 'brief', 'الموجز', $u);

        $this->assertEquals(64, strlen($doc->checksum_sha256));
        $this->assertStringContainsString("clients/{$client->tenant_id}/{$client->id}", $doc->path);
        $this->assertStringNotContainsString('brief.pdf', $doc->path); // الاسم الأصلي لا يظهر في المسار
        Storage::disk('local')->assertExists($doc->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_document.uploaded']);
    }

    public function test_disallowed_mime_is_rejected(): void
    {
        Storage::fake('local');
        [$org, $u] = $this->ctx();
        $client = app(CreateClient::class)->handle($org, ['display_name' => 'C', 'status' => 'active', 'type' => 'company'], $u);
        $file = UploadedFile::fake()->create('evil.php', 1, 'application/x-php');
        $this->expectException(\RuntimeException::class);
        app(UploadClientDocument::class)->handle($client, $file, 'other', 't', $u);
    }

    public function test_invalid_category_is_rejected(): void
    {
        Storage::fake('local');
        [$org, $u] = $this->ctx();
        $client = app(CreateClient::class)->handle($org, ['display_name' => 'C', 'status' => 'active', 'type' => 'company'], $u);
        $file = UploadedFile::fake()->create('a.pdf', 1, 'application/pdf');
        $this->expectException(\RuntimeException::class);
        app(UploadClientDocument::class)->handle($client, $file, 'nonsense', 't', $u);
    }

    public function test_download_is_audited_over_http(): void
    {
        Storage::fake('local');
        [$org, $u] = $this->ctx();
        $client = app(CreateClient::class)->handle($org, ['display_name' => 'C', 'status' => 'active', 'type' => 'company'], $u);
        $doc = app(UploadClientDocument::class)->handle($client, UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'), 'report', 'r', $u);

        Sanctum::actingAs($u);
        $this->get("/api/v1/clients/{$client->id}/documents/{$doc->id}/download")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_document.downloaded', 'auditable_id' => $doc->id]);
    }

    public function test_cross_tenant_download_is_blocked(): void
    {
        Storage::fake('local');
        [$orgA, $uA] = $this->ctx();
        $clientA = app(CreateClient::class)->handle($orgA, ['display_name' => 'A', 'status' => 'active', 'type' => 'company'], $uA);
        $docA = app(UploadClientDocument::class)->handle($clientA, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'other', 'a', $uA);
        TenantContext::reset();

        [$orgB, $uB] = $this->ctx();
        Sanctum::actingAs($uB);
        // مستأجر آخر لا يصل لمستند A (IDOR) → 404
        $this->get("/api/v1/clients/{$clientA->id}/documents/{$docA->id}/download")->assertNotFound();
    }

    public function test_mismatched_client_document_pair_is_404(): void
    {
        Storage::fake('local');
        [$org, $u] = $this->ctx();
        $c1 = app(CreateClient::class)->handle($org, ['display_name' => 'C1', 'status' => 'active', 'type' => 'company'], $u);
        $c2 = app(CreateClient::class)->handle($org, ['display_name' => 'C2', 'status' => 'active', 'type' => 'company'], $u);
        $doc = app(UploadClientDocument::class)->handle($c1, UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'), 'other', 'x', $u);

        Sanctum::actingAs($u);
        // مستند c1 عبر مسار c2 → لا تطابق → 404 (منع خلط المعرفات)
        $this->get("/api/v1/clients/{$c2->id}/documents/{$doc->id}/download")->assertNotFound();
    }

    public function test_delete_is_soft_and_audited(): void
    {
        Storage::fake('local');
        [$org, $u] = $this->ctx();
        $client = app(CreateClient::class)->handle($org, ['display_name' => 'C', 'status' => 'active', 'type' => 'company'], $u);
        $doc = app(UploadClientDocument::class)->handle($client, UploadedFile::fake()->create('d.pdf', 10, 'application/pdf'), 'other', 'd', $u);

        Sanctum::actingAs($u);
        $this->delete("/api/v1/clients/{$client->id}/documents/{$doc->id}")->assertOk();
        $this->assertSoftDeleted('client_documents', ['id' => $doc->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_document.deleted']);
    }
}
