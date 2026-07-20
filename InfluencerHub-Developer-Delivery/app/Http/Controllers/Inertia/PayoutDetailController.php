<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Services\PayoutWorkflowService;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل المستحق (React/Inertia) — سير صرف صادق (لا تنفيذ تحويل؛ "مدفوع" يدوية بمرجع).
 * الإجراءات تعيد استخدام PayoutWorkflowService. view للعرض، manage للإجراءات. IDOR-safe.
 */
class PayoutDetailController extends Controller
{
    /** [action, label, tone, input(none|reason|date|reference)]. */
    private const ACTIONS = [
        'pending' => [['approve', 'اعتماد', 'primary', 'none'], ['cancel', 'إلغاء', 'danger', 'none']],
        'approved' => [['schedule', 'جدولة الصرف', 'primary', 'date'], ['cancel', 'إلغاء', 'danger', 'none']],
        'scheduled' => [['send-to-provider', 'إرسال للمزوّد', 'primary', 'none'], ['cancel', 'إلغاء', 'danger', 'none']],
        'waiting_for_provider' => [['mark-paid', 'تسجيل الدفع', 'primary', 'reference'], ['mark-failed', 'تسجيل الفشل', 'danger', 'reason']],
        'failed' => [['schedule', 'إعادة الجدولة', 'primary', 'date'], ['cancel', 'إلغاء', 'danger', 'none']],
        'paid' => [], 'cancelled' => [],
    ];

    public function show(Request $r, Payout $payout): Response
    {
        $this->authorize('view', $payout);
        $p = $payout->load('creator', 'statusHistory');
        // كل فعل يُفحص بقاعدته: مدير الحملة يطلب ولا يعتمد، والصرف للمالية وحدها
        $allowed = fn (string $action) => $r->user()->can('act', [$p, $action]);
        $actorNames = User::whereIn('id', $p->statusHistory->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $st = fn ($s) => __('statuses.' . $s);

        return Inertia::render('Payouts/Show', [
            'payout' => [
                'id' => $p->id, 'number' => $p->payout_number, 'creator' => $p->creator?->display_name,
                'amountMinor' => (int) $p->amount_minor, 'currency' => $p->currency, 'ibanLast4' => $p->iban_last4,
                'description' => $p->description, 'dueDate' => $p->due_date?->format('Y-m-d'),
                'paidAt' => $p->paid_at?->format('Y-m-d H:i'), 'paymentReference' => $p->payment_reference, 'failureReason' => $p->failure_reason,
                'status' => $p->status, 'statusLabel' => $st($p->status), 'statusTone' => __('statuses.tone.' . $p->status),
            ],
            'canManage' => $r->user()->can('update', $p),
            'actions' => array_values(array_filter(
                self::ACTIONS[$p->status] ?? [],
                fn (array $a) => $allowed($a[0]),
            )),
            // شفافية: النظام لا ينفّذ تحويلًا في مرحلة انتظار المزوّد
            'providerNote' => $p->status === 'waiting_for_provider',
            'history' => $p->statusHistory->sortByDesc('id')->values()->map(fn ($h) => [
                'from' => $h->from_status ? $st($h->from_status) : '—', 'to' => $st($h->to_status),
                'by' => $actorNames[$h->actor_id] ?? '—', 'reason' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function action(Request $r, Payout $payout, string $action, PayoutWorkflowService $wf): RedirectResponse
    {
        // التفويض بالفعل نفسه: `manage` العامّة كانت تسمح لمن يطلب أن يصرف
        abort_unless($r->user()->can('act', [$payout, $action]), 403,
            'هذا الإجراء يحتاج صلاحية مالية لا تملكها.');

        try {
            match ($action) {
                'approve' => $wf->approve($payout, $r->user()->id),
                'schedule' => $wf->schedule($payout, $r->user()->id, $r->input('due_date') ? new \DateTimeImmutable($r->input('due_date')) : null),
                'send-to-provider' => $wf->sendToProvider($payout, $r->user()->id),
                'mark-paid' => $wf->markPaid($payout, $r->user()->id, $r->validate(['payment_reference' => 'required|string|max:120'])['payment_reference']),
                'mark-failed' => $wf->markFailed($payout, $r->user()->id, $r->validate(['reason' => 'required|string|max:300'])['reason']),
                'cancel' => $wf->cancel($payout, $r->user()->id, $r->input('reason')),
                default => abort(404),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُدّثت حالة المستحق.');
    }
}
