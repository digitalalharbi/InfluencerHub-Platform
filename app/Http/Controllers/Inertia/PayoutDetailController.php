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

    private const STMT_TYPE = 'payout_statement';
    private const STMT_TEMPLATE = 'v1';

    /** بيانات كشف المستحق — حتمية (تُحدّد البصمة، بلا وقت). مستند مالي داخلي. */
    private function statementData(Payout $payout): array
    {
        $payout->loadMissing('creator', 'campaign');
        $cur = $payout->currency ?: 'SAR';
        $m = fn (int $minor) => number_format($minor / 100, 2) . ' ' . $cur;
        $map = [
            'pending' => ['#eef2f7', '#475467'], 'approved' => ['#eff8ff', '#175cd3'], 'scheduled' => ['#eff8ff', '#175cd3'],
            'waiting_for_provider' => ['#fffaeb', '#b54708'], 'paid' => ['#ecfdf3', '#067647'],
            'failed' => ['#fef3f2', '#b42318'], 'cancelled' => ['#f2f4f7', '#475467'],
        ];
        return [
            'workspace' => \App\Domain\Tenancy\Models\Organization::find(\App\Domain\Tenancy\Support\TenantContext::organizationId())?->name ?? \App\Support\Brand::name(),
            'number' => $payout->payout_number,
            'creator' => $payout->creator?->display_name ?? '—',
            'campaign' => $payout->campaign?->name,
            'iban4' => $payout->iban_last4,
            'statusLabel' => __('statuses.' . $payout->status),
            'statusColor' => $map[$payout->status] ?? ['#eef2f7', '#475467'],
            'amount' => $m((int) $payout->amount_minor),
            'currency' => $cur,
            'due' => $payout->due_date?->format('Y-m-d'),
            'paid' => $payout->paid_at?->format('Y-m-d'),
            'reference' => $payout->payment_reference,
            'description' => $payout->description,
            'failure' => $payout->failure_reason,
        ];
    }

    private function stmtArtifact(Request $r, Payout $payout, \App\Domain\Exports\DocumentArtifactService $svc, bool $regenerate = false): \App\Domain\Exports\Models\ExportJob
    {
        $data = $this->statementData($payout);
        $render = function () use ($payout, $data, $svc, $r) {
            \App\Domain\Audit\Services\AuditLogger::log('export.generated', $payout, ['type' => self::STMT_TYPE, 'format' => 'pdf'], $payout->tenant_id, $r->user()?->id);
            return $svc->pdfFromView('exports.payout-statement', $data + ['generatedAt' => now()->format('Y-m-d H:i')]);
        };
        if (! $regenerate) { $latest = $svc->latest(self::STMT_TYPE, $payout); if ($latest) return $latest; }
        return $svc->current(self::STMT_TYPE, $payout, 'pdf', self::STMT_TEMPLATE, $data, 'كشف مستحق ' . $payout->payout_number, $render, $r->user()?->id);
    }

    private function streamStmt(\App\Domain\Exports\Models\ExportJob $a, \App\Domain\Exports\DocumentArtifactService $svc, string $disposition)
    {
        $bytes = $svc->bytes($a);
        return response($bytes, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . \App\Support\Brand::documentFilename($a->title) . '"',
            'Content-Length' => (string) strlen($bytes), 'X-Artifact-Checksum' => $a->checksum]);
    }

    public function pdfPreview(Request $r, Payout $payout, \App\Domain\Exports\DocumentArtifactService $svc)
    {
        $this->authorize('view', $payout);
        return $this->streamStmt($this->stmtArtifact($r, $payout, $svc), $svc, 'inline');
    }

    public function pdfDownload(Request $r, Payout $payout, \App\Domain\Exports\DocumentArtifactService $svc)
    {
        $this->authorize('view', $payout);
        return $this->streamStmt($this->stmtArtifact($r, $payout, $svc), $svc, 'attachment');
    }

    public function pdfRegenerate(Request $r, Payout $payout, \App\Domain\Exports\DocumentArtifactService $svc): RedirectResponse
    {
        $this->authorize('view', $payout);
        $this->stmtArtifact($r, $payout, $svc, regenerate: true);
        return back()->with('ok', 'أُنشئت نسخة محدّثة من الكشف.');
    }

    private function stmtMeta(Payout $payout, \App\Domain\Exports\DocumentArtifactService $svc): array
    {
        $latest = $svc->latest(self::STMT_TYPE, $payout);
        $currentFp = $svc->fingerprint($this->statementData($payout), self::STMT_TEMPLATE, 'pdf');
        $base = "/payouts/{$payout->id}/statement";
        return ['title' => 'كشف مستحق ' . $payout->payout_number, 'hasArtifact' => (bool) $latest,
            'generatedAt' => $latest?->created_at?->format('Y-m-d H:i'), 'stale' => $svc->isStale($latest, $currentFp),
            'previewUrl' => "{$base}/preview", 'downloadUrl' => "{$base}/download", 'regenerateUrl' => "{$base}/regenerate"];
    }

    public function show(Request $r, Payout $payout, \App\Domain\Exports\DocumentArtifactService $artifacts): Response
    {
        $this->authorize('view', $payout);
        $p = $payout->load('creator', 'statusHistory');
        // كل فعل يُفحص بقاعدته: مدير الحملة يطلب ولا يعتمد، والصرف للمالية وحدها
        $allowed = fn (string $action) => $r->user()->can('act', [$p, $action]);
        $actorNames = User::whereIn('id', $p->statusHistory->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $st = fn ($s) => __('statuses.' . $s);

        return Inertia::render('Payouts/Show', [
            'documents' => ['statement' => $this->stmtMeta($payout, $artifacts)],
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
