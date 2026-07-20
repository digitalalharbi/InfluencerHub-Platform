<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Campaigns\Enums\DeliverableType;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\CampaignWorkflowService;
use App\Http\Controllers\Controller;
use App\Support\Analytics\CampaignAnalytics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل الحملة (React/Inertia) — مركز قيادة: الخطوة التالية + رحلة الحملة + الجاهزية + المخطط الزمني +
 * تبويبات (المخرجات/التعاونات/المحتوى). نفس محلّلات CampaignAnalytics الحقيقية. Policy(view)، معزولة.
 */
class CampaignDetailController extends Controller
{
    /** الإجراءات المتاحة لكل حالة → [action, label, tone, needsReason]. مطابقة لنسخة Blade. */
    private const ACTIONS = [
        'draft' => [['plan', 'نقل للتخطيط', 'primary', false], ['cancel', 'إلغاء الحملة', 'danger', true]],
        'planning' => [['activate', 'تفعيل', 'primary', false], ['cancel', 'إلغاء الحملة', 'danger', true]],
        'active' => [['complete', 'إكمال الحملة', 'primary', true], ['pause', 'إيقاف مؤقت', 'ghost', true], ['cancel', 'إلغاء الحملة', 'danger', true]],
        'paused' => [['resume', 'استئناف', 'primary', false], ['cancel', 'إلغاء الحملة', 'danger', true]],
        'completed' => [],
        'cancelled' => [],
    ];

