<?php

namespace App\Domain\Exports\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** تقرير مجدول — يومي/أسبوعي/شهري، يُسلَّم داخل التطبيق (وبريد عند التفعيل). */
class ScheduledReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'report_type', 'name', 'format', 'filters', 'frequency',
        'timezone', 'delivery', 'enabled', 'last_run_at', 'next_run_at',
    ];

    protected $casts = [
        'filters' => 'array', 'enabled' => 'boolean',
        'last_run_at' => 'datetime', 'next_run_at' => 'datetime',
    ];

    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    /** يحسب موعد التشغيل التالي من التكرار. */
    public function computeNextRun(?\Illuminate\Support\Carbon $from = null): \Illuminate\Support\Carbon
    {
        $from ??= now();
        return match ($this->frequency) {
            'weekly' => $from->copy()->addWeek()->startOfDay()->addHours(6),
            'monthly' => $from->copy()->addMonthNoOverflow()->startOfMonth()->addHours(6),
            default => $from->copy()->addDay()->startOfDay()->addHours(6),
        };
    }
}
