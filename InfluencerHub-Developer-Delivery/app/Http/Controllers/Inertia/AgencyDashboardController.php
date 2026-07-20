<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\Client;
use App\Domain\Finance\Models\Payout;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Analytics\{CampaignAnalytics, ClientAnalytics, CreatorAnalytics};
use App\Support\Dashboard\OperationalDashboard;
use Illuminate\Http\Request;
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
            'setup' => \App\Support\Onboarding\WorkspaceSetup::for(
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
            'statusTone' => __('statuses.tone.' . $c->status),
            'statusLabel' => __('statuses.' . $c->status),
            'progress' => (int) ($cMetrics[$c->id]['progress'] ?? 0),
            'creators' => (int) ($cMetrics[$c->id]['creators'] ?? 0),
            'deliverables' => (int) ($cMetrics[$c->id]['deliverables'] ?? 0),
            'isLate' => (bool) ($cMetrics[$c->id]['is_late'] ?? false),
        ])->values();

        return [
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
