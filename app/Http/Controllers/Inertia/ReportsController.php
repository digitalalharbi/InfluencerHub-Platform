<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\Client;
use App\Domain\Finance\Models\Payout;
use App\Http\Controllers\Controller;
use App\Support\Analytics\ClientAnalytics;
use Inertia\Inertia;
use Inertia\Response;

/**
 * التقارير والتحليلات (React/Inertia) — عرض فقط. تجميعات حقيقية من AnalyticsService + المالية.
 * Policy(viewAny Client) — نفس صلاحية عرض CRM. معزول بالمستأجر.
 */
class ReportsController extends Controller
{
    /** تصدير تقرير أداء العملاء (csv/xlsx/pdf) — من محرك التحليلات نفسه. */
    private const REPORT_TYPE = 'clients_report_pdf';
    private const REPORT_TEMPLATE = 'v1';

    /** جدول تقرير أداء العملاء — مصدر واحد للتصدير والمعاينة (بلا وقت في الصفوف). */
    private function reportTabular(): \App\Domain\Exports\TabularData
    {
        $clients = Client::query()->get();
        $metrics = ClientAnalytics::forPage($clients);
        $sar = fn (int $minor) => number_format($minor / 100, 0) . ' ر.س';
        $rows = $clients->map(fn (Client $c) => [
            'name' => $c->display_name,
            'active' => (int) ($metrics[$c->id]['active_campaigns'] ?? 0),
            'completion' => (int) ($metrics[$c->id]['completion'] ?? 0) . '%',
            'revenue' => $sar((int) ($metrics[$c->id]['revenue_minor'] ?? 0)),
        ])->sortByDesc(fn ($x) => $x['active'])->values();

        return new \App\Domain\Exports\TabularData(
            title: 'تقرير أداء العملاء',
            columns: ['name' => 'العميل', 'active' => 'حملات نشطة', 'completion' => 'اكتمال الملف', 'revenue' => 'الإيراد (مُحصَّل)'],
            rows: $rows,
            meta: ['الفترة' => now()->format('Y')],
            workspace: \App\Domain\Tenancy\Support\TenantContext::organizationId() ? \App\Domain\Tenancy\Models\Organization::find(\App\Domain\Tenancy\Support\TenantContext::organizationId())?->name : null,
            generatedAt: now()->format('Y-m-d H:i'),
        );
    }

    public function export(\Illuminate\Http\Request $r, \App\Domain\Exports\ExportService $svc)
    {
        $this->authorize('viewAny', Client::class);
        $data = $this->reportTabular();
        $count = is_countable($data->rows) ? count($data->rows) : 0;

        return $svc->download($data, (string) $r->query('format', 'xlsx'), 'clients-report-' . now()->format('Ymd'), 'clients_report', $count, \App\Domain\Tenancy\Support\TenantContext::tenantId(), $r->user()->id);
    }

    /** التنظيم (المستأجر) هو موضوع أثر التقرير المجمَّع. */
    private function reportSubject(): \App\Domain\Tenancy\Models\Organization
    {
        return \App\Domain\Tenancy\Models\Organization::findOrFail(\App\Domain\Tenancy\Support\TenantContext::organizationId());
    }

    private function reportArtifact(\Illuminate\Http\Request $r, \App\Domain\Exports\DocumentArtifactService $svc, \App\Domain\Exports\Writers\PdfWriter $pdf, bool $regenerate = false): \App\Domain\Exports\Models\ExportJob
    {
        $subject = $this->reportSubject();
        $tab = $this->reportTabular();
        $fpData = ['rows' => collect($tab->rows)->toArray()];   // البصمة من الصفوف لا الوقت
        $render = function () use ($tab, $pdf, $r) {
            \App\Domain\Audit\Services\AuditLogger::log('export.generated', null,
                ['type' => self::REPORT_TYPE, 'format' => 'pdf'], \App\Domain\Tenancy\Support\TenantContext::tenantId(), $r->user()?->id);
            return $pdf->render($tab);
        };
        if (! $regenerate) {
            $latest = $svc->latest(self::REPORT_TYPE, $subject);
            if ($latest) return $latest;
        }
        return $svc->current(self::REPORT_TYPE, $subject, 'pdf', self::REPORT_TEMPLATE, $fpData, 'تقرير أداء العملاء', $render, $r->user()?->id);
    }

