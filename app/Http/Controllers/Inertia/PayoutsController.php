<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Services\PayoutWorkflowService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * قائمة المستحقات (React/Inertia) — KPIs مالية + شرائح حالة + بحث. Policy(viewAny)، معزولة.
 * النظام لا ينفّذ تحويلات؛ "مدفوع" تُسجَّل يدويًا (راجع docs/EXTERNAL-BLOCKERS.md).
 */
class PayoutsController extends Controller
{
    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Payout::class);

        $q = Payout::query()->with('creator', 'campaign')->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('payout_number', 'ilike', "%{$s}%")
                ->orWhereHas('creator', fn ($c) => $c->where('display_name', 'ilike', "%{$s}%")));
        }
        match ($r->query('seg')) {
            'open' => $q->whereIn('status', Payout::OPEN),
            'ready' => $q->whereIn('status', ['approved', 'scheduled']),
            'pending', 'approved', 'scheduled', 'waiting_for_provider', 'paid', 'failed', 'cancelled' => $q->where('status', $r->query('seg')),
            default => null,
        };

        $payouts = $q->paginate(20)->withQueryString();
        $payouts->through(fn (Payout $p) => [
            'id' => $p->id,
            'number' => $p->payout_number,
            'creator' => $p->creator?->display_name,
            'amountMinor' => (int) $p->amount_minor,
            'currency' => $p->currency,
            'ibanLast4' => $p->iban_last4,
            'dueDate' => $p->due_date?->format('Y-m-d'),
            'status' => $p->status,
            'statusLabel' => __('statuses.' . $p->status),
            'statusTone' => __('statuses.tone.' . $p->status),
            'campaign' => $p->campaign?->name,
            'paidAt' => $p->paid_at?->format('Y-m-d'),
            'overdue' => (bool) ($p->due_date && $p->due_date->isPast() && ! in_array($p->status, ['paid', 'cancelled'], true)),
            'bucket' => in_array($p->status, ['paid'], true) ? 'paid'
                : (in_array($p->status, ['approved', 'scheduled', 'waiting_for_provider'], true) ? 'ready'
                : ($p->status === 'pending' ? 'pending' : 'closed')),
        ]);

        // عدّادات الحالات في استعلام تجميعي واحد بدل استعلام لكل حالة
        $byStatus = Payout::query()->groupBy('status')
            ->selectRaw('status, count(*) as c, sum(amount_minor) as s')->get()
            ->keyBy('status');
        $count = fn (string $st) => (int) ($byStatus[$st]->c ?? 0);
        $sumOf = fn (array $sts) => (int) collect($sts)->sum(fn ($st) => (int) ($byStatus[$st]->s ?? 0));
        $countOf = fn (array $sts) => (int) collect($sts)->sum(fn ($st) => (int) ($byStatus[$st]->c ?? 0));
        $canCreate = $r->user()->can('create', Payout::class);
        return Inertia::render('Payouts/Index', [
            'payouts' => $payouts,
            'filters' => $r->only('q', 'seg'),
            'canCreate' => $canCreate,
            // خيارات نموذج الإنشاء — لا تُحمَّل لمن لا يملك الصلاحية
            'creatorOptions' => $canCreate
                ? Creator::query()->orderBy('display_name')->get(['id', 'display_name'])
                    ->map(fn (Creator $c) => ['id' => $c->id, 'name' => $c->display_name])->values()
                : [],
            'summary' => [
                'total' => (int) $byStatus->sum('c'),
                'openMinor' => $sumOf(Payout::OPEN),
                'openCount' => $countOf(Payout::OPEN),
                'readyMinor' => $sumOf(['approved', 'scheduled']),
                'readyCount' => $countOf(['approved', 'scheduled']),
                'paidMinor' => $sumOf(['paid']),
                'pending' => $count('pending'),
                'waiting' => $count('waiting_for_provider'),
                'failed' => $count('failed'),
                'paid' => $count('paid'),
            ],
        ]);
    }

    /**
     * إنشاء مستحق — نفس تحقّق نسخة Blade وPayoutWorkflowService نفسه.
     * المبالغ بوحدات صغرى صحيحة (هللات)، ولا ينفّذ النظام أي تحويل مالي فعلي.
     */
    public function store(Request $r, PayoutWorkflowService $wf): RedirectResponse
    {
        $this->authorize('create', Payout::class);
        $data = $r->validate([
            'creator_id' => 'required|integer',
            'amount_minor' => 'required|integer|min:1',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string|max:200',
            'due_date' => 'nullable|date',
        ]);
        Creator::findOrFail($data['creator_id']); // ضمن المستأجر — يمنع الإسناد لمبدع خارجي

        try {
            $payout = $wf->create(TenantContext::tenantId(), $data, $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payout' => $e->getMessage()]);
        }

        return redirect(MountPrefix::path($r, "/payouts/{$payout->id}"))->with('ok', 'أُنشئ المستحق.');
    }
}
