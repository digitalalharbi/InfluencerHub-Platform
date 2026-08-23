<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تشغيلة مزامنة — سجلّ كامل: النوع، الحالة، المؤشّر (cursor)، عدّادات
 * (fetched/created/updated/skipped/failed)، حدود المعدّل، إعادة المحاولة، خطأ آمن.
 */
class IntegrationSyncRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'connection_id', 'provider', 'capability', 'type', 'status', 'cursor',
        'started_at', 'completed_at', 'fetched', 'created', 'updated', 'skipped', 'failed',
        'rate_limit_remaining', 'retry_count', 'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'fetched' => 'integer', 'created' => 'integer', 'updated' => 'integer',
        'skipped' => 'integer', 'failed' => 'integer', 'rate_limit_remaining' => 'integer',
        'retry_count' => 'integer',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }
}