    private function streamReport(\App\Domain\Exports\Models\ExportJob $a, \App\Domain\Exports\DocumentArtifactService $svc, string $disposition)
    {
        $bytes = $svc->bytes($a);
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . \App\Support\Brand::documentFilename("clients-report") . '"',
            'Content-Length' => (string) strlen($bytes), 'X-Artifact-Checksum' => $a->checksum,
        ]);
    }

    public function pdfPreview(\Illuminate\Http\Request $r, \App\Domain\Exports\DocumentArtifactService $svc, \App\Domain\Exports\Writers\PdfWriter $pdf)
    {
        $this->authorize('viewAny', Client::class);
        return $this->streamReport($this->reportArtifact($r, $svc, $pdf), $svc, 'inline');
    }

    public function pdfDownload(\Illuminate\Http\Request $r, \App\Domain\Exports\DocumentArtifactService $svc, \App\Domain\Exports\Writers\PdfWriter $pdf)
    {
        $this->authorize('viewAny', Client::class);
        return $this->streamReport($this->reportArtifact($r, $svc, $pdf), $svc, 'attachment');
    }

    public function pdfRegenerate(\Illuminate\Http\Request $r, \App\Domain\Exports\DocumentArtifactService $svc, \App\Domain\Exports\Writers\PdfWriter $pdf): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('viewAny', Client::class);
        $this->reportArtifact($r, $svc, $pdf, regenerate: true);
        return back()->with('ok', 'أُنشئت نسخة محدّثة من التقرير.');
    }

    /** بيانات مستند التقرير لصفحة التقارير — مسارات نسبية للتركيب. */
    public function reportDocMeta(\App\Domain\Exports\DocumentArtifactService $svc): array
    {
        $latest = $svc->latest(self::REPORT_TYPE, $this->reportSubject());
        $currentFp = $svc->fingerprint(['rows' => collect($this->reportTabular()->rows)->toArray()], self::REPORT_TEMPLATE, 'pdf');
        return [
            'title' => 'تقرير أداء العملاء',
            'hasArtifact' => (bool) $latest,
            'generatedAt' => $latest?->created_at?->format('Y-m-d H:i'),
            'stale' => $svc->isStale($latest, $currentFp),
            'previewUrl' => '/reports/pdf/preview', 'downloadUrl' => '/reports/pdf/download', 'regenerateUrl' => '/reports/pdf/regenerate',
        ];
    }

    public function index(AnalyticsService $analytics, \App\Domain\Exports\DocumentArtifactService $artifacts): Response
    {
        $this->authorize('viewAny', Client::class);
        $o = $analytics->agencyOverview();
        $op = ClientAnalytics::operational();
        $st = fn ($s) => __('statuses.' . $s);
        $tone = fn ($s) => __('statuses.tone.' . $s);

        // توزيعات مُعنونة (label/tone/count) للعرض كأشرطة
        $breakdown = function (array $byStatus) use ($st, $tone) {
            $out = [];
            arsort($byStatus);
            foreach ($byStatus as $k => $v) $out[] = ['label' => $st($k), 'tone' => $tone($k), 'count' => (int) $v];
            return $out;
        };

        // سلسلة زمنية حقيقية — آخر 6 أشهر (بلا تقديرات)
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $paidByMonth = Payout::where('status', 'paid')->whereNotNull('paid_at')
            ->where('paid_at', '>=', $months->first())->get()
            ->groupBy(fn ($p) => $p->paid_at->format('Y-m'))->map(fn ($g) => (int) $g->sum('amount_minor'));
        $campaignsByMonth = Campaign::where('created_at', '>=', $months->first())->get()
            ->groupBy(fn ($c) => $c->created_at->format('Y-m'));
        $publishedByMonth = ContentItem::where('status', 'published')->whereNotNull('published_at')
            ->where('published_at', '>=', $months->first())->get()
            ->groupBy(fn ($c) => $c->published_at->format('Y-m'))->map->count();

        $AR_MONTHS = ['01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل', '05' => 'مايو', '06' => 'يونيو',
            '07' => 'يوليو', '08' => 'أغسطس', '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'];
        $timeline = $months->map(function ($m) use ($paidByMonth, $campaignsByMonth, $publishedByMonth, $AR_MONTHS) {
            $key = $m->format('Y-m');
            return [
                'key' => $key,
                'label' => $AR_MONTHS[$m->format('m')],
                'paidMinor' => (int) ($paidByMonth[$key] ?? 0),
                'budgetMinor' => (int) (($campaignsByMonth[$key] ?? collect())->sum('budget_minor')),
                'campaigns' => ($campaignsByMonth[$key] ?? collect())->count(),
                'published' => (int) ($publishedByMonth[$key] ?? 0),
            ];
        })->values();

        // أبرز العملاء بالإيراد (من نفس محرك تحليلات العميل)
        $allClients = Client::query()->get();
        $clientMetrics = ClientAnalytics::forPage($allClients);
        $topClients = $allClients->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->display_name,
            'revenueMinor' => (int) ($clientMetrics[$c->id]['revenue_minor'] ?? 0),
            'campaigns' => (int) ($clientMetrics[$c->id]['active_campaigns'] ?? 0),
        ])->filter(fn ($r) => $r['revenueMinor'] > 0)->sortByDesc('revenueMinor')->take(6)->values();

        return Inertia::render('Reports/Index', [
            'documents' => ['report' => $this->reportDocMeta($artifacts)],
            'timeline' => $timeline,
            'topClients' => $topClients,
            // كل حدّ من FinancialMetrics — لا يُعاد حسابه هنا بتعريف ثانٍ
            'financial' => [
                'revenueMinor' => (int) $op['revenue_minor'],
                'taxMinor' => (int) $op['tax_minor'],
                'billedMinor' => (int) $op['billed_minor'],
                'collectedMinor' => (int) $op['collected_minor'],
                'outstandingMinor' => (int) $op['outstanding_minor'],
                'costMinor' => (int) $op['cost_minor'],
                'costPaidMinor' => (int) $op['cost_paid_minor'],
                'profitMinor' => (int) $op['profit_minor'],
                'margin' => (float) $op['margin'],
                'openPayoutMinor' => (int) ($o['payouts']['open_minor'] ?? 0),
                'activeContractValueMinor' => (int) ($o['contracts']['value_active_minor'] ?? 0),
            ],
            'kpis' => [
                'clients' => (int) $o['clients']['total'], 'clientsActive' => (int) $o['clients']['active'],
                'creators' => (int) $o['creators']['total'], 'creatorsActive' => (int) $o['creators']['active'],
                'campaigns' => (int) $o['campaigns']['total'], 'campaignsActive' => (int) $o['campaigns']['active'],
                'campaignsBudgetMinor' => (int) $o['campaigns']['budget_minor'],
                'requestsOpen' => (int) $o['requests']['open'], 'requestsOverdue' => (int) $o['requests']['overdue'],
                'contentPublished' => (int) $o['content']['published'], 'contentAwaiting' => (int) $o['content']['awaiting'],
                'collaborations' => (int) $o['collaborations']['total'],
            ],
            'breakdowns' => [
                'campaigns' => $breakdown($o['campaigns']['by_status']),
                'requests' => $breakdown($o['requests']['by_status']),
                'content' => $breakdown($o['content']['by_status']),
                'collaborations' => $breakdown($o['collaborations']['by_status']),
            ],
            // التسميات من مصدر القدرات نفسه — لا خريطة ثالثة تتخلّف عن الأولى
            'creatorsByType' => collect($o['creators']['by_capability'])->map(fn ($v, $k) => [
                'label' => \App\Domain\Creators\Models\CreatorCapability::label($k), 'count' => (int) $v,
            ])->values(),
        ]);
    }
}
