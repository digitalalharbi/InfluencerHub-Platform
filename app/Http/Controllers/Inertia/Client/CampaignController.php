<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlist;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Nomination\Support\ClientNominationView;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * حملات العميل (React/Inertia) — عرض فقط + قرار الترشيح. معزول على العميل النشِط.
 * يعيد استخدام ShortlistService::clientDecision. العميل لا يرى المسودات.
 */
class CampaignController extends Controller
{
    public function index(Request $r): Response
    {
        $c = $r->attributes->get('activeClient');
        $items = Campaign::withCount('deliverables')->where('client_id', $c->id)->whereNotIn('status', ['draft'])
            ->latest()->paginate(15)->through(fn (Campaign $cm) => [
                'id' => $cm->id, 'name' => $cm->name, 'number' => $cm->campaign_number,
                'status' => $cm->status, 'statusLabel' => __("statuses.{$cm->status}"), 'statusTone' => __("statuses.tone.{$cm->status}"),
                'deliverables' => (int) $cm->deliverables_count, 'budgetMinor' => (int) $cm->budget_minor,
                'startDate' => $cm->start_date?->format('Y-m-d'), 'endDate' => $cm->end_date?->format('Y-m-d'),
            ]);

        return Inertia::render('ClientPortal/Campaigns/Index', [
            'clientName' => $c->display_name,
            'items' => $items,
        ]);
    }

    public function show(Request $r, int $campaign): Response
    {
        $c = $r->attributes->get('activeClient');
        $cm = Campaign::where('id', $campaign)->where('client_id', $c->id)->whereNotIn('status', ['draft'])
            ->with('deliverables.creator', 'brand')->first();
        abort_unless($cm, 404);

        $sl = CampaignShortlist::where('campaign_id', $cm->id)->first();
        $slVersion = $sl?->versions()->where('status', '!=', 'draft')->orderByDesc('version')->first();
        $pendingDecisions = $slVersion
            ? $slVersion->items()->where(fn ($q) => $q->whereNull('client_decision')->orWhere('client_decision', 'pending'))->count()
            : 0;

        $deliverables = $cm->deliverables->map(fn ($d) => [
            'id' => $d->id, 'type' => $d->type, 'platform' => $d->platform, 'quantity' => (int) $d->quantity,
            'creator' => $d->creator?->display_name,
            'status' => $d->status, 'statusLabel' => __("statuses.{$d->status}"), 'statusTone' => __("statuses.tone.{$d->status}"),
        ])->values();

        return Inertia::render('ClientPortal/Campaigns/Show', [
            'clientName' => $c->display_name,
            'campaign' => [
                'id' => $cm->id, 'name' => $cm->name, 'number' => $cm->campaign_number,
                'brand' => $cm->brand?->name, 'objective' => $cm->objective,
                'status' => $cm->status, 'statusLabel' => __("statuses.{$cm->status}"), 'statusTone' => __("statuses.tone.{$cm->status}"),
                'budgetMinor' => (int) $cm->budget_minor, 'currency' => $cm->currency,
                'startDate' => $cm->start_date?->format('Y-m-d'), 'endDate' => $cm->end_date?->format('Y-m-d'),
            ],
            'deliverables' => $deliverables,
            'shortlist' => $slVersion ? [
                'version' => (int) $slVersion->version, 'status' => $slVersion->status,
                'pending' => $pendingDecisions, 'link' => "/campaigns/{$cm->id}/shortlist",
            ] : null,
        ]);
    }

    public function shortlist(Request $r, int $campaign): Response
    {
        $c = $r->attributes->get('activeClient');
        $cm = Campaign::where('id', $campaign)->where('client_id', $c->id)->whereNotIn('status', ['draft'])->first();
        abort_unless($cm, 404);
        $sl = CampaignShortlist::where('campaign_id', $cm->id)->first();
        $version = $sl?->versions()->where('status', '!=', 'draft')->orderByDesc('version')->first();
        // الإسقاط الآمن للعميل من مصدر واحد (لا تكلفة/هامش/مستحقّات — تحصيل العميل ≠ مستحقّات المبدع).
        $items = $version
            ? collect(ClientNominationView::items($version->items()->with('creator')->get()))
            : collect();

        return Inertia::render('ClientPortal/Campaigns/Shortlist', [
            'clientName' => $c->display_name,
            'campaign' => ['id' => $cm->id, 'name' => $cm->name, 'number' => $cm->campaign_number],
            'version' => $version ? ['version' => (int) $version->version, 'status' => $version->status] : null,
            'items' => $items,
        ]);
    }

    public function shortlistDecision(Request $r, int $campaign, int $item, ShortlistService $svc)
    {
        $c = $r->attributes->get('activeClient');
        $data = $r->validate(['decision' => 'required|in:approved,rejected,needs_alternative', 'reason' => 'nullable|string|max:500']);
        $cm = Campaign::where('id', $campaign)->where('client_id', $c->id)->first();
        abort_unless($cm, 404);
        $it = CampaignShortlistItem::whereKey($item)
            ->whereHas('version.shortlist', fn ($q) => $q->where('campaign_id', $cm->id))->first();
        abort_unless($it && $it->version->status !== 'draft', 404);
        $svc->clientDecision($it, $data['decision'], $data['reason'] ?? null);

        $msg = match ($data['decision']) {
            'approved' => 'اعتمدت المؤثر.',
            'needs_alternative' => 'طلبت بديلًا — سيقترح الفريق مرشّحًا آخر.',
            default => 'رفضت المؤثر.',
        };

        return back()->with('ok', $msg);
    }
}
