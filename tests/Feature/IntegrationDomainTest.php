<?php

namespace Tests\Feature;

use App\Domain\Integrations\Adapters\{AdapterRegistry, IntegrationAdapter, SyncResult};
use App\Domain\Integrations\Jobs\SyncProviderJob;
use App\Domain\Integrations\Models\{ExternalObjectMap, IntegrationConnection, IntegrationSyncRun};
use App\Domain\Integrations\Services\IntegrationConnectionService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * نطاق التكاملات + إطار المزامنة — مُختبَر بمحوّل مزيّف (INTERNAL_VERIFIED).
 * المحوّلات الحقيقية محجوبة خارجيًا (بلا بيانات اعتماد) لكن الإطار كامل: اتّصال
 * بحالة صريحة، رموز مُعمّاة، تشغيلة بعدّادات ومؤشّر وصحّة، وفشل يُسجَّل ويُعيد الرمي.
 */
class IntegrationDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
    }

    private function fakeAdapter(string $provider, ?SyncResult $result = null, ?\Throwable $throw = null): IntegrationAdapter
    {
        return new class($provider, $result, $throw) implements IntegrationAdapter {
            public function __construct(private string $p, private ?SyncResult $r, private ?\Throwable $t) {}
            public function provider(): string { return $this->p; }
            public function capabilities(): array { return ['creator_profile', 'content']; }
            public function sync(IntegrationConnection $c, IntegrationSyncRun $run): SyncResult
            {
                if ($this->t) throw $this->t;
                return $this->r ?? new SyncResult(fetched: 10, created: 7, updated: 3, cursor: 'cur-2');
            }
        };
    }

    public function test_connect_stores_encrypted_tokens_and_hides_them(): void
    {
        $t = $this->tenant();
        $svc = new IntegrationConnectionService(new AdapterRegistry([]));
        $conn = $svc->connect($t->id, 'tiktok', 'production', [
            'status' => 'connected', 'external_account_id' => 'acc1', 'access_token' => 'super-secret',
            'refresh_token' => 'refresh-secret', 'scopes' => ['user.info', 'video.list'],
        ]);

        // الرمز مُعمّى في القاعدة (النص الخام لا يظهر)
        $raw = TenantContext::withBypass(fn () => DB::table('integration_connections')->where('id', $conn->id)->value('access_token'));
        $this->assertNotSame('super-secret', $raw);
        // ويُفكّ عبر النموذج
        $this->assertSame('super-secret', $conn->fresh()->access_token);
        // ولا يُسلسَل للواجهة
        $this->assertArrayNotHasKey('access_token', $conn->toArray());
    }

    public function test_run_sync_records_metrics_and_health(): void
    {
        $t = $this->tenant();
        $registry = new AdapterRegistry([$this->fakeAdapter('tiktok')]);
        $svc = new IntegrationConnectionService($registry);
        $conn = $svc->connect($t->id, 'tiktok', 'production', ['status' => 'connected', 'access_token' => 'x']);

        $run = $svc->runSync($conn, 'manual', 'creator_profile');

        $this->assertSame('success', $run->status);
        $this->assertSame(10, $run->fetched);
        $this->assertSame(7, $run->created);
        $this->assertSame('cur-2', $run->cursor);
        $fresh = $conn->fresh();
        $this->assertSame('healthy', $fresh->health);
        $this->assertNotNull($fresh->last_success_sync_at);
    }

    public function test_sync_failure_records_error_and_rethrows(): void
    {
        $t = $this->tenant();
        $registry = new AdapterRegistry([$this->fakeAdapter('tiktok', throw: new \RuntimeException('rate limited'))]);
        $svc = new IntegrationConnectionService($registry);
        $conn = $svc->connect($t->id, 'tiktok', 'production', ['status' => 'connected', 'access_token' => 'x']);

        try {
            $svc->runSync($conn, 'manual');
            $this->fail('expected rethrow');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rate limited', $e->getMessage());
        }

        $run = TenantContext::withBypass(fn () => IntegrationSyncRun::where('connection_id', $conn->id)->latest('id')->first());
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('rate limited', $run->error);
        $this->assertSame('error', $conn->fresh()->health);
    }

    public function test_disconnect_clears_tokens(): void
    {
        $t = $this->tenant();
        $svc = new IntegrationConnectionService(new AdapterRegistry([]));
        $conn = $svc->connect($t->id, 'meta', 'production', ['status' => 'connected', 'access_token' => 'x', 'refresh_token' => 'y']);
        $svc->disconnect($conn);
        $fresh = $conn->fresh();
        $this->assertSame('disconnected', $fresh->status);
        $this->assertNull($fresh->access_token);
        $this->assertNotNull($fresh->disconnected_at);
    }

    public function test_external_object_map_is_unique_per_provider_object(): void
    {
        $t = $this->tenant();
        TenantContext::set($t->id, null);
        ExternalObjectMap::create(['tenant_id' => $t->id, 'provider' => 'tiktok', 'external_type' => 'account', 'external_id' => 'X1', 'local_type' => 'creator', 'local_id' => 5]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        ExternalObjectMap::create(['tenant_id' => $t->id, 'provider' => 'tiktok', 'external_type' => 'account', 'external_id' => 'X1', 'local_type' => 'creator', 'local_id' => 9]);
    }

    public function test_sync_job_skips_unconnected_connection(): void
    {
        $t = $this->tenant();
        $registry = new AdapterRegistry([$this->fakeAdapter('tiktok')]);
        $svc = new IntegrationConnectionService($registry);
        $conn = $svc->connect($t->id, 'tiktok', 'production', ['status' => 'disconnected']);

        (new SyncProviderJob($conn->id))->handle($svc);

        $count = TenantContext::withBypass(fn () => IntegrationSyncRun::where('connection_id', $conn->id)->count());
        $this->assertSame(0, $count, 'لا تشغيلة لاتّصال غير موصول');
    }
}
