<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Identity\Models\User;
use App\Domain\Requests\Enums\{ServiceRequestPriority, ServiceRequestType};
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\CampaignWorkflowService;
use App\Domain\Requests\Services\ServiceRequestWorkflowService;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل طلب الخدمة (React/Inertia) — SLA + إسناد + سير عمل + تعليقات + سجل حالة.
 * الإجراءات تعيد استخدام ServiceRequestWorkflowService (لا نسختا منطق) وترجع Inertia redirect.
 * الصلاحيات: view للعرض، handle للإجراءات (Policy). معزولة بالمستأجر.
 */
class ServiceRequestDetailController extends Controller
{
    /** الإجراءات المتاحة لكل حالة → [action, label, tone, needsReason(bool)]. */
    private const ACTIONS = [
        'submitted' => [['triage', 'بدء الفرز', 'primary', false], ['cancel', 'إلغاء', 'danger', false]],
        'triage' => [['start', 'بدء التنفيذ', 'primary', false], ['request-info', 'طلب معلومة', 'ghost', true], ['cancel', 'إلغاء', 'danger', false]],
        'in_progress' => [['resolve', 'إنجاز الطلب', 'primary', false], ['request-info', 'طلب معلومة', 'ghost', true], ['cancel', 'إلغاء', 'danger', false]],
        'needs_info' => [['start', 'استئناف التنفيذ', 'primary', false], ['cancel', 'إلغاء', 'danger', false]],
        'resolved' => [['close', 'إغلاق الطلب', 'primary', false], ['reopen', 'إعادة الفتح', 'ghost', true]],
        'closed' => [],
        'cancelled' => [],
    ];

