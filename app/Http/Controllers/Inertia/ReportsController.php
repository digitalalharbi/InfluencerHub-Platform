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
    public function index(AnalyticsService $analytics): Response
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
