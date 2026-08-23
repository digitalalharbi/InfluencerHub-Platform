<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Integrations\Adapters\{AdapterRegistry, SyncResult};
use App\Domain\Integrations\Models\{IntegrationConnection, IntegrationSyncRun};
use App\Domain\Tenancy\Support\TenantContext;
use Throwable;

/**
 * إدارة اتّصالات التكامل: ربط/فصل، وتنفيذ تشغيلة مزامنة عبر المحوّل مع تسجيل
 * كامل للعدّادات والصحّة والخطأ الآمن. الرموز لا تُسجَّل ولا تُدقَّق.
 */
class IntegrationConnectionService
{
    public function __construct(private AdapterRegistry $adapters) {}

    /** ينشئ/يحدّث اتّصالًا بحالة صريحة (لا يُخترع «connected» بلا رمز حقيقي). */
    public function connect(int $tenantId, string $provider, string $environment, array $attrs, ?int $userId = null): IntegrationConnection
    {
        return TenantContext::withTenant($tenantId, function () use ($tenantId, $provider, $environment, $attrs, $userId) {
            $conn = IntegrationConnection::updateOrCreate(
                ['tenant_id' => $tenantId, 'provider' => $provider, 'environment' => $environment],
                array_merge($attrs, [
                    'connected_by' => $userId,
                    'connected_at' => now(),
                    'disconnected_at' => null,
                ]),
            );
            AuditLogger::log('integration.connected', $conn, ['provider' => $provider, 'env' => $environment, 'status' => $conn->status], $tenantId, $userId);

            return $conn;
        });
    }

    public function disconnect(IntegrationConnection $conn, ?int $userId = null): IntegrationConnection
    {
        return TenantContext::withTenant($conn->tenant_id, function () use ($conn, $userId) {
            $conn->update([
                'status' => 'disconnected', 'health' => 'unknown',
                'access_token' => null, 'refresh_token' => null, 'token_expires_at' => null,
                'disconnected_at' => now(),
            ]);
            AuditLogger::log('integration.disconnected', $conn, ['provider' => $conn->provider], $conn->tenant_id, $userId);

            return $conn;
        });
    }

    /**
     * ينفّذ تشغيلة مزامنة متزامِنة (يستدعيها الـJob). ينشئ SyncRun، يشغّل المحوّل،
     * يسجّل النتيجة والصحّة والمؤشّر، ويعالج الفشل بأمان (يُعيد رميه للطابور ليُعيد المحاولة).
     */
    public function runSync(IntegrationConnection $conn, string $type = 'manual', ?string $capability = null, int $retryCount = 0): IntegrationSyncRun
    {
        return TenantContext::withTenant($conn->tenant_id, function () use ($conn, $type, $capability, $retryCount) {
            $run = IntegrationSyncRun::create([
                'tenant_id' => $conn->tenant_id, 'connection_id' => $conn->id, 'provider' => $conn->provider,
                'capability' => $capability, 'type' => $type, 'status' => 'running',
                'started_at' => now(), 'retry_count' => $retryCount, 'cursor' => $conn->meta['cursor'] ?? null,
            ]);
            $conn->update(['last_attempt_sync_at' => now()]);

            try {
                $adapter = $this->adapters->get($conn->provider);
                /** @var SyncResult $res */
                $res = $adapter->sync($conn, $run);

                $run->update([
                    'status' => $res->partial ? 'partial' : 'success',
                    'completed_at' => now(),
                    'fetched' => $res->fetched, 'created' => $res->created, 'updated' => $res->updated,
                    'skipped' => $res->skipped, 'failed' => $res->failed,
                    'cursor' => $res->cursor, 'rate_limit_remaining' => $res->rateLimitRemaining,
                ]);
                $conn->update([
                    'last_success_sync_at' => now(),
                    'health' => $res->partial ? 'degraded' : 'healthy',
                    'last_error' => null, 'last_error_at' => null,
                ]);

                return $run;
            } catch (Throwable $e) {
                $run->update(['status' => 'failed', 'completed_at' => now(), 'error' => $this->safeError($e)]);
                $conn->update(['health' => 'error', 'last_error' => $this->safeError($e), 'last_error_at' => now()]);
                throw $e; // يُعاد للطابور ليُعيد المحاولة ضمن حدّ محدّد
            }
        });
    }

    /** رسالة خطأ مختصرة آمنة (بلا أسرار/رموز). */
    private function safeError(Throwable $e): string
    {
        return mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 290);
    }
}
