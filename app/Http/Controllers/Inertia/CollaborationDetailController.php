<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Collaborations\Services\CollaborationWorkflowService;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractWorkflowService;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Services\PayoutWorkflowService;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use App\Support\Workflow\WaitingOn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل التعاون (React/Inertia) — Brief + سير عمل + سجل حالة.
 * الإجراءات تعيد استخدام CollaborationWorkflowService. view/manage=WRITE. IDOR-safe.
 */
class CollaborationDetailController extends Controller
{
    /** [action, labelKey (collaborations.act_*), tone, needsReason]. التسمية تُترجَم في show. */
    private const ACTIONS = [
        'offered' => [['cancel', 'cancel_offer', 'danger', false]],
        // بعد القبول يأتي العقد — وهو الخطوة التالية في الرحلة لا إجراء جانبيًّا
        'accepted' => [['issue-contract', 'issue_contract', 'primary', false], ['cancel', 'cancel', 'danger', false]],
        'in_progress' => [['issue-contract', 'issue_contract', 'primary', false], ['cancel', 'cancel', 'danger', false]],
        'submitted' => [['approve', 'approve_submission', 'primary', false], ['request-revision', 'request_revision', 'ghost', true], ['cancel', 'cancel', 'danger', false]],
        // العمل اعتُمد، فأجره صار مستحقًّا — الخطوة التالية في الرحلة لا إجراء جانبيّ
        'approved' => [['complete', 'complete', 'primary', false], ['create-payout', 'create_payout', 'primary', false], ['cancel', 'cancel', 'danger', false]],
        // التعاون المكتمل ما زال يستحقّ أجره — كانت قائمته فارغة فبدا الأجر منسيًّا
        'completed' => [['create-payout', 'create_payout', 'primary', false]],
        'declined' => [], 'cancelled' => [],
    ];

    /** العقد يُصدَر مرّة؛ بعدها الإجراء هو فتحه لا إصداره ثانيةً. */
    private function actionsFor(Collaboration $c): array
    {
        $actions = self::ACTIONS[$c->status] ?? [];
        $hasContract = Contract::where('collaboration_id', $c->id)
            ->whereNotIn('status', ['cancelled', 'terminated'])->exists();
        $hasPayout = Payout::where('collaboration_id', $c->id)
            ->whereNotIn('status', ['cancelled', 'failed'])->exists();

        // كلٌّ يُصدَر مرّة؛ بعدها الإجراء هو فتحه لا إصداره ثانيةً
        $hide = array_merge(
            $hasContract ? ['issue-contract'] : [],
            $hasPayout ? ['create-payout'] : [],
        );

        return array_values(array_filter($actions, fn ($a) => ! in_array($a[0], $hide, true)));
    }

    public function show(Request $r, Collaboration $collaboration): Response
    {
        $this->authorize('view', $collaboration);
        $c = $collaboration->load('creator', 'campaign', 'statusHistory');
        $canManage = $r->user()->can('manage', $c);
        $actorNames = User::whereIn('id', $c->statusHistory->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $st = fn ($s) => __('statuses.'.$s);

        return Inertia::render('Collaborations/Show', [
            'collaboration' => [
                'id' => $c->id, 'number' => $c->collaboration_number, 'title' => $c->title, 'brief' => $c->brief,
                'creator' => $c->creator?->display_name, 'creatorId' => $c->creator_id,
                'campaign' => $c->campaign?->name, 'campaignId' => $c->campaign_id,
                'feeMinor' => (int) $c->fee_minor, 'currency' => $c->currency, 'dueDate' => $c->due_date?->format('Y-m-d'),
                'submissionNote' => $c->submission_note, 'declineReason' => $c->decline_reason,
                'status' => $c->status, 'statusLabel' => $st($c->status), 'statusTone' => __('statuses.tone.'.$c->status),
            ],
            'canManage' => $canManage,
            // العقد الحيّ لهذا التعاون إن وُجد — يُبدّل «إصدار العقد» بفتحه
            'contractId' => Contract::where('collaboration_id', $c->id)
                ->whereNotIn('status', ['cancelled', 'terminated'])->value('id'),
            'actions' => $canManage ? array_map(
                fn ($a) => [$a[0], trans("collaborations.act_{$a[1]}"), $a[2], $a[3]],
                $this->actionsFor($c),
            ) : [],
            // الانتظار حالة مشروعة لكنها تُعلَن: قائمة إجراءات فارغة
            // بلا تفسير تبدو عطلًا أو صلاحية ناقصة.
            'waitingOn' => WaitingOn::for('collaboration', $c->status),
            'history' => $c->statusHistory->sortByDesc('id')->values()->map(fn ($h) => [
                'from' => $h->from_status ? $st($h->from_status) : '—', 'to' => $st($h->to_status),
                'by' => $actorNames[$h->actor_id] ?? ($h->actor_type ?? '—'), 'reason' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function action(Request $r, Collaboration $collaboration, string $action, CollaborationWorkflowService $wf): RedirectResponse
    {
        $this->authorize('manage', $collaboration);

        // إصدار العقد ينقل المستخدم إلى العقد المُنشأ، فلا يندرج تحت «حُدّثت الحالة»
        if ($action === 'issue-contract') {
            $this->authorize('create', Contract::class);
            try {
                $contract = app(ContractWorkflowService::class)
                    ->createFromCollaboration($collaboration, $r->user()->id);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['wf' => $e->getMessage()]);
            }

            return redirect(MountPrefix::path($r, "/contracts/{$contract->id}"))
                ->with('ok', 'أُصدر العقد من التعاون — راجعه ثم أرسله للتوقيع.');
        }

        // المستحقّ ينقل المستخدم إليه، ويحتاج صلاحية مالية لا صلاحية تعاون
        if ($action === 'create-payout') {
            $this->authorize('create', Payout::class);
            try {
                $payout = app(PayoutWorkflowService::class)
                    ->createFromCollaboration($collaboration, $r->user()->id);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['wf' => $e->getMessage()]);
            }

            return redirect(MountPrefix::path($r, "/payouts/{$payout->id}"))
                ->with('ok', 'أُنشئ المستحقّ من التعاون — راجعه ثم اعتمده.');
        }

        try {
            match ($action) {
                'approve' => $wf->approve($collaboration, $r->user()->id, $r->input('note')),
                'request-revision' => $wf->requestRevision($collaboration, $r->user()->id, $r->validate(['reason' => 'required|string|max:500'])['reason']),
                'complete' => $wf->complete($collaboration, $r->user()->id),
                'cancel' => $wf->cancel($collaboration, $r->user()->id, $r->input('reason')),
                default => abort(404),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُدّثت حالة التعاون.');
    }
}
