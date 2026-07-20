<?php

namespace App\Http\Controllers\Inertia\Creator;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Collaborations\Services\CollaborationWorkflowService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تعاونات المبدع (React/Inertia) — عرض + إجراءات (قبول/رفض/بدء/تسليم) عبر CollaborationWorkflowService.
 * معزول على المبدع النشِط.
 */
class CollaborationController extends Controller
{
    public function index(Request $r): Response
    {
        $c = $r->attributes->get('creator');
        $items = Collaboration::with('campaign', 'client')->where('creator_id', $c->id)->latest()->paginate(15)
            ->through(fn (Collaboration $cl) => $this->row($cl));
        $actionable = Collaboration::where('creator_id', $c->id)->whereIn('status', Collaboration::CREATOR_ACTIONABLE)->count();

        return Inertia::render('CreatorPortal/Collaborations/Index', [
            'creatorName' => $c->display_name,
            'items' => $items,
            'actionable' => $actionable,
        ]);
    }

    public function show(Request $r, int $collaboration): Response
    {
        $col = $this->collabOf($r, $collaboration);
        $col->load('campaign', 'client', 'statusHistory');
        $actorIds = $col->statusHistory->pluck('actor_id')->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->pluck('name', 'id');
        $history = $col->statusHistory->sortByDesc('occurred_at')->take(12)->map(fn ($h) => [
            'to' => __("statuses.{$h->to_status}"), 'tone' => __("statuses.tone.{$h->to_status}"),
            'actor' => $actors[$h->actor_id] ?? 'النظام', 'note' => $h->reason,
            'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();

        return Inertia::render('CreatorPortal/Collaborations/Show', [
            'creatorName' => $r->attributes->get('creator')->display_name,
            'collab' => $this->row($col) + [
                'brief' => $col->brief,
                'campaign' => $col->campaign?->name,
                'dueDate' => $col->due_date?->format('Y-m-d'),
                'declineReason' => $col->decline_reason,
                'submissionNote' => $col->submission_note,
            ],
            'history' => $history,
            'actions' => $this->availableActions($col->status),
        ]);
    }

    public function action(Request $r, int $collaboration, string $action, CollaborationWorkflowService $wf)
    {
        $col = $this->collabOf($r, $collaboration);
        abort_unless(in_array($action, ['accept', 'decline', 'start', 'submit'], true), 404);
        try {
            match ($action) {
                'accept' => $wf->accept($col, $r->user()->id),
                'decline' => $wf->decline($col, $r->user()->id, $r->input('reason')),
                'start' => $wf->startWork($col, $r->user()->id),
                'submit' => $wf->submit($col, $r->user()->id, $r->input('note')),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['collab' => $e->getMessage()]);
        }
        return back()->with('ok', 'حُدّثت حالة التعاون.');
    }

    /** الإجراءات المتاحة للمبدع حسب الحالة (تُطابق CollaborationWorkflowService). */
    private function availableActions(string $status): array
    {
        return match ($status) {
            'offered' => [
                ['key' => 'accept', 'label' => 'قبول التعاون', 'tone' => 'primary', 'input' => null],
                ['key' => 'decline', 'label' => 'اعتذار', 'tone' => 'danger', 'input' => 'reason'],
            ],
            'accepted' => [['key' => 'start', 'label' => 'بدء العمل', 'tone' => 'primary', 'input' => null]],
            'in_progress' => [['key' => 'submit', 'label' => 'تسليم العمل', 'tone' => 'primary', 'input' => 'note']],
            default => [],
        };
    }

    private function collabOf(Request $r, int $id): Collaboration
    {
        $c = $r->attributes->get('creator');
        $col = Collaboration::where('id', $id)->where('creator_id', $c->id)->first();
        abort_unless($col, 404);
        return $col;
    }

    private function row(Collaboration $cl): array
    {
        return [
            'id' => $cl->id, 'number' => $cl->collaboration_number, 'title' => $cl->title,
            'campaignName' => $cl->campaign?->name, 'client' => $cl->client?->display_name,
            'feeMinor' => (int) $cl->fee_minor, 'currency' => $cl->currency,
            'status' => $cl->status, 'statusLabel' => __("statuses.{$cl->status}"), 'statusTone' => __("statuses.tone.{$cl->status}"),
        ];
    }
}
