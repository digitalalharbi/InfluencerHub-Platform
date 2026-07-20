<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Exceptions\EntitlementLimitExceeded;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement, UsageAggregate};
use App\Domain\CRM\Actions\{CreateClient, ArchiveClient, RestoreClient, RecalculateCustomerUsage, CreateBrand};
use App\Domain\CRM\Models\{Client, Brand};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use App\Domain\Billing\Services\UsageMeterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 3 — CRM: enforcement customers.max + archive/restore + recalc + isolation. */
class CrmClientTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** org + user + subscription بحد customers.max=$max. */
    private function ctx(int $max = 2): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $user = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'customers.max', 'value' => $max]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        TenantContext::set($t->id, $org->id);
        return [$org, $user];
    }

    private function create(Organization $org, User $u, string $status = 'active', string $name = 'C'): Client
    {
        return app(CreateClient::class)->handle($org, ['display_name' => $name, 'type' => 'company', 'status' => $status], $u);
    }

    public function test_create_client_under_limit_consumes_usage(): void
    {
        [$org, $u] = $this->ctx(2);
        $c = $this->create($org, $u, 'active');
        $this->assertStringStartsWith('CL-', $c->client_number);
        $this->assertEquals(1, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.created']);
        $this->assertDatabaseHas('client_status_history', ['client_id' => $c->id, 'to_status' => 'active']);
    }

    public function test_lead_client_does_not_consume_usage(): void
    {
        [$org, $u] = $this->ctx(1);
        $this->create($org, $u, 'lead'); // lead لا يُحسب
        $this->assertEquals(0, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
    }

    public function test_reject_create_when_over_customers_max(): void
    {
        [$org, $u] = $this->ctx(1);
        $this->create($org, $u, 'active');           // يستهلك 1/1
        $countBefore = Client::count();
        $this->expectException(EntitlementLimitExceeded::class);
        try {
            $this->create($org, $u, 'active');       // يتجاوز → يرمي
        } finally {
            // failed create لم يترك أثرًا (rollback): لا عميل جديد، الاستهلاك ما زال 1
            $this->assertEquals($countBefore, Client::count());
            $this->assertEquals(1, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
        }
    }

    public function test_archive_releases_usage_idempotently(): void
    {
        [$org, $u] = $this->ctx(2);
        $c = $this->create($org, $u, 'active');
        $this->assertEquals(1, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
        app(ArchiveClient::class)->handle($org, $c);
        $this->assertEquals(0, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
        // idempotent: أرشفة ثانية لا تُنقص أكثر
        app(ArchiveClient::class)->handle($org, $c->fresh());
        $this->assertEquals(0, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
    }

    public function test_restore_reconsumes_usage(): void
    {
        [$org, $u] = $this->ctx(2);
        $c = $this->create($org, $u, 'active');
        app(ArchiveClient::class)->handle($org, $c);
        app(RestoreClient::class)->handle($org, $c->fresh(), 'active');
        $this->assertEquals(1, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
    }

    public function test_recalculate_fixes_drift(): void
    {
        [$org, $u] = $this->ctx(10);
        $this->create($org, $u, 'active');
        $this->create($org, $u, 'active');
        $this->create($org, $u, 'lead'); // لا يُحسب
        // تخريب متعمّد للتجميع
        UsageAggregate::where('organization_id', $org->id)->where('feature_key', 'customers.max')->update(['used' => 99]);
        $real = app(RecalculateCustomerUsage::class)->handle($org);
        $this->assertEquals(2, $real); // العميلان النشطان فقط
        $this->assertEquals(2, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
    }

    public function test_brands_and_contacts_do_not_consume_customer_units(): void
    {
        [$org, $u] = $this->ctx(1);
        $c = $this->create($org, $u, 'active'); // 1/1
        $brand = app(CreateBrand::class)->handle($c, ['name' => 'BrandX'], $u);
        $this->assertEquals($c->id, $brand->client_id);
        // إنشاء علامة لم يستهلك وحدة إضافية
        $this->assertEquals(1, app(UsageMeterService::class)->currentUsage($org, 'customers.max'));
    }

    public function test_client_is_tenant_isolated(): void
    {
        [$orgA, $uA] = $this->ctx(5);
        $clientA = $this->create($orgA, $uA, 'active', 'ClientA');
        TenantContext::reset();

        [$orgB] = $this->ctx(5);
        // ضمن سياق B: لا نرى عميل A
        $this->assertEquals(0, Client::where('display_name', 'ClientA')->count());
        $this->assertNull(Client::find($clientA->id));
    }
}
