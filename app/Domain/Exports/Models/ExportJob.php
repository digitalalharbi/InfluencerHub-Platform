<?php

namespace App\Domain\Exports\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** وظيفة تصدير — سجلّ تاريخ التصدير/التقارير مع ملفّ خاص وتنزيل آمن. */
class ExportJob extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'title', 'format', 'status', 'filters', 'row_count',
        'disk', 'path', 'size_bytes', 'error', 'scheduled_report_id', 'completed_at', 'expires_at',
    ];

    protected $casts = [
        'filters' => 'array', 'row_count' => 'integer', 'size_bytes' => 'integer',
        'completed_at' => 'datetime', 'expires_at' => 'datetime',
    ];

    public function isDownloadable(): bool
    {
        return $this->status === 'completed' && $this->path
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
