<?php

namespace App\Http\Controllers\Inertia\Creator;

use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractWorkflowService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * عقود المبدع (React/Inertia) — عرض + قبول/توقيع عبر ContractWorkflowService::sign('creator').
 * معزول على المبدع النشِط. المبدع يوقّع عقده بنفسه.
 */
class ContractController extends Controller
{
    private const VISIBLE = ['sent', 'signed', 'active', 'completed', 'terminated'];

    public function index(Request $r): Response
    {
        $c = $r->attributes->get('creator');
        $items = Contract::where('party_type', 'creator')->where('creator_id', $c->id)->whereIn('status', self::VISIBLE)
            ->latest()->paginate(15)->through(fn (Contract $ct) => $this->row($ct));
        $awaiting = Contract::where('party_type', 'creator')->where('creator_id', $c->id)->where('status', 'sent')->count();

        return Inertia::render('CreatorPortal/Contracts/Index', [
            'creatorName' => $c->display_name,
            'items' => $items,
            'awaiting' => $awaiting,
        ]);
    }

    public function show(Request $r, int $contract): Response
    {
        $ct = $this->contractOf($r, $contract);
        $ct->load('campaign', 'statusHistory');
        $actorIds = $ct->statusHistory->pluck('actor_id')->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->pluck('name', 'id');
        $history = $ct->statusHistory->sortByDesc('occurred_at')->take(12)->map(fn ($h) => [
            'to' => __("statuses.{$h->to_status}"), 'tone' => __("statuses.tone.{$h->to_status}"),
            'actor' => $actors[$h->actor_id] ?? ($h->actor_type === 'creator' ? 'أنت' : 'الوكالة'),
            'note' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();

        return Inertia::render('CreatorPortal/Contracts/Show', [
            'creatorName' => $r->attributes->get('creator')->display_name,
            'contract' => $this->row($ct) + [
                'terms' => $ct->terms, 'campaign' => $ct->campaign?->name,
                'startDate' => $ct->start_date?->format('Y-m-d'), 'endDate' => $ct->end_date?->format('Y-m-d'),
                'signedByName' => $ct->signed_by_name, 'signedAt' => $ct->signed_at?->format('Y-m-d H:i'),
            ],
            'history' => $history,
            'isPending' => $ct->status === 'sent',
        ]);
    }

    public function sign(Request $r, int $contract, ContractWorkflowService $wf)
    {
        $ct = $this->contractOf($r, $contract);
        $data = $r->validate(['signer_name' => 'required|string|max:120', 'agree' => 'accepted']);
        try { $wf->sign($ct, $r->user()->id, $data['signer_name'], 'creator'); }
        catch (\RuntimeException $e) { return back()->withErrors(['contract' => $e->getMessage()]); }
        return back()->with('ok', 'قبِلت العقد وسُجّل قبولك.');
    }

    private function contractOf(Request $r, int $id): Contract
    {
        $c = $r->attributes->get('creator');
        $ct = Contract::where('id', $id)->where('party_type', 'creator')->where('creator_id', $c->id)
            ->whereIn('status', self::VISIBLE)->first();
        abort_unless($ct, 404);
        return $ct;
    }

    private function row(Contract $ct): array
    {
        return [
            'id' => $ct->id, 'number' => $ct->contract_number, 'title' => $ct->title,
            'campaignName' => $ct->campaign?->name,
            'valueMinor' => (int) $ct->value_minor, 'currency' => $ct->currency,
            'status' => $ct->status, 'statusLabel' => __("statuses.{$ct->status}"), 'statusTone' => __("statuses.tone.{$ct->status}"),
        ];
    }
}
