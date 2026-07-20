<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\CRM\Models\Brand;
use App\Domain\Identity\Models\User;
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
 * طلبات الخدمة — بوابة العميل (React/Inertia). إنشاء/عرض/محادثة (عامة فقط).
 * يعيد استخدام ServiceRequestWorkflowService. معزول على العميل النشِط.
 */
class RequestController extends Controller
{
    public function index(Request $r): Response
    {
        $c = $r->attributes->get('activeClient');
        $items = ServiceRequest::with('assignee')->where('requester_type', 'client')->where('requester_client_id', $c->id)
            ->latest()->paginate(15)->through(fn (ServiceRequest $s) => $this->row($s));
        $open = ServiceRequest::where('requester_type', 'client')->where('requester_client_id', $c->id)
            ->whereIn('status', ServiceRequest::OPEN_STATUSES)->count();
        $brands = Brand::where('client_id', $c->id)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values();

        return Inertia::render('ClientPortal/Requests/Index', [
            'clientName' => $c->display_name,
            'items' => $items,
            'open' => $open,
            'brands' => $brands,
            'types' => $this->options(ServiceRequestType::labels()),
            'priorities' => $this->options(ServiceRequestPriority::labels()),
            // خيارات المنصّات من السجلّ — لا قائمة ثابتة في الواجهة
            'platformOptions' => \App\Support\Platforms\PlatformRegistry::options('influencer_campaign'),
        ]);
    }

    public function show(Request $r, int $request): Response
    {
        $s = $this->requestOf($r, $request);
        $s->load('assignee', 'statusHistory', 'brand');
        $comments = $s->comments()->where('is_internal', false)->with('author')->get()->map(fn ($cm) => [
            'id' => $cm->id, 'author' => $cm->author?->name ?? ($cm->author_type === 'client' ? 'أنت' : 'الوكالة'),
            'authorType' => $cm->author_type, 'body' => $cm->body,
            'at' => $cm->created_at?->format('Y-m-d H:i'),
        ])->values();
        $actorIds = $s->statusHistory->pluck('actor_id')->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->pluck('name', 'id');
        $history = $s->statusHistory->sortByDesc('occurred_at')->take(12)->map(fn ($h) => [
            'to' => __("statuses.{$h->to_status}"), 'tone' => __("statuses.tone.{$h->to_status}"),
            'actor' => $actors[$h->actor_id] ?? 'النظام', 'note' => $h->reason,
            'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();

        return Inertia::render('ClientPortal/Requests/Show', [
            'clientName' => $r->attributes->get('activeClient')->display_name,
            'request' => $this->row($s) + [
                'description' => $s->description, 'brand' => $s->brand?->name,
                'createdAt' => $s->created_at?->format('Y-m-d H:i'),
                'dueAt' => $s->due_at?->format('Y-m-d H:i'),
                'isOpen' => in_array($s->status, ServiceRequest::OPEN_STATUSES, true),
            ],
            'comments' => $comments,
            'history' => $history,
        ]);
    }

    public function store(Request $r, ServiceRequestWorkflowService $wf)
    {
        $c = $r->attributes->get('activeClient');
        $data = $r->validate([
            'type' => 'required|in:' . implode(',', ServiceRequestType::values()),
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:4000',
            'priority' => 'required|in:' . implode(',', ServiceRequestPriority::values()),
            'brand_id' => 'nullable|integer',
            // موجز الحملة — اختياري، ويُطلب فقط حين يكون النوع «حملة»
            'budget' => 'nullable|numeric|min:0',
            'preferred_start_date' => 'nullable|date',
            'preferred_end_date' => 'nullable|date|after_or_equal:preferred_start_date',
            'platforms' => 'nullable|array',
            'platforms.*' => 'string|max:40',
            'scope_notes' => 'nullable|string|max:2000',
        ]);

        // الريال يُدخله المستخدم، والتخزين بوحدات صغرى صحيحة
        $brief = [
            'budget_minor' => isset($data['budget']) ? (int) round($data['budget'] * 100) : null,
            'currency' => isset($data['budget']) ? 'SAR' : null,
            'preferred_start_date' => $data['preferred_start_date'] ?? null,
            'preferred_end_date' => $data['preferred_end_date'] ?? null,
            'platforms' => $data['platforms'] ?? null,
            'scope_notes' => $data['scope_notes'] ?? null,
        ];
        unset($data['budget'], $data['preferred_start_date'], $data['preferred_end_date'], $data['platforms'], $data['scope_notes']);
        if (! empty($data['brand_id'])) {
            $owns = Brand::where('id', $data['brand_id'])->where('client_id', $c->id)->exists();
            abort_unless($owns, 422);
        }
        $wf->create($c->tenant_id, $data + $brief + ['requester_type' => 'client', 'requester_client_id' => $c->id, 'client_id' => $c->id], $r->user()->id);
        return redirect(MountPrefix::path($r, '/requests'))->with('ok', 'أُرسل طلب الخدمة.');
    }

    public function comment(Request $r, int $request, ServiceRequestWorkflowService $wf)
    {
        $s = $this->requestOf($r, $request);
        $data = $r->validate(['body' => 'required|string|max:2000']);
        $wf->comment($s, $r->user()->id, 'client', $data['body'], false);
        return back()->with('ok', 'أُضيف تعليقك.');
    }

    private function requestOf(Request $r, int $id): ServiceRequest
    {
        $c = $r->attributes->get('activeClient');
        $s = ServiceRequest::where('id', $id)->where('requester_type', 'client')->where('requester_client_id', $c->id)->first();
        abort_unless($s, 404);
        return $s;
    }

    private function row(ServiceRequest $s): array
    {
        return [
            'id' => $s->id, 'number' => $s->request_number, 'title' => $s->title,
            'type' => $s->type, 'typeLabel' => ServiceRequestType::labels()[$s->type] ?? $s->type,
            'priority' => $s->priority, 'priorityLabel' => ServiceRequestPriority::labels()[$s->priority] ?? $s->priority,
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