    public function show(Request $r, ServiceRequest $serviceRequest): Response
    {
        $this->authorize('view', $serviceRequest);
        $s = $serviceRequest->load('client', 'brand', 'assignee', 'requesterClient', 'requesterAgency', 'statusHistory', 'comments.author');
        $actorNames = User::whereIn('id', $s->statusHistory->pluck('actor_id')->filter()->unique())->pluck('name', 'id');
        $canHandle = $r->user()->can('handle', $s);
        $now = Carbon::now();
        $open = ServiceRequest::OPEN_STATUSES;

        $sla = 'none'; $hours = null;
        if ($s->due_at) {
            $isOpen = in_array($s->status, $open, true);
            $hours = (int) round($now->diffInHours($s->due_at, false));
            if ($s->sla_breached_at || ($isOpen && $s->due_at->isPast())) $sla = 'overdue';
            elseif ($isOpen && $s->due_at->lte($now->copy()->addHours(24))) $sla = 'soon';
            else $sla = 'ok';
        }
        $prio = ServiceRequestPriority::labels();
        $types = ServiceRequestType::labels();

        return Inertia::render('ServiceRequests/Show', [
            'request' => [
                'id' => $s->id, 'number' => $s->request_number, 'title' => $s->title, 'description' => $s->description,
                'client' => $s->client?->display_name ?? $s->requesterAgency?->name, 'brand' => $s->brand?->name,
                'type' => $types[$s->type] ?? $s->type, 'priority' => $s->priority, 'priorityLabel' => $prio[$s->priority] ?? $s->priority,
                'status' => $s->status, 'statusLabel' => __('statuses.' . $s->status), 'statusTone' => __('statuses.tone.' . $s->status),
                'assignee' => $s->assignee?->name, 'assignedTo' => $s->assigned_to,
                'dueAt' => $s->due_at?->format('Y-m-d H:i'), 'sla' => $sla, 'slaHours' => $hours,
                'createdAt' => $s->created_at?->format('Y-m-d H:i'), 'resolvedAt' => $s->resolved_at?->format('Y-m-d H:i'),
            ],
            // موجز الحملة كما أرسله العميل — هذا ما سينتقل حرفيًا عند التحويل
            'brief' => [
                'budgetMinor' => $s->budget_minor === null ? null : (int) $s->budget_minor,
                'currency' => $s->currency ?: 'SAR',
                'startDate' => $s->preferred_start_date?->format('Y-m-d'),
                'endDate' => $s->preferred_end_date?->format('Y-m-d'),
                'platforms' => collect($s->platforms ?? [])
                    ->map(fn ($p) => \App\Support\Platforms\PlatformRegistry::label($p))->values(),
                'scopeNotes' => $s->scope_notes,
                'brand' => $s->brand?->name,
                'hasAny' => $s->budget_minor !== null || $s->preferred_start_date !== null
                    || ! empty($s->platforms) || ! empty($s->scope_notes) || $s->brand_id !== null,
            ],
            'canHandle' => $canHandle,
            // إن حُوّل الطلب سابقًا نعرض الحملة الناتجة بدل تكرار التحويل
            'convertedCampaign' => ($cm = \App\Domain\Campaigns\Models\Campaign::where('source_request_id', $s->id)->first())
                ? ['id' => $cm->id, 'name' => $cm->name, 'number' => $cm->campaign_number]
                : null,
            // التحويل إلى حملة: طلب حملة لعميل معروف وغير ملغى، ولمن يملك إنشاء الحملات.
            // نحسبها هنا لأن الواجهة تستقبل تسمية النوع المترجمة لا مفتاحه.
            'canConvert' => $r->user()->can('create', Campaign::class)
                && $s->type === 'campaign' && $s->client_id && $s->status !== 'cancelled',
            'actions' => $canHandle ? (self::ACTIONS[$s->status] ?? []) : [],
            'agents' => $canHandle ? User::whereHas('memberships', fn ($m) => $m->where('tenant_id', $s->tenant_id)->where('status', 'active'))
                ->orderBy('name')->get(['id', 'name']) : [],
            'comments' => $s->comments->sortByDesc('id')->values()->map(fn ($c) => [
                'id' => $c->id, 'author' => $c->author?->name ?? '—', 'authorType' => $c->author_type,
                'body' => $c->body, 'internal' => (bool) $c->is_internal, 'at' => $c->created_at?->format('Y-m-d H:i'),
            ]),
            'history' => $s->statusHistory->sortByDesc('id')->values()->map(fn ($h) => [
                'from' => $h->from_status ? __('statuses.' . $h->from_status) : '—',
                'to' => __('statuses.' . $h->to_status), 'by' => $actorNames[$h->actor_id] ?? '—',
                'reason' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function assign(Request $r, ServiceRequest $serviceRequest, ServiceRequestWorkflowService $wf): RedirectResponse
    {
        $this->authorize('handle', $serviceRequest);
        $data = $r->validate(['assigned_to' => 'required|integer']);
        $ok = User::where('id', $data['assigned_to'])->whereHas('memberships', fn ($m) => $m->where('tenant_id', $serviceRequest->tenant_id)->where('status', 'active'))->exists();
        abort_unless($ok, 422);
        $wf->assign($serviceRequest, $data['assigned_to'], $r->user()->id);
        return back()->with('ok', 'أُسند الطلب.');
    }

    public function comment(Request $r, ServiceRequest $serviceRequest, ServiceRequestWorkflowService $wf): RedirectResponse
    {
        $this->authorize('handle', $serviceRequest);
        $data = $r->validate(['body' => 'required|string|max:2000']);
        $wf->comment($serviceRequest, $r->user()->id, 'agency', $data['body'], true);
        return back()->with('ok', 'أُضيف التعليق.');
    }

    /**
     * تحويل الطلب إلى حملة — الرابط الأول في سلسلة التشغيل (طلب ← حملة).
     * صلاحية إنشاء الحملة هي البوابة (لا صلاحية الطلب)، كما في نسخة Blade.
     */
    public function convertToCampaign(Request $r, ServiceRequest $serviceRequest, CampaignWorkflowService $wf): RedirectResponse
    {
        $this->authorize('create', Campaign::class);
        try {
            $campaign = $wf->convertFromRequest($serviceRequest, $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['convert' => $e->getMessage()]);
        }

        return redirect(MountPrefix::path($r, "/campaigns/{$campaign->id}"))->with('ok', 'حُوّل الطلب إلى حملة.');
    }

    public function transition(Request $r, ServiceRequest $serviceRequest, string $action, ServiceRequestWorkflowService $wf): RedirectResponse
    {
        $this->authorize('handle', $serviceRequest);
        $reason = $r->input('reason');
        try {
            match ($action) {
                'triage' => $wf->triage($serviceRequest, $r->user()->id),
                'start' => $wf->startWork($serviceRequest, $r->user()->id),
                'request-info' => $wf->requestInfo($serviceRequest, $r->user()->id, $r->validate(['reason' => 'required|string|max:500'])['reason']),
                'resolve' => $wf->resolve($serviceRequest, $r->user()->id, $reason),
                'close' => $wf->close($serviceRequest, $r->user()->id, $reason),
                'reopen' => $wf->reopen($serviceRequest, $r->user()->id, $reason),
                'cancel' => $wf->cancel($serviceRequest, $r->user()->id, $reason),
                default => abort(404),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }
        return back()->with('ok', 'حُدّثت حالة الطلب.');
    }
}