    public function show(Campaign $campaign): Response
    {
        $this->authorize('view', $campaign);
        $campaign->load('client', 'brand', 'deliverables.creator', 'collaborations.creator', 'contentItems.creator');

        $metrics = CampaignAnalytics::forPage(collect([$campaign]))[$campaign->id] ?? [];
        $command = CampaignAnalytics::commandCenter($campaign, $metrics);
        $readiness = CampaignAnalytics::readiness($campaign, $metrics);
        $timeline = collect(CampaignAnalytics::timeline($campaign))->map(fn ($e) => [
            'at' => $e['at']?->format('Y-m-d H:i'),
            'icon' => $e['icon'],
            'tone' => $e['tone'],
            'text' => $e['text'],
            'meta' => $e['meta'],
        ])->values();

        $committed = (int) $campaign->committedMinor();

        return Inertia::render('Campaigns/Show', [
            // فواتير هذه الحملة — تُغلق الحملة ماليًّا من مكانها لا من وحدة أخرى
            'invoices' => \App\Domain\Finance\Models\Invoice::where('campaign_id', $campaign->id)
                ->withSum('payments', 'amount_minor')->latest('id')->get()
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'number' => $i->invoice_number,
                    'status' => $i->status,
                    'statusLabel' => __('statuses.' . $i->status),
                    'statusTone' => __('statuses.tone.' . $i->status),
                    'totalMinor' => (int) $i->total_minor,
                    'balanceMinor' => max(0, (int) $i->total_minor - (int) $i->payments_sum_amount_minor),
                    'dueDate' => $i->due_date?->format('Y-m-d'),
                ]),
            'canInvoice' => request()->user()?->can('create', \App\Domain\Finance\Models\Invoice::class) ?? false,
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'number' => $campaign->campaign_number,
                'client' => $campaign->client?->display_name,
                'clientId' => $campaign->client_id,
                'brand' => $campaign->brand?->name,
                'brandId' => $campaign->brand_id,
                // الطلب المصدر: الحملة تعرف من أين جاءت، فيُفتح الأصل بنقرة
                // بدل البحث عنه في الطابور — والسلسلة تبقى مرئية لا مضمرة.
                'sourceRequest' => ($src = $campaign->source_request_id
                    ? \App\Domain\Requests\Models\ServiceRequest::find($campaign->source_request_id)
                    : null)
                    ? ['id' => $src->id, 'number' => $src->request_number, 'title' => $src->title]
                    : null,
                'status' => $campaign->status,
                'statusLabel' => __('statuses.' . $campaign->status),
                'statusTone' => __('statuses.tone.' . $campaign->status),
                'budgetMinor' => (int) $campaign->budget_minor,
                'committedMinor' => $committed,
                'currency' => $campaign->currency,
                'startDate' => $campaign->start_date?->format('Y-m-d'),
                'endDate' => $campaign->end_date?->format('Y-m-d'),
                'objective' => $campaign->objective,
            ],
            'metrics' => [
                'progress' => (int) ($metrics['progress'] ?? 0),
                'deliverables' => (int) ($metrics['deliverables'] ?? 0),
                'creators' => (int) ($metrics['creators'] ?? 0),
                'awaitingClient' => (int) ($metrics['awaiting_client'] ?? 0),
                'isLate' => (bool) ($metrics['is_late'] ?? false),
            ],
            'command' => $command,
            'readiness' => $readiness,
            'timeline' => $timeline,
            'canManage' => request()->user()->can('update', $campaign),
            'actions' => request()->user()->can('update', $campaign) ? (self::ACTIONS[$campaign->status] ?? []) : [],
            'deliverableTypes' => collect(DeliverableType::labels())->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'deliverables' => $campaign->deliverables->map(fn ($d) => [
                'id' => $d->id, 'type' => $d->type, 'typeLabel' => DeliverableType::labels()[$d->type] ?? $d->type,
                'platform' => $d->platform, 'quantity' => (int) $d->quantity,
                'creator' => $d->creator?->display_name,
                'status' => $d->status, 'statusLabel' => __('statuses.' . $d->status), 'statusTone' => __('statuses.tone.' . $d->status),
            ])->values(),
            'collaborations' => $campaign->collaborations->map(fn ($c) => [
                'id' => $c->id, 'creator' => $c->creator?->display_name, 'title' => $c->title, 'feeMinor' => (int) $c->fee_minor,
                'status' => $c->status, 'statusLabel' => __('statuses.' . $c->status), 'statusTone' => __('statuses.tone.' . $c->status),
            ])->values(),
            'content' => $campaign->contentItems->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'creator' => $c->creator?->display_name, 'platform' => $c->platform,
                'status' => $c->status, 'statusLabel' => __('statuses.' . $c->status), 'statusTone' => __('statuses.tone.' . $c->status),
            ])->values(),
        ]);
    }

    public function update(Request $r, Campaign $campaign, CampaignWorkflowService $wf)
    {
        $this->authorize('update', $campaign);
        $data = $r->validate([
            'name' => 'required|string|max:160',
            'objective' => 'nullable|string|max:2000',
            'brief' => 'nullable|string|max:4000',
            'budget_minor' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|size:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        try { $wf->updateDraft($campaign, $data, $r->user()->id); }
        catch (\RuntimeException $e) { return back()->withErrors(['wf' => $e->getMessage()]); }
        return back()->with('ok', 'حُفظت الحملة.');
    }

    public function addDeliverable(Request $r, Campaign $campaign, CampaignWorkflowService $wf)
    {
        $this->authorize('update', $campaign);
        $data = $r->validate([
            'type' => 'required|in:' . implode(',', DeliverableType::values()),
            'platform' => 'nullable|string|max:20',
            'quantity' => 'required|integer|min:1|max:1000',
            'creator_id' => 'nullable|integer',
            'fee_minor' => 'nullable|integer|min:0',
            'due_date' => 'nullable|date',
        ]);
        try { $wf->addDeliverable($campaign, $data, $r->user()->id); }
        catch (\RuntimeException $e) { return back()->withErrors(['deliverable' => $e->getMessage()]); }
        return back()->with('ok', 'أُضيف المخرج.');
    }

    public function removeDeliverable(Request $r, Campaign $campaign, int $deliverable, CampaignWorkflowService $wf)
    {
        $this->authorize('update', $campaign);
        try { $wf->removeDeliverable($campaign, $deliverable, $r->user()->id); }
        catch (\RuntimeException $e) { return back()->withErrors(['deliverable' => $e->getMessage()]); }
        return back()->with('ok', 'حُذف المخرج.');
    }

    /** انتقالات حالة الحملة — نفس CampaignWorkflowService وبوابة update كما في Blade. */
    public function transition(Request $r, Campaign $campaign, string $action, CampaignWorkflowService $wf)
    {
        $this->authorize('update', $campaign);
        try {
            match ($action) {
                'plan' => $wf->plan($campaign, $r->user()->id),
                'activate' => $wf->activate($campaign, $r->user()->id),
                'pause' => $wf->pause($campaign, $r->user()->id, $r->input('reason')),
                'resume' => $wf->resume($campaign, $r->user()->id),
                'complete' => $wf->complete($campaign, $r->user()->id, $r->input('reason')),
                'cancel' => $wf->cancel($campaign, $r->user()->id, $r->input('reason')),
                default => abort(404),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُدّثت حالة الحملة.');
    }
}
