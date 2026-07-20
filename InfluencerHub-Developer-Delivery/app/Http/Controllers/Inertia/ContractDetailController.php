<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractWorkflowService;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل العقد (React/Inertia) — بنود + سير عمل (send/activate/complete/terminate/cancel) + سجل حالة.
 * الإجراءات تعيد استخدام ContractWorkflowService. view للعرض، manage للإجراءات. IDOR-safe.
 */
class ContractDetailController extends Controller
{
    /** [action, label, tone, needsReason]. */
    private const ACTIONS = [
        'draft' => [['send', 'إرسال للطرف', 'primary', false], ['cancel', 'إلغاء', 'danger', false]],
        'sent' => [['cancel', 'إلغاء', 'danger', false]],
        'signed' => [['activate', 'تفعيل العقد', 'primary', false], ['cancel', 'إلغاء', 'danger', false]],
        'active' => [['complete', 'إكمال', 'primary', false], ['terminate', 'إنهاء', 'danger', true]],
        'completed' => [], 'terminated' => [], 'cancelled' => [],
    ];

    public function show(Request $r, Contract $contract): Response
    {
        $this->authorize('view', $contract);
        $c = $contract->load('creator', 'client', 'statusHistory');
        $canManage = $r->user()->can('manage', $c);
        $actorNames = User::whereIn('id', $c->statusHistory->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $st = fn ($s) => __('statuses.' . $s);

        return Inertia::render('Contracts/Show', [
            'contract' => [
                'id' => $c->id, 'number' => $c->contract_number, 'title' => $c->title,
                'party' => $c->party_type === 'creator' ? ($c->creator?->display_name) : ($c->client?->display_name),
                'partyType' => $c->party_type === 'creator' ? 'مبدع' : 'عميل',
                'valueMinor' => (int) $c->value_minor, 'currency' => $c->currency,
                'startDate' => $c->start_date?->format('Y-m-d'), 'endDate' => $c->end_date?->format('Y-m-d'),
                'terms' => $c->terms, 'status' => $c->status, 'statusLabel' => $st($c->status), 'statusTone' => __('statuses.tone.' . $c->status),
                'signedByName' => $c->signed_by_name, 'signedAt' => $c->signed_at?->format('Y-m-d H:i'),
            ],
            'canManage' => $canManage,
            'actions' => $canManage ? (self::ACTIONS[$c->status] ?? []) : [],
            // الانتظار حالة مشروعة لكنها تُعلَن: قائمة إجراءات فارغة
            // بلا تفسير تبدو عطلًا أو صلاحية ناقصة.
            'waitingOn' => \App\Support\Workflow\WaitingOn::for('contract', $c->status),
            'history' => $c->statusHistory->sortByDesc('id')->values()->map(fn ($h) => [
                'from' => $h->from_status ? $st($h->from_status) : '—', 'to' => $st($h->to_status),
                'by' => $actorNames[$h->actor_id] ?? ($h->actor_type ?? '—'), 'reason' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function action(Request $r, Contract $contract, string $action, ContractWorkflowService $wf): RedirectResponse
    {
        $this->authorize('manage', $contract);
        try {
            match ($action) {
                'send' => $wf->send($contract, $r->user()->id),
                'activate' => $wf->activate($contract, $r->user()->id),
                'complete' => $wf->complete($contract, $r->user()->id),
                'terminate' => $wf->terminate($contract, $r->user()->id, $r->validate(['reason' => 'required|string|max:500'])['reason']),
                'cancel' => $wf->cancel($contract, $r->user()->id, $r->input('reason')),
                default => abort(404),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }
        return back()->with('ok', 'حُدّثت حالة العقد.');
    }

    /**
     * حفظ تعديلات المسودة — نفس تحقّق Blade وupdateDraft نفسه
     * (الخدمة ترفض التعديل بعد مغادرة حالة المسودة).
     */
    public function update(Request $r, Contract $contract, ContractWorkflowService $wf): RedirectResponse
    {
        $this->authorize('manage', $contract);
        $data = $r->validate([
            'title' => 'required|string|max:160',
            'terms' => 'nullable|string|max:20000',
            'value_minor' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|size:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $wf->updateDraft($contract, $data, $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُفظ العقد.');
    }
}
