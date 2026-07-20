<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\CampaignWorkflowService;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use App\Support\Analytics\CampaignAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * قائمة الحملات (React/Inertia) — نفس منطق CampaignController@index وبياناته الحقيقية.
 * لوحة بطاقات (شكل مناسب للحملات). Policy(viewAny)، معزولة بالمستأجر.
 */
class CampaignsController extends Controller
{
    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $q = Campaign::query()->with('client', 'brand', 'creator')->withCount('deliverables')->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'ilike', "%{$s}%")->orWhere('campaign_number', 'ilike', "%{$s}%")
                ->orWhereHas('client', fn ($c) => $c->where('display_name', 'ilike', "%{$s}%")));
        }
        if ($s = $r->query('status')) $q->where('status', $s);
        CampaignAnalytics::applySegment($q, $r->query('seg'));

        $campaigns = $q->paginate(12)->withQueryString();
        $metrics = CampaignAnalytics::forPage($campaigns->getCollection());

        $campaigns->through(fn (Campaign $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'client' => $c->client?->display_name,
            'brand' => $c->brand?->name,
            'status' => $c->status,
            'statusLabel' => __('statuses.' . $c->status),
            'statusTone' => __('statuses.tone.' . $c->status),
            'budgetMinor' => (int) $c->budget_minor,
            'endDate' => $c->end_date?->format('Y-m-d'),
            'progress' => (int) ($metrics[$c->id]['progress'] ?? 0),
            'creators' => (int) ($metrics[$c->id]['creators'] ?? 0),
            'deliverables' => (int) ($metrics[$c->id]['deliverables'] ?? 0),
            'platforms' => array_values($metrics[$c->id]['platforms'] ?? []),
            'isLate' => (bool) ($metrics[$c->id]['is_late'] ?? false),
            'awaitingClient' => (int) ($metrics[$c->id]['awaiting_client'] ?? 0),
            'startDate' => $c->start_date?->format('Y-m-d'),
            'stage' => in_array($c->status, ['draft', 'planning'], true) ? 'planning'
                : (in_array($c->status, ['completed', 'cancelled'], true) ? 'closed' : 'running'),
        ]);

        $canCreate = $r->user()->can('create', Campaign::class);

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'summary' => CampaignAnalytics::summary(),
            'filters' => $r->only('q', 'status', 'seg'),
            'canCreate' => $canCreate,
            'clients' => $canCreate
                ? Client::query()->whereNotIn('status', ['archived'])->with(['brands:id,client_id,name'])->orderBy('display_name')
                    ->get(['id', 'display_name'])
                    ->map(fn (Client $c) => [
                        'id' => $c->id, 'name' => $c->display_name,
                        'brands' => $c->brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
                    ])->values()
                : [],
        ]);
    }

    public function store(Request $r, CampaignWorkflowService $wf)
    {
        $this->authorize('create', Campaign::class);
        $data = $r->validate([
            'client_id' => 'required|integer',
            'brand_id' => 'nullable|integer',
            'name' => 'required|string|max:160',
            'objective' => 'nullable|string|max:2000',
            'budget_minor' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|size:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        $client = Client::findOrFail($data['client_id']);
        if (! empty($data['brand_id'])) {
            Brand::where('id', $data['brand_id'])->where('client_id', $client->id)->firstOrFail();
        }
        $c = $wf->create(TenantContext::tenantId(), $data, $r->user()->id);
        return redirect(MountPrefix::path($r, "/campaigns/{$c->id}"))->with('ok', 'أُنشئت الحملة (مسودة).');
    }
}
