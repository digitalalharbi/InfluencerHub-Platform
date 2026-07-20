<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Requests\Enums\{ServiceRequestPriority, ServiceRequestType};
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Requests\Services\ServiceRequestWorkflowService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * طابور طلبات الخدمة (React/Inertia) — فرز/إسناد/SLA. أغنى من نسخة Blade:
 * مؤشرات (مفتوحة/متجاوزة/غير مسندة/اليوم/مسندة لي) + شرائح + بحث + عدّاد SLA لكل طلب.
 * Policy(viewAny)، معزولة بالمستأجر.
 */
class ServiceRequestsController extends Controller
{
    public function index(Request $r): Response
    {
        $this->authorize('viewAny', ServiceRequest::class);
        $uid = $r->user()->id;
        $open = ServiceRequest::OPEN_STATUSES;

        $q = ServiceRequest::query()->with('client', 'assignee', 'requesterAgency')->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('title', 'ilike', "%{$s}%")->orWhere('request_number', 'ilike', "%{$s}%")
                ->orWhereHas('client', fn ($c) => $c->where('display_name', 'ilike', "%{$s}%")));
        }
        if ($v = $r->query('priority')) $q->where('priority', $v);

        // شرائح تشغيلية
        match ($r->query('seg')) {
            'mine' => $q->where('assigned_to', $uid)->whereIn('status', $open),
            'unassigned' => $q->whereNull('assigned_to')->whereIn('status', $open),
            'breached' => $q->whereNotNull('sla_breached_at')->whereIn('status', $open),
            'open' => $q->whereIn('status', $open),
            'resolved' => $q->where('status', 'resolved'),
            'triage', 'in_progress', 'needs_info' => $q->where('status', $r->query('seg')),
            default => null,
        };

        $requests = $q->paginate(20)->withQueryString();
        $prioLabels = ServiceRequestPriority::labels();
        $typeLabels = ServiceRequestType::labels();
        $now = Carbon::now();

        $requests->through(function (ServiceRequest $s) use ($now, $open, $prioLabels, $typeLabels) {
            $isOpen = in_array($s->status, $open, true);
            $sla = 'none';
            $hours = null;
            if ($s->due_at) {
                $hours = (int) round($now->diffInHours($s->due_at, false));
                if ($s->sla_breached_at || ($isOpen && $s->due_at->isPast())) $sla = 'overdue';
                elseif ($isOpen && $s->due_at->lte($now->copy()->addHours(24))) $sla = 'soon';
                else $sla = 'ok';
            }
            return [
                'id' => $s->id,
                'number' => $s->request_number,
                'title' => $s->title,
                'client' => $s->client?->display_name ?? $s->requesterAgency?->name,
                'type' => $typeLabels[$s->type] ?? $s->type,
                'priority' => $s->priority,
                'priorityLabel' => $prioLabels[$s->priority] ?? $s->priority,
                'status' => $s->status,
                'statusLabel' => __('statuses.' . $s->status),
                'statusTone' => __('statuses.tone.' . $s->status),
                'assignee' => $s->assignee?->name,
                'dueAt' => $s->due_at?->format('Y-m-d H:i'),
                'sla' => $sla,
                'slaHours' => $hours,
                // دلو الفرز + سبب التعطل الفعلي
                'bucket' => $sla === 'overdue' ? 'overdue' : ($s->status === 'submitted' ? 'new' : (in_array($s->status, ServiceRequest::OPEN_STATUSES, true) ? 'open' : 'done')),
                'blocked' => $sla === 'overdue' ? 'تجاوز مهلة SLA'
                    : ($s->status === 'needs_info' ? 'بانتظار معلومة'
                    : (! $s->assigned_to && in_array($s->status, ServiceRequest::OPEN_STATUSES, true) ? 'غير مُسنَد' : null)),
            ];
        });

        $base = fn () => ServiceRequest::query();
        // خيارات الإنشاء: العلامات مربوطة بعميلها لتُرشَّح تلقائيًّا في النموذج
        $clients = Client::query()->orderBy('display_name')->get(['id', 'display_name']);
        $brands = Brand::query()->orderBy('name')->get(['id', 'name', 'client_id']);

        return Inertia::render('ServiceRequests/Index', [
            'requests' => $requests,
            'filters' => $r->only('q', 'priority', 'seg'),
            'priorityLabels' => $prioLabels,
            'canCreate' => $r->user()->can('create', ServiceRequest::class),
            'options' => [
                'clients' => $clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name]),
                'brands' => $brands->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'clientId' => $b->client_id]),
                'types' => ServiceRequestType::labels(),
                'priorities' => $prioLabels,
                'platforms' => \App\Support\Platforms\PlatformRegistry::options(),
            ],
            'summary' => [
                'open' => (clone $base())->whereIn('status', $open)->count(),
                'breached' => (clone $base())->whereNotNull('sla_breached_at')->whereIn('status', $open)->count(),
                'unassigned' => (clone $base())->whereNull('assigned_to')->whereIn('status', $open)->count(),
                'dueToday' => (clone $base())->whereIn('status', $open)->whereNotNull('due_at')
                    ->whereDate('due_at', $now->toDateString())->count(),
                'mine' => (clone $base())->where('assigned_to', $uid)->whereIn('status', $open)->count(),
                'triage' => (clone $base())->where('status', 'triage')->count(),
                'in_progress' => (clone $base())->where('status', 'in_progress')->count(),
                'needs_info' => (clone $base())->where('status', 'needs_info')->count(),
                'resolved' => (clone $base())->where('status', 'resolved')->count(),
            ],
        ]);
    }

    /**
     * إنشاء طلب من جهة الوكالة — نيابةً عن العميل.
     *
     * الطلبات تصل عادةً من بوابة العميل، لكن كثيرًا ما تصل بالهاتف أو البريد،
     * فكان طابور الطلبات بلا أي مدخل: صفحة تَعِد بطلبات ولا تتيح تسجيل واحد.
     *
     * يُلتقط موجز الحملة هنا كاملًا لأنه ينتقل حرفيًّا عند التحويل إلى حملة،
     * فلا يُعاد إدخال ما قاله العميل مرّة أخرى.
     */
    public function store(Request $r, ServiceRequestWorkflowService $wf): RedirectResponse
    {
        $this->authorize('create', ServiceRequest::class);

        $data = $r->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'type' => 'required|string|in:' . implode(',', array_keys(ServiceRequestType::labels())),
            'title' => 'required|string|max:190',
            'description' => 'nullable|string|max:5000',
            'priority' => 'required|string|in:' . implode(',', array_keys(ServiceRequestPriority::labels())),
            'budget_riyals' => 'nullable|numeric|min:0',
            'preferred_start_date' => 'nullable|date',
            'preferred_end_date' => 'nullable|date|after_or_equal:preferred_start_date',
            'platforms' => 'nullable|array',
            'platforms.*' => 'string|max:40',
            'scope_notes' => 'nullable|string|max:2000',
        ], [], [
            'client_id' => 'العميل', 'title' => 'عنوان الطلب', 'type' => 'نوع الطلب',
            'preferred_end_date' => 'تاريخ النهاية',
        ]);

        // العميل والعلامة يُتحقّقان داخل المستأجر: مُعرّف من مستأجر آخر يُرفض
        $client = Client::findOrFail($data['client_id']);
        if (! empty($data['brand_id'])) {
            $brand = Brand::findOrFail($data['brand_id']);
            abort_unless($brand->client_id === $client->id, 422, 'العلامة لا تتبع هذا العميل.');
        }

        $sr = $wf->create((int) $client->tenant_id, [
            // مُقدِّم الطلب هو العميل حتى وإن سجّلته الوكالة نيابةً عنه: الطلب
            // يخصّه ويظهر في بوابته، والوكالة ناقلة لا صاحبة طلب.
            'requester_type' => 'client',
            'requester_client_id' => $client->id,
            'client_id' => $client->id,
            'brand_id' => $data['brand_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            // الريالات تُحوَّل إلى وحدات صغرى عند الحدّ لا في العرض
            'budget_minor' => isset($data['budget_riyals']) ? (int) round($data['budget_riyals'] * 100) : null,
            'currency' => 'SAR',
            'preferred_start_date' => $data['preferred_start_date'] ?? null,
            'preferred_end_date' => $data['preferred_end_date'] ?? null,
            'platforms' => $data['platforms'] ?? null,
            'scope_notes' => $data['scope_notes'] ?? null,
        ], (int) $r->user()->id);

        return redirect(\App\Support\Http\MountPrefix::path($r, "/service-requests/{$sr->id}"))
            ->with('ok', 'سُجّل الطلب. الخطوة التالية: الفرز ثم التحويل إلى حملة.');
    }
}
