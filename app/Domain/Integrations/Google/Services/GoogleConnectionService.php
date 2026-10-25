<?php

namespace App\Domain\Integrations\Google\Services;

use App\Domain\Integrations\Google\GoogleWorkspaceAdapter;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Services\OAuthFlowService;
use RuntimeException;

/**
 * يحلّ اتّصال Google للمستأجر، يضمن رمزًا صالحًا (تحديث تلقائيّ قبل الانتهاء)، ويتحقّق من
 * منح القدرة. لا قدرة ممنوحة ⇒ رفض صريح (لا عملية صامتة). رمز وحده لا يُعدّ قدرةً ممنوحة.
 */
class GoogleConnectionService
{
    public function __construct(private OAuthFlowService $oauth) {}

    public function forTenant(int $tenantId): ?IntegrationConnection
    {
        return IntegrationConnection::query()
            ->where('tenant_id', $tenantId)
            ->where('provider', GoogleWorkspaceAdapter::PROVIDER)
            ->where('environment', 'production')
            ->first();
    }

    /** يُعيد الاتّصال برمز صالح — يحدّثه إن انتهى ووُجد refresh_token. */
    public function ensureFresh(IntegrationConnection $conn): IntegrationConnection
    {
        if (! $conn->isConnected()) {
            throw new RuntimeException('google_not_connected');
        }
        if ($conn->tokenExpired() && ! empty($conn->refresh_token)) {
            return $this->oauth->refresh($conn);
        }
        if (empty($conn->access_token)) {
            throw new RuntimeException('google_no_access_token');
        }

        return $conn;
    }

    public function hasCapability(IntegrationConnection $conn, string $capability): bool
    {
        return ($conn->capabilities[$capability] ?? null) === 'connected';
    }

    public function assertCapability(IntegrationConnection $conn, string $capability): void
    {
        if (! $this->hasCapability($conn, $capability)) {
            throw new RuntimeException("google_capability_not_granted:{$capability}");
        }
    }
}
