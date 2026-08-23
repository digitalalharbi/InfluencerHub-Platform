<?php

namespace App\Console\Commands;

use App\Domain\Communications\Services\NotificationService;
use App\Domain\Exports\ExportService;
use App\Domain\Exports\Models\{ExportJob, ScheduledReport};
use App\Domain\Exports\ReportGenerator;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * يشغّل التقارير المجدولة المستحقّة: يولّد الملفّ، يخزّنه على قرص خاص، يسجّل وظيفة
 * تصدير مكتملة، ويُشعر المالك برابط تنزيل آمن — ثم يحسب الموعد التالي.
 * يُدار عبر المجدول (كل ساعة). التوليد الثقيل خارج طلب المتصفّح.
 */
class RunScheduledReportsCommand extends Command
{
    protected $signature = 'reports:run-scheduled';
    protected $description = 'يشغّل التقارير المجدولة المستحقّة';

    public function handle(ReportGenerator $gen, ExportService $exports, NotificationService $notify): int
    {
        $due = TenantContext::withBypass(fn () => ScheduledReport::where('enabled', true)
            ->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()))->get());

        foreach ($due as $report) {
            $this->runOne($report, $gen, $exports, $notify);
        }
        $this->info($due->count() . ' scheduled report(s) processed');

        return self::SUCCESS;
    }

    public function runOne(ScheduledReport $report, ReportGenerator $gen, ExportService $exports, NotificationService $notify): ExportJob
    {
        return TenantContext::withTenant($report->tenant_id, function () use ($report, $gen, $exports, $notify) {
            $data = $gen->generate($report->report_type, $report->filters ?? [], $report->tenant_id);
            $rows = is_countable($data->rows) ? count($data->rows) : null;
            $file = $exports->toFile($data, $report->format, 'scheduled-' . $report->id . '-' . now()->format('Ymd'));

            $job = ExportJob::create([
                'tenant_id' => $report->tenant_id, 'user_id' => $report->user_id,
                'type' => $report->report_type, 'title' => $report->name, 'format' => $report->format,
                'status' => 'completed', 'filters' => $report->filters, 'row_count' => $rows,
                'disk' => $file['disk'], 'path' => $file['path'], 'size_bytes' => $file['size'],
                'scheduled_report_id' => $report->id, 'completed_at' => now(),
                'expires_at' => now()->addDays(14),
            ]);

            $notify->notify(
                $report->tenant_id, $report->user_id, 'report.ready', 'system',
                'تقريرك المجدول جاهز', $report->name,
                "/app/exports/{$job->id}/download", ['export_job_id' => $job->id], $job,
            );

            $report->update(['last_run_at' => now(), 'next_run_at' => $report->computeNextRun()]);

            return $job;
        });
    }
}
