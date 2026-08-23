<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Exports\Models\{ExportJob, ScheduledReport};
use App\Domain\Exports\ReportGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * مركز التصدير (React/Inertia) — تقارير مجدولة (يومي/أسبوعي/شهري) + سجلّ تنزيلات
 * التصدير. كلّ استعلام مقيّد بـuser_id للمستخدم الحالي (لا IDOR)، والتنزيل يتحقّق
 * من ملكية الملفّ ثم يبثّه من قرص خاص — لا رابط عام متوقّع.
 */
class ExportsController extends Controller
{
    private const FMT = ['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'];
    private const FREQ = ['daily' => 'يومي', 'weekly' => 'أسبوعي', 'monthly' => 'شهري'];
    private const STATUS = [
        'queued' => 'بالطابور', 'processing' => 'قيد التوليد', 'completed' => 'جاهز',
        'failed' => 'فشل', 'expired' => 'منتهٍ',
    ];
    private const STATUS_TONE = [
        'queued' => 'submitted', 'processing' => 'under_review', 'completed' => 'completed',
        'failed' => 'changes_requested', 'expired' => 'draft',
    ];

    public function index(Request $r): Response
    {
        $uid = $r->user()->id;

        $schedules = ScheduledReport::where('user_id', $uid)->latest()->get()->map(fn (ScheduledReport $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'reportType' => ReportGenerator::TYPES[$s->report_type] ?? $s->report_type,
            'format' => self::FMT[$s->format] ?? strtoupper($s->format),
            'frequency' => self::FREQ[$s->frequency] ?? $s->frequency,
            'delivery' => $s->delivery === 'email' ? 'بريد إلكتروني' : 'داخل التطبيق',
            'enabled' => (bool) $s->enabled,
            'lastRun' => $s->last_run_at?->format('Y-m-d H:i'),
            'nextRun' => $s->next_run_at?->format('Y-m-d H:i'),
        ]);

        $history = ExportJob::where('user_id', $uid)->latest()->limit(50)->get()->map(fn (ExportJob $j) => [
            'id' => $j->id,
            'title' => $j->title,
            'format' => self::FMT[$j->format] ?? strtoupper($j->format),
            'status' => self::STATUS[$j->status] ?? $j->status,
            'tone' => self::STATUS_TONE[$j->status] ?? 'draft',
            'rows' => $j->row_count,
            'size' => $j->size_bytes ? $this->humanSize($j->size_bytes) : null,
            'createdAt' => $j->created_at?->format('Y-m-d H:i'),
            'expiresAt' => $j->expires_at?->format('Y-m-d'),
            'downloadable' => $j->isDownloadable(),
            'downloadUrl' => $j->isDownloadable() ? "/app/exports/{$j->id}/download" : null,
            'scheduled' => (bool) $j->scheduled_report_id,
        ]);

        return Inertia::render('Exports/Index', [
            'schedules' => $schedules,
            'history' => $history,
            'reportTypes' => collect(ReportGenerator::TYPES)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
            'frequencies' => collect(self::FREQ)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
            'formats' => collect(self::FMT)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
        ]);
    }

    public function storeSchedule(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'name' => ['required', 'string', 'max:160'],
            'report_type' => ['required', 'string', 'in:' . implode(',', array_keys(ReportGenerator::TYPES))],
            'format' => ['required', 'string', 'in:csv,xlsx,pdf'],
            'frequency' => ['required', 'string', 'in:' . implode(',', ScheduledReport::FREQUENCIES)],
            'delivery' => ['nullable', 'string', 'in:in_app,email'],
        ]);

        $report = new ScheduledReport([
            'tenant_id' => \App\Domain\Tenancy\Support\TenantContext::tenantId(),
            'user_id' => $r->user()->id,
            'report_type' => $data['report_type'],
            'name' => $data['name'],
            'format' => $data['format'],
            'frequency' => $data['frequency'],
            'delivery' => $data['delivery'] ?? 'in_app',
            'enabled' => true,
        ]);
        $report->next_run_at = $report->computeNextRun();
        $report->save();

        return back()->with('ok', 'أُنشئ التقرير المجدول.');
    }

    public function toggleSchedule(Request $r, ScheduledReport $scheduledReport): RedirectResponse
    {
        abort_unless($scheduledReport->user_id === $r->user()->id, 403);
        $scheduledReport->enabled = ! $scheduledReport->enabled;
        if ($scheduledReport->enabled && ! $scheduledReport->next_run_at) {
            $scheduledReport->next_run_at = $scheduledReport->computeNextRun();
        }
        $scheduledReport->save();

        return back()->with('ok', $scheduledReport->enabled ? 'فُعِّل التقرير.' : 'أُوقف التقرير.');
    }

    public function destroySchedule(Request $r, ScheduledReport $scheduledReport): RedirectResponse
    {
        abort_unless($scheduledReport->user_id === $r->user()->id, 403);
        $scheduledReport->delete();

        return back()->with('ok', 'حُذف التقرير المجدول.');
    }

    /** تنزيل آمن: ملكية الملفّ + صلاحيّته، ثم بثّ من قرص خاص. */
    public function download(Request $r, ExportJob $exportJob): StreamedResponse
    {
        abort_unless($exportJob->user_id === $r->user()->id, 403);
        abort_unless($exportJob->isDownloadable(), 410, 'انتهت صلاحية هذا الملفّ أو لم يعد متاحًا.');

        $disk = Storage::disk($exportJob->disk ?: 'local');
        abort_unless($exportJob->path && $disk->exists($exportJob->path), 404);

        $ext = pathinfo($exportJob->path, PATHINFO_EXTENSION) ?: $exportJob->format;
        $name = \Illuminate\Support\Str::slug($exportJob->title) . '.' . $ext;

        return $disk->download($exportJob->path, $name);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';

        return round($bytes / 1048576, 1) . ' MB';
    }
}
