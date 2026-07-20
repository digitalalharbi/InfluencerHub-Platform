<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractWorkflowService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * عقود العميل (React/Inertia) — عرض + قبول/توقيع (client_admin فقط) عبر ContractWorkflowService::sign.
 * معزول على العميل النشِط. العميل لا يرى المسودات.
 */
class ContractController extends Controller
{
    private const VISIBLE = ['sent', 'signed', 'active', 'completed', 'terminated'];

    public function index(Request $r): Response
    {
        $c = $r->attributes->get('activeClient');
        $items = Contract::where('party_type', 'client')->where('client_id', $c->id)->whereIn('status', self::VISIBLE)
            ->latest()->paginate(15)->through(fn (Contract $ct) => $this->row($ct));
        $awaiting = Contract::where('party_type', 'client')->where('client_id', $c->id)->where('status', 'sent')->count();

        return Inertia::render('ClientPortal/Contracts/Index', [
            'clientName' => $c->display_name,
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
            'actor' => $actors[$h->actor_id] ?? ($h->actor_type === 'client' ? 'العميل' : 'الوكالة'),
            'note' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();
        $canSign = $r->attributes->get('clientMembership')->role === 'client_admin';

        return Inertia::render('ClientPortal/Contracts/Show', [
            'clientName' => $r->attributes->get('activeClient')->display_name,
            'contract' => $this->row($ct) + [
                'terms' => $ct->terms,
                'campaign' => $ct->campaign?->name,
                'startDate' => $ct->start_date?->format('Y-m-d'), 'endDate' => $ct->end_date?->format('Y-m-d'),
                'signedByName' => $ct->signed_by_name, 'signedAt' => $ct->signed_at?->format('Y-m-d H:i'),
            ],
            'history' => $history,
            'canSign' => $canSign,
            'isPending' => $ct->status === 'sent',
        ]);
    }

    public function sign(Request $r, int $contract, ContractWorkflowService $wf)
    {
        abort_unless($r->attributes->get('clientMembership')->role === 'client_admin', 403);
        $ct = $this->contractOf($r, $contract);
        $data = $r->validate(['signer_name' => 'required|string|max:120', 'agree' => 'accepted']);
        try { $wf->sign($ct, $r->user()->id, $data['signer_name'], 'client'); }
        catch (\RuntimeException $e) { return back()->withErrors(['contract' => $e->getMessage()]); }
        return back()->with('ok', 'قبِلت العقد وسُجّل قبولك.');
    }

    private function contractOf(Request $r, int $id): Contract
    {
        $c = $r->attributes->get('activeClient');
        $ct = Contract::where('id', $id)->where('party_type', 'client')->where('client_id', $c->id)
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
