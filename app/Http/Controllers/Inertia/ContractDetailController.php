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

    private const DOC_TYPE = 'contract_pdf';
    private const DOC_TEMPLATE = 'v1';

    private function contractData(Contract $contract): array
    {
        $contract->loadMissing('creator', 'client', 'campaign');
        $cur = $contract->currency ?: 'SAR';
        $map = ['draft' => ['#eef2f7', '#475467'], 'sent' => ['#eff8ff', '#175cd3'], 'signed' => ['#ecfdf3', '#067647'],
            'active' => ['#ecfdf3', '#067647'], 'completed' => ['#f2f4f7', '#475467'], 'terminated' => ['#fef3f2', '#b42318'], 'cancelled' => ['#f2f4f7', '#475467']];
        return [
            'workspace' => \App\Domain\Tenancy\Models\Organization::find(\App\Domain\Tenancy\Support\TenantContext::organizationId())?->name ?? \App\Support\Brand::name(),
            'number' => $contract->contract_number,
            'title' => $contract->title,
            'party' => $contract->party_type === 'creator' ? ($contract->creator?->display_name ?? '—') : ($contract->client?->display_name ?? '—'),
            'partyType' => $contract->party_type === 'creator' ? 'مبدع' : 'عميل',
            'campaign' => $contract->campaign?->name,
            'statusLabel' => __('statuses.' . $contract->status),
            'statusColor' => $map[$contract->status] ?? ['#eef2f7', '#475467'],
            'value' => $contract->value_minor ? number_format($contract->value_minor / 100, 2) . ' ' . $cur : null,
            'start' => $contract->start_date?->format('Y-m-d'),
            'end' => $contract->end_date?->format('Y-m-d'),
            'terms' => $contract->terms,
            'signedBy' => $contract->signed_by_name,
            'signedAt' => $contract->signed_at?->format('Y-m-d'),
        ];
    }

    private function docArtifact(Request $r, Contract $contract, \App\Domain\Exports\DocumentArtifactService $svc, bool $regenerate = false): \App\Domain\Exports\Models\ExportJob
    {
        $data = $this->contractData($contract);
        $render = function () use ($contract, $data, $svc, $r) {
            \App\Domain\Audit\Services\AuditLogger::log('export.generated', $contract, ['type' => self::DOC_TYPE, 'format' => 'pdf'], $contract->tenant_id, $r->user()?->id);
            return $svc->pdfFromView('exports.contract', $data + ['generatedAt' => now()->format('Y-m-d H:i')]);
        };
        if (! $regenerate) { $latest = $svc->latest(self::DOC_TYPE, $contract); if ($latest) return $latest; }
        return $svc->current(self::DOC_TYPE, $contract, 'pdf', self::DOC_TEMPLATE, $data, 'عقد ' . $contract->contract_number, $render, $r->user()?->id);
    }

    private function streamDoc(\App\Domain\Exports\Models\ExportJob $a, \App\Domain\Exports\DocumentArtifactService $svc, string $disposition)
    {
        $bytes = $svc->bytes($a);
        return response($bytes, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . \App\Support\Brand::documentFilename($a->title) . '"',
            'Content-Length' => (string) strlen($bytes), 'X-Artifact-Checksum' => $a->checksum]);
    }

    public function pdfPreview(Request $r, Contract $contract, \App\Domain\Exports\DocumentArtifactService $svc)
    {
        $this->authorize('view', $contract);
        return $this->streamDoc($this->docArtifact($r, $contract, $svc), $svc, 'inline');
    }

    public function pdfDownload(Request $r, Contract $contract, \App\Domain\Exports\DocumentArtifactService $svc)
    {
        $this->authorize('view', $contract);
        return $this->streamDoc($this->docArtifact($r, $contract, $svc), $svc, 'attachment');
    }

    public function pdfRegenerate(Request $r, Contract $contract, \App\Domain\Exports\DocumentArtifactService $svc): RedirectResponse
    {
        $this->authorize('view', $contract);
        $this->docArtifact($r, $contract, $svc, regenerate: true);
        return back()->with('ok', 'أُنشئت نسخة محدّثة من العقد.');
    }

    private function docMeta(Contract $contract, \App\Domain\Exports\DocumentArtifactService $svc): array
    {
        $latest = $svc->latest(self::DOC_TYPE, $contract);
        $currentFp = $svc->fingerprint($this->contractData($contract), self::DOC_TEMPLATE, 'pdf');
        $base = "/contracts/{$contract->id}/pdf";
        return ['title' => 'عقد ' . $contract->contract_number, 'hasArtifact' => (bool) $latest,
            'generatedAt' => $latest?->created_at?->format('Y-m-d H:i'), 'stale' => $svc->isStale($latest, $currentFp),
            'previewUrl' => "{$base}/preview", 'downloadUrl' => "{$base}/download", 'regenerateUrl' => "{$base}/regenerate"];
    }

    public function show(Request $r, Contract $contract, \App\Domain\Exports\DocumentArtifactService $artifacts): Response
    {
        $this->authorize('view', $contract);
        $c = $contract->load('creator', 'client', 'statusHistory');
        $canManage = $r->user()->can('manage', $c);
        $actorNames = User::whereIn('id', $c->statusHistory->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $st = fn ($s) => __('statuses.' . $s);

        return Inertia::render('Contracts/Show', [
            'documents' => ['pdf' => $this->docMeta($contract, $artifacts)],
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
