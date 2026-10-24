<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Finance\Models\Payout;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Analytics\CampaignAnalytics;
use App\Support\Analytics\ClientAnalytics;
use App\Support\Analytics\CreatorAnalytics;
use App\Support\Dashboard\OperationalDashboard;
use App\Support\Onboarding\WorkspaceSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * لوحة تشغيل الوكالة (React/Inertia) — قائمة على الدور والصلاحيات:
 * الجميع يرى "مساحة عملي" و"المطلوب مني الآن"؛ المؤشرات المالية ولقطة الفريق والنظرة العامة
 * تُرسَل فقط لمن يملك صلاحية الإدارة/المالية (deny-by-default؛ لا إخفاء CSS — البيانات لا تُرسَل أصلًا).
 */
class AgencyDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $orgId = (int) TenantContext::organizationId();
        $dash = (new OperationalDashboard($user, $orgId))->compose();

        $payload = [
            'role' => $dash['role'],
            'canSeeTeam' => $dash['canSeeTeam'],
            'brief' => $dash['brief'],
            'myWork' => $dash['myWork'],
            'team' => $dash['team'],
            // تهيئة المساحة: مساحة فارغة تحتاج خطوة تالية لا رسالة اطمئنان
            'setup' => WorkspaceSetup::for(
                (int) TenantContext::tenantId(), $orgId,
            ),
        ];

        // النظرة العامة (مالية/تشغيلية) للمديرين فقط
        if ($dash['canSeeTeam']) {
            $payload['overview'] = $this->managerOverview();
        }

        return Inertia::render('Dashboard', $payload);
    }

    /** مؤشرات ونظرة عامة للمدير — نفس analytics المُجمّعة (بلا N+1). */
    private function managerOverview(): array
    {
        $op = ClientAnalytics::operational();
        $campaignsSummary = CampaignAnalytics::summary();
        $creatorsSummary = CreatorAnalytics::summary(null);
        $pendingPayoutMinor = (int) Payout::whereIn('status', Payout::OPEN)->sum('amount_minor');

        // نفس المجموعة والمقاييس المحسوبة داخل operational() — لا تُحسب مرّتين
        $clients = $op['clients'];
        $clientMetrics = $op['client_metrics'];
        $topClients = $clients->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->display_name,
            'revenueMinor' => (int) ($clientMetrics[$c->id]['revenue_minor'] ?? 0),
            'activeCampaigns' => (int) ($clientMetrics[$c->id]['active_campaigns'] ?? 0),
            'brands' => (int) ($clientMetrics[$c->id]['brands'] ?? 0),
            'isVip' => (bool) ($clientMetrics[$c->id]['is_vip'] ?? false),
        ])->sortByDesc('revenueMinor')->take(6)->values();

        $campaignsCol = Campaign::query()
            ->whereIn('status', ['active', 'planning', 'paused'])
            ->with(['client', 'brand'])->latest()->limit(6)->get();
        $cMetrics = CampaignAnalytics::forPage($campaignsCol);
        $activeCampaigns = $campaignsCol->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'client' => $c->client?->display_name,
            'brand' => $c->brand?->name,
            'statusTone' => __('statuses.tone.'.$c->status),
            'statusLabel' => __('statuses.'.$c->status),
            'progress' => (int) ($cMetrics[$c->id]['progress'] ?? 0),
            'creators' => (int) ($cMetrics[$c->id]['creators'] ?? 0),
            'deliverables' => (int) ($cMetrics[$c->id]['deliverables'] ?? 0),
            'isLate' => (bool) ($cMetrics[$c->id]['is_late'] ?? false),
        ])->values();

        // اتجاه النشاط التشغيلي — 8 أسابيع حقيقية (محتوى + تعاونات أُنشئت)، مُنطّق بالمستأجر.
        // التجميع في PostgreSQL عبر date_trunc('week') — يعيد الدلاء الثمانية فقط بدل سحب
        // كل الصفوف إلى PHP. created_at = timestamp بلا منطقة (UTC)، وapp.timezone=UTC،
        // فحدّ الأسبوع يطابق Carbon::startOfWeek (الاثنين UTC) تمامًا.
        $since = Carbon::now()->startOfWeek()->subWeeks(7);
        $weekly = function (string $model) use ($since): array {
            return $model::query()->where('created_at', '>=', $since)
                ->selectRaw("to_char(date_trunc('week', created_at), 'YYYY-MM-DD') as wk, count(*) as c")
                ->groupBy('wk')->pluck('c', 'wk')->all();
        };
        $contentW = $weekly(ContentItem::class);
        $collabW = $weekly(Collaboration::class);
        $trend = [];
        for ($i = 7; $i >= 0; $i--) {
            $ws = Carbon::now()->startOfWeek()->subWeeks($i);
            $key = $ws->format('Y-m-d');
            $trend[] = [
                'label' => $ws->isoFormat('D/M'),
                'value' => (int) ($contentW[$key] ?? 0) + (int) ($collabW[$key] ?? 0),
                'content' => (int) ($contentW[$key] ?? 0),
                'collabs' => (int) ($collabW[$key] ?? 0),
            ];
        }

        return [
            'trend' => $trend,
            'kpis' => [
                'clientsTotal' => $clients->count(),
                'revenueMinor' => (int) ($op['revenue_minor'] ?? 0),
                'profitMinor' => (int) ($op['profit_minor'] ?? 0),
                'margin' => ($op['revenue_minor'] ?? 0) > 0 ? (int) round((($op['profit_minor'] ?? 0) / $op['revenue_minor']) * 100) : 0,
                'activeCampaigns' => (int) ($op['active_campaigns'] ?? 0),
                'awaitingClient' => (int) ($campaignsSummary['awaiting_client'] ?? 0),
                'late' => (int) ($campaignsSummary['late'] ?? 0),
                'campaignsActive' => (int) ($campaignsSummary['active'] ?? 0),
                'pendingPayoutMinor' => $pendingPayoutMinor,
                'pendingPayouts' => (int) ($op['pending_payouts'] ?? 0),
                'creatorsTotal' => (int) ($creatorsSummary['total'] ?? 0),
                'creatorsVerified' => (int) ($creatorsSummary['verified'] ?? 0),
                'creatorsTierA' => (int) ($creatorsSummary['tier_a'] ?? 0),
                'avgCompletion' => (int) ($op['avg_completion'] ?? 0),
            ],
            'topClients' => $topClients,
            'activeCampaigns' => $activeCampaigns,
        ];
    }
}
