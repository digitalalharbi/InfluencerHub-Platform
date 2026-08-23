<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * اتّصال تكامل مُخزَّن لكل (مستأجر، مزوّد، بيئة). الرموز مُعمّاة في القاعدة
 * (encrypted cast) ولا تُسلسَل للواجهة. الحالة صريحة من مفردات محدّدة.
 */
class IntegrationConnection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'provider', 'environment', 'status', 'external_account_id', 'external_account_name',
        'scopes', 'access_token', 'refresh_token', 'token_expires_at', 'last_success_sync_at',
        'last_attempt_sync_at', 'next_sync_at', 'last_error', 'last_error_at', 'health', 'capabilities',
        'connected_by', 'connected_at', 'disconnected_at', 'meta',
    ];

    protected $casts = [
        'scopes' => 'array',
        'capabilities' => 'array',
        'meta' => 'array',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_success_sync_at' => 'datetime',
        'last_attempt_sync_at' => 'datetime',
        'next_sync_at' => 'datetime',
        'last_error_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    /** لا تُسلسَل الرموز أبدًا إلى JSON/الواجهة. */
    protected $hidden = ['access_token', 'refresh_token'];

    public const STATUSES = [
        'not_configured', 'waiting_for_credentials', 'waiting_for_approval', 'connecting',
        'connected', 'sandbox', 'limited', 'degraded', 'error', 'expired', 'disconnected',
    ];

    public const CONNECTED_STATUSES = ['connected', 'sandbox', 'limited'];

    public function syncRuns(): HasMany
    {
        return $this->hasMany(IntegrationSyncRun::class, 'connection_id');
    }

    public function isConnected(): bool
    {
        return in_array($this->status, self::CONNECTED_STATUSES, true);
    }

    public function tokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
