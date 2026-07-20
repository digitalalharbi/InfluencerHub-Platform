<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement, UsageAggregate, UsageRecord};
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تزامن حقيقي على PostgreSQL: عمليتان متوازيتان (subprocesses) تنشئان عميلًا
 * والحد customers.max=1. لا RefreshDatabase (البيانات تُلتزم لتراها العمليات).
 */
class ConcurrentClientCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh'); // مخطط نظيف على قاعدة الاختبار (influencerhub_testing)
    }
    protected function tearDown(): void { TenantContext::reset(); Artisan::call('migrate:fresh'); parent::tearDown(); }

    public function test_two_parallel_processes_do_not_exceed_customers_max(): void
    {
        // إعداد ملتزم (لا معاملة): مستأجر + مؤسسة + اشتراك بحد 1 + صف تجميع used=0
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'customers.max', 'value' => 1]);
        (new CreateSubscription)->handle($org, $v);
        UsageAggregate::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'feature_key' => 'customers.max',
            'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString(), 'used' => 0]);
        TenantContext::reset();

        // أطلق عمليتين متوازيتين تستهدفان قاعدة الاختبار نفسها
        $script = base_path('tests/Support/concurrent_create_client.php');
        $env = ['DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'influencerhub_testing', 'PATH' => getenv('PATH')];
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $cmd = fn () => proc_open(['php', $script, (string) $org->id, 'active'], $descriptors, $pipes, base_path(), $env);

        $p1 = proc_open(['php', $script, (string) $org->id, 'active'], $descriptors, $pipes1, base_path(), $env);
        $p2 = proc_open(['php', $script, (string) $org->id, 'active'], $descriptors, $pipes2, base_path(), $env);
        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($p1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($p2);

        $results = [trim(str_contains($out1, 'RESULT=') ? explode('RESULT=', $out1)[1] : $out1),
                    trim(str_contains($out2, 'RESULT=') ? explode('RESULT=', $out2)[1] : $out2)];
        sort($results);

        // نتيجة واحدة نجحت وأخرى رُفضت
        $this->assertEquals(['REJECTED', 'SUCCESS'], $results, "outputs: [$out1 | $out2]");

        // التحقق النهائي في قاعدة البيانات
        TenantContext::bypass(true);
        $this->assertEquals(1, UsageAggregate::where('organization_id', $org->id)->where('feature_key', 'customers.max')->value('used'), 'usage must not exceed 1');
        $this->assertEquals(1, Client::where('tenant_id', $t->id)->whereIn('status', ['active', 'qualified'])->count(), 'exactly one counting client');
        $this->assertEquals(1, UsageRecord::where('organization_id', $org->id)->where('feature_key', 'customers.max')->where('amount', '>', 0)->count(), 'no orphan usage record');
        TenantContext::reset();
    }
}
