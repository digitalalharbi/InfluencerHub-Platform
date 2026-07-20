<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\CRM\Models\{Client, ImportBatch};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 3 — مستورد النظام القديم: CSV/JSON + mapping + dry-run + dedup + rollback. */
class LegacyImportTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private Tenant $tenant;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 't', 'slug' => 'imp-' . Str::random(5), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $this->tenant->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $this->tenant->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'customers.max', 'value' => 100]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        $this->dir = sys_get_temp_dir() . '/imp_' . Str::random(6);
        mkdir($this->dir);
    }

    private function csvFile(string $content): string { $p = "{$this->dir}/legacy.csv"; file_put_contents($p, $content); return $p; }
    private function jsonFile(array $rows): string { $p = "{$this->dir}/legacy.json"; file_put_contents($p, json_encode($rows)); return $p; }
    private function clientCount(): int { TenantContext::bypass(true); $c = Client::where('tenant_id', $this->tenant->id)->count(); TenantContext::reset(); return $c; }

    public function test_dry_run_writes_nothing(): void
    {
        $file = $this->csvFile("name,email,status\nنايك,n@x.com,active\nستي سي,s@x.com,active\n");
        Artisan::call('import:legacy-clients', ['--file' => $file, '--tenant' => $this->tenant->id, '--dry-run' => true]);
        $this->assertStringContainsString('DRY-RUN', Artisan::output());
        $this->assertEquals(0, $this->clientCount());          // لا كتابة
        $this->assertEquals(0, ImportBatch::count());     // لا دفعة
    }

    public function test_csv_import_creates_clients_and_batch(): void
    {
        $file = $this->csvFile("name,email,status\nنايك,n@x.com,active\nستي سي,s@x.com,qualified\n");
        Artisan::call('import:legacy-clients', ['--file' => $file, '--tenant' => $this->tenant->id]);
        $this->assertEquals(2, $this->clientCount());
        TenantContext::bypass(true);
        $this->assertEquals(2, ImportBatch::first()->imported_count);
        $this->assertDatabaseHas('clients', ['display_name' => 'نايك', 'email' => 'n@x.com']);
        TenantContext::reset();
    }

    public function test_json_import_with_custom_mapping(): void
    {
        $file = $this->jsonFile([['CompanyName' => 'شركة أ', 'Mail' => 'a@x.com'], ['CompanyName' => 'شركة ب', 'Mail' => 'b@x.com']]);
        $mapping = json_encode(['display_name' => 'CompanyName', 'email' => 'Mail']);
        Artisan::call('import:legacy-clients', ['--file' => $file, '--tenant' => $this->tenant->id, '--mapping' => $mapping]);
        $this->assertEquals(2, $this->clientCount());
        $this->assertDatabaseHas('clients', ['display_name' => 'شركة أ', 'email' => 'a@x.com']);
    }

    public function test_duplicate_email_is_skipped(): void
    {
        $file = $this->csvFile("name,email,status\nأول,dup@x.com,active\nثانٍ,dup@x.com,active\n");
        Artisan::call('import:legacy-clients', ['--file' => $file, '--tenant' => $this->tenant->id]);
        $this->assertEquals(1, $this->clientCount()); // الثاني (بريد مكرر) تُخطّي
        TenantContext::bypass(true);
        $this->assertEquals(1, ImportBatch::first()->skipped_count);
        TenantContext::reset();
    }

    public function test_rollback_batch_removes_its_clients(): void
    {
        $file = $this->csvFile("name,email,status\nأ,a@x.com,active\nب,b@x.com,active\n");
        Artisan::call('import:legacy-clients', ['--file' => $file, '--tenant' => $this->tenant->id]);
        $this->assertEquals(2, $this->clientCount());
        TenantContext::bypass(true);
        $batchId = ImportBatch::first()->id;
        TenantContext::reset();

        Artisan::call('import:legacy-clients', ['--rollback-batch' => $batchId]);
        $this->assertEquals(0, $this->clientCount()); // حذف ناعم للدفعة كاملةً
        TenantContext::bypass(true);
        $this->assertEquals('rolled_back', ImportBatch::find($batchId)->status);
        $this->assertEquals(2, Client::onlyTrashed()->where('import_batch_id', $batchId)->count());
        TenantContext::reset();
    }

    public function test_row_without_name_is_skipped(): void
    {
        $file = $this->csvFile("name,email,status\n,noname@x.com,active\nصحيح,ok@x.com,active\n");
        Artisan::call('import:legacy-clients', ['--file' => $file, '--tenant' => $this->tenant->id]);
        $this->assertEquals(1, $this->clientCount());
    }
}
