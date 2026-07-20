<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Campaigns\Models\{Campaign, CampaignShortlist, CampaignShortlistVersion};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الترشيحات — مركز اختيار المؤثرين (React/Inertia). صفحة مركزية: اختر حملة → مساحة الترشيح.
 * تُظهر حالة الترشيح لكل حملة (مسودة/مُرسلة/قرار العميل). Policy(viewAny Campaign)، معزولة.
 */
class ShortlistingController extends Controller
{
    private const V_LABEL = ['draft' => 'مسودة', 'submitted' => 'بانتظار العميل', 'approved' => 'مُعتمَد', 'partially_approved' => 'اعتماد جزئي', 'rejected' => 'مرفوض'];
    private const V_TONE = ['draft' => 'draft', 'submitted' => 'submitted', 'approved' => 'approved', 'partially_approved' => 'under_review', 'rejected' => 'rejected'];

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $q = Campaign::query()->with('client', 'brand')->whereNotIn('status', ['cancelled'])->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'ilike', "%{$s}%")->orWhere('campaign_number', 'ilike', "%{$s}%")
                ->orWhereHas('client', fn ($c) => $c->where('display_name', 'ilike', "%{$s}%")));
        }
        $campaigns = $q->paginate(15)->withQueryString();

        // خرائط حالة الترشيح لكل حملة في الصفحة (بلا N+1 ثقيل)
        $ids = $campaigns->getCollection()->pluck('id');
        $shortlists = CampaignShortlist::whereIn('campaign_id', $ids)->get()->keyBy('campaign_id');
        $versions = CampaignShortlistVersion::whereIn('shortlist_id', $shortlists->pluck('id'))->get()->groupBy('shortlist_id');
        $pendingByVersion = [];
        $curVersionByShortlist = [];
        foreach ($shortlists as $sl) {
            $cur = ($versions[$sl->id] ?? collect())->firstWhere('version', $sl->current_version);
            if ($cur) {
                $curVersionByShortlist[$sl->id] = $cur;
                $pendingByVersion[$cur->id] = $cur->items()->where(fn ($q2) => $q2->whereNull('client_decision')->orWhere('client_decision', 'pending'))->count();
            }
        }

        $campaigns->through(function (Campaign $c) use ($shortlists, $curVersionByShortlist, $pendingByVersion) {
            $sl = $shortlists[$c->id] ?? null;
            $cur = $sl ? ($curVersionByShortlist[$sl->id] ?? null) : null;
            $status = $cur?->status;
            return [
                'id' => $c->id, 'name' => $c->name, 'number' => $c->campaign_number,
                'client' => $c->client?->display_name, 'brand' => $c->brand?->name,
                'budgetMinor' => (int) $c->budget_minor,
                'hasShortlist' => (bool) $sl,
                'version' => $cur ? (int) $cur->version : null,
                'slStatus' => $status,
                'slLabel' => $status ? (self::V_LABEL[$status] ?? $status) : 'لم يبدأ',
                'slTone' => $status ? (self::V_TONE[$status] ?? 'draft') : 'draft',
                'pending' => $cur ? (int) ($pendingByVersion[$cur->id] ?? 0) : 0,
            ];
        });

        return Inertia::render('Shortlisting/Index', [
            'campaigns' => $campaigns,
            'filters' => ['q' => $r->query('q')],
            'summary' => [
                'total' => (clone $q)->count(),
                'awaitingClient' => CampaignShortlistVersion::whereIn('status', ['submitted', 'partially_approved'])
                    ->whereHas('shortlist', fn ($s) => $s->whereIn('campaign_id', Campaign::query()->pluck('id')))->count(),
            ],
        ]);
    }
}
