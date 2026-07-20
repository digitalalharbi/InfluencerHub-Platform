<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Collaborations\Services\CollaborationWorkflowService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * قائمة التعاونات (React/Inertia) — KPIs + شرائح حالة + بحث. Policy(viewAny)، معزولة.
 */
class CollaborationsController extends Controller
{
    private const ACTIVE = ['accepted', 'in_progress', 'submitted', 'approved'];

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Collaboration::class);

        $q = Collaboration::query()->with('creator', 'campaign')->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('title', 'ilike', "%{$s}%")->orWhere('collaboration_number', 'ilike', "%{$s}%")
                ->orWhereHas('creator', fn ($c) => $c->where('display_name', 'ilike', "%{$s}%")));
        }
        match ($r->query('seg')) {
            'active' => $q->whereIn('status', self::ACTIVE),
            'offered', 'accepted', 'declined', 'in_progress', 'submitted', 'approved', 'completed', 'cancelled' => $q->where('status', $r->query('seg')),
            default => null,
        };

        $collaborations = $q->paginate(20)->withQueryString();
        $collaborations->through(fn (Collaboration $c) => [
            'id' => $c->id,
            'number' => $c->collaboration_number,
            'title' => $c->title,
            'creator' => $c->creator?->display_name,
            'campaign' => $c->campaign?->name,
            'feeMinor' => (int) $c->fee_minor,
            'currency' => $c->currency,
            'dueDate' => $c->due_date?->format('Y-m-d'),
            'status' => $c->status,
            'statusLabel' => __('statuses.' . $c->status),
            'statusTone' => __('statuses.tone.' . $c->status),
            'needsApproval' => $c->status === 'submitted',
            'overdue' => (bool) ($c->due_date && $c->due_date->isPast() && ! in_array($c->status, ['completed', 'cancelled', 'declined'], true)),
            // مرحلة دورة التعاون
            'stage' => in_array($c->status, ['offered', 'accepted'], true) ? 'offered'
                : (in_array($c->status, ['in_progress', 'submitted'], true) ? 'progress'
                : (in_array($c->status, ['completed'], true) ? 'done' : 'closed')),
        ]);

        // عدّادات الحالات في استعلام تجميعي واحد بدل استعلام لكل حالة
        $byStatus = Collaboration::query()->groupBy('status')->selectRaw('status, count(*) as c')->pluck('c', 'status');
        $count = fn (string $st) => (int) ($byStatus[$st] ?? 0);
        $canCreate = $r->user()->can('create', Collaboration::class);
        return Inertia::render('Collaborations/Index', [
            'collaborations' => $collaborations,
            'filters' => $r->only('q', 'seg'),
            'canCreate' => $canCreate,
            'creatorOptions' => $canCreate
                ? Creator::query()->orderBy('display_name')->get(['id', 'display_name'])
                    ->map(fn (Creator $c) => ['id' => $c->id, 'name' => $c->display_name])->values()
                : [],
            'summary' => [
                'total' => (int) $byStatus->sum(),
                'active' => Collaboration::query()->whereIn('status', self::ACTIVE)->count(),
                'offered' => $count('offered'),
                'submitted' => $count('submitted'),
                'approved' => $count('approved'),
                'completed' => $count('completed'),
                'declined' => $count('declined'),
                'committedMinor' => (int) Collaboration::query()->whereIn('status', self::ACTIVE)->sum('fee_minor'),
            ],
        ]);
    }

    /**
     * عرض تعاون على مبدع — نفس تحقّق نسخة Blade وCollaborationWorkflowService نفسه.
     * الأجر بوحدات صغرى صحيحة، ويُصفَّر إن لم يُحدَّد (عرض بلا أجر متفَق عليه بعد).
     */
    public function store(Request $r, CollaborationWorkflowService $wf): RedirectResponse
    {
        $this->authorize('create', Collaboration::class);
        $data = $r->validate([
            'creator_id' => 'required|integer',
            'title' => 'required|string|max:160',
            'brief' => 'nullable|string|max:4000',
            'fee_minor' => 'nullable|integer|min:0',
            'due_date' => 'nullable|date',
        ]);
        Creator::findOrFail($data['creator_id']); // ضمن المستأجر

        try {
            $c = $wf->offer(TenantContext::tenantId(), $data + ['fee_minor' => $data['fee_minor'] ?? 0], $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        return redirect(MountPrefix::path($r, "/collaborations/{$c->id}"))->with('ok', 'أُرسل عرض التعاون.');
    }
}
