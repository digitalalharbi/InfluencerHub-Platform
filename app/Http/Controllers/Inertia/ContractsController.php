<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Contracts\Models\Contract;
use App\Domain\Contracts\Services\ContractWorkflowService;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * قائمة العقود (React/Inertia) — KPIs + شرائح حالة + بحث. Policy(viewAny)، معزولة.
 */
class ContractsController extends Controller
{
    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Contract::class);

        $q = Contract::query()->with('creator', 'client')->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('title', 'ilike', "%{$s}%")->orWhere('contract_number', 'ilike', "%{$s}%"));
        }
        match ($r->query('seg')) {
            'draft', 'sent', 'signed', 'active', 'completed', 'terminated', 'cancelled' => $q->where('status', $r->query('seg')),
            default => null,
        };

        $contracts = $q->paginate(20)->withQueryString();
        $contracts->through(fn (Contract $c) => [
            'id' => $c->id,
            'number' => $c->contract_number,
            'title' => $c->title,
            'party' => $c->party_type === 'creator' ? ($c->creator?->display_name) : ($c->client?->display_name),
            'partyType' => $c->party_type === 'creator' ? 'مبدع' : 'عميل',
            'valueMinor' => (int) $c->value_minor,
            'currency' => $c->currency,
            'endDate' => $c->end_date?->format('Y-m-d'),
            'status' => $c->status,
            'statusLabel' => __('statuses.' . $c->status),
            'statusTone' => __('statuses.tone.' . $c->status),
            'sentAt' => $c->sent_at?->format('Y-m-d'),
            'signedAt' => $c->signed_at?->format('Y-m-d'),
            'startDate' => $c->start_date?->format('Y-m-d'),
            'expiringSoon' => (bool) ($c->end_date && $c->end_date->isFuture() && $c->end_date->diffInDays(now()) <= 30),
            'expired' => (bool) ($c->end_date && $c->end_date->isPast()),
            'bucket' => $c->status === 'sent' ? 'awaiting'
                : (in_array($c->status, ['signed', 'active'], true) ? 'active'
                : (in_array($c->status, ['completed', 'terminated', 'cancelled'], true) ? 'closed' : 'draft')),
        ]);

        // عدّادات الحالات في استعلام تجميعي واحد بدل استعلام لكل حالة
        $byStatus = Contract::query()->groupBy('status')->selectRaw('status, count(*) as c')->pluck('c', 'status');
        $count = fn (string $st) => (int) ($byStatus[$st] ?? 0);
        $sum = fn (array $st) => (int) Contract::query()->whereIn('status', $st)->sum('value_minor');
        $canCreate = $r->user()->can('create', Contract::class);
        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'filters' => $r->only('q', 'seg'),
            'canCreate' => $canCreate,
            // أطراف العقد المحتملة — لا تُحمّل لمن لا يملك الإنشاء
            'creatorOptions' => $canCreate
                ? Creator::query()->orderBy('display_name')->get(['id', 'display_name'])
                    ->map(fn (Creator $c) => ['id' => $c->id, 'name' => $c->display_name])->values()
                : [],
            'clientOptions' => $canCreate
                ? Client::query()->orderBy('display_name')->get(['id', 'display_name'])
                    ->map(fn (Client $c) => ['id' => $c->id, 'name' => $c->display_name])->values()
                : [],
            'summary' => [
                'total' => (int) $byStatus->sum(),
                'active' => $count('active'),
                'sent' => $count('sent'),
                'signed' => $count('signed'),
                'draft' => $count('draft'),
                'completed' => $count('completed'),
                'terminated' => $count('terminated'),
                'cancelled' => $count('cancelled'),
                'activeValueMinor' => $sum(['active', 'signed']),
            ],
        ]);
    }

    /**
     * إنشاء عقد (مسودة) — نفس تحقّق نسخة Blade وContractWorkflowService نفسه.
     * الطرف يُتحقّق من انتمائه للمستأجر قبل الإنشاء (يمنع الربط بطرف خارجي).
     */
    public function store(Request $r, ContractWorkflowService $wf): RedirectResponse
    {
        $this->authorize('create', Contract::class);
        $data = $r->validate([
            'party_type' => 'required|in:creator,client',
            'creator_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
            'title' => 'required|string|max:160',
            'terms' => 'nullable|string|max:20000',
            'value_minor' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|size:3',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($data['party_type'] === 'creator') {
            Creator::findOrFail($data['creator_id'] ?? 0);
        } else {
            Client::findOrFail($data['client_id'] ?? 0);
        }

        try {
            $c = $wf->create(TenantContext::tenantId(), $data, $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect(MountPrefix::path($r, "/contracts/{$c->id}"))->with('ok', 'أُنشئ العقد (مسودة).');
    }
}
