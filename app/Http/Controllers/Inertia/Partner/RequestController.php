<?php

namespace App\Http\Controllers\Inertia\Partner;

use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\PartnerClientLink;
use App\Domain\Requests\Enums\{ServiceRequestPriority, ServiceRequestType};
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Requests\Services\ServiceRequestWorkflowService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * طلبات الشريك (React/Inertia) — مقيّدة بالعملاء المرتبطين نشطًا (fail-closed).
 * يعيد استخدام ServiceRequestWorkflowService. معزول على الوكالة الشريكة النشِطة.
 */
class RequestController extends Controller
{
    public function index(Request $r): Response
    {
        $a = $r->attributes->get('activeAgency');
        $items = ServiceRequest::with('client', 'assignee')->where('requester_type', 'partner')->where('requester_agency_id', $a->id)
            ->latest()->paginate(15)->through(fn (ServiceRequest $s) => $this->row($s));
        $open = ServiceRequest::where('requester_type', 'partner')->where('requester_agency_id', $a->id)
            ->whereIn('status', ServiceRequest::OPEN_STATUSES)->count();

        return Inertia::render('PartnerPortal/Requests/Index', [
            'agencyName' => $a->name,
            'items' => $items,
            'open' => $open,
            'clients' => $this->linkedClients($a)->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name])->values(),
            'types' => $this->options(ServiceRequestType::labels()),
            'priorities' => $this->options(ServiceRequestPriority::labels()),
        ]);
    }

    public function show(Request $r, int $request): Response
    {
        $s = $this->requestOf($r, $request);
        $s->load('assignee', 'statusHistory', 'client');
        $comments = $s->comments()->where('is_internal', false)->with('author')->get()->map(fn ($cm) => [
            'id' => $cm->id, 'author' => $cm->author?->name ?? ($cm->author_type === 'partner' ? 'أنت' : 'الوكالة'),
            'authorType' => $cm->author_type, 'body' => $cm->body, 'at' => $cm->created_at?->format('Y-m-d H:i'),
        ])->values();
        $actorIds = $s->statusHistory->pluck('actor_id')->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->pluck('name', 'id');
        $history = $s->statusHistory->sortByDesc('occurred_at')->take(12)->map(fn ($h) => [
            'to' => __("statuses.{$h->to_status}"), 'tone' => __("statuses.tone.{$h->to_status}"),
            'actor' => $actors[$h->actor_id] ?? 'النظام', 'note' => $h->reason,
            'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();

        return Inertia::render('PartnerPortal/Requests/Show', [
            'agencyName' => $r->attributes->get('activeAgency')->name,
            'request' => $this->row($s) + [
                'description' => $s->description, 'client' => $s->client?->display_name,
                'createdAt' => $s->created_at?->format('Y-m-d H:i'), 'dueAt' => $s->due_at?->format('Y-m-d H:i'),
                'isOpen' => in_array($s->status, ServiceRequest::OPEN_STATUSES, true),
            ],
            'comments' => $comments,
            'history' => $history,
        ]);
    }

    public function store(Request $r, ServiceRequestWorkflowService $wf)
    {
        $a = $r->attributes->get('activeAgency');
        $data = $r->validate([
            'client_id' => 'required|integer',
            'type' => 'required|in:' . implode(',', ServiceRequestType::values()),
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:4000',
            'priority' => 'required|in:' . implode(',', ServiceRequestPriority::values()),
        ]);
        abort_unless($this->linkedClients($a)->contains('id', (int) $data['client_id']), 422, 'لا يمكن إنشاء طلب لعميل غير مرتبط بك.');
        $wf->create($a->tenant_id, $data + ['requester_type' => 'partner', 'requester_agency_id' => $a->id], $r->user()->id);
        return redirect(MountPrefix::path($r, '/requests'))->with('ok', 'أُرسل طلب الخدمة.');
    }

    public function comment(Request $r, int $request, ServiceRequestWorkflowService $wf)
    {
        $s = $this->requestOf($r, $request);
        $data = $r->validate(['body' => 'required|string|max:2000']);
        $wf->comment($s, $r->user()->id, 'partner', $data['body'], false);
        return back()->with('ok', 'أُضيف تعليقك.');
    }

    private function linkedClients($a)
    {
        $links = PartnerClientLink::with('client')->where('external_agency_id', $a->id)->where('status', 'active')->get();
        return $links->pluck('client')->filter()->unique('id')->values();
    }

    private function requestOf(Request $r, int $id): ServiceRequest
    {
        $a = $r->attributes->get('activeAgency');
        $s = ServiceRequest::where('id', $id)->where('requester_type', 'partner')->where('requester_agency_id', $a->id)->first();
        abort_unless($s, 404);
        return $s;
    }

    private function row(ServiceRequest $s): array
    {
        return [
            'id' => $s->id, 'number' => $s->request_number, 'title' => $s->title,
            'type' => $s->type, 'typeLabel' => ServiceRequestType::labels()[$s->type] ?? $s->type,
            'priority' => $s->priority, 'priorityLabel' => ServiceRequestPriority::labels()[$s->priority] ?? $s->priority,
            'clientName' => $s->client?->display_name,
            'status' => $s->status, 'statusLabel' => __("statuses.{$s->status}"), 'statusTone' => __("statuses.tone.{$s->status}"),
            'assignee' => $s->assignee?->name,
        ];
    }

    /** @param array<string,string> $labels */
    private function options(array $labels): array
    {
        return collect($labels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
