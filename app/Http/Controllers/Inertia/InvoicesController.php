<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\Client;
use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\Finance\Models\{Invoice, InvoicePayment};
use App\Domain\Finance\Services\InvoiceService;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\{RedirectResponse, Request};
use Inertia\{Inertia, Response};

/**
 * فواتير العملاء.
 *
 * كانت «المالية» تحوي المستحقات وحدها: يُصرَف للمبدع ولا يُطالَب العميل، فلا
 * تُغلق حملة ماليًّا. هذه الوحدة تُكمل الطرف الآخر من الدفتر.
 *
 * التحصيل يُسجَّل ولا يُنفَّذ: لا مزوّد دفع مربوط، وتسجيل حوالة وقعت في البنك
 * حقيقة محاسبية — أمّا ادّعاء أن النظام حصّلها فليس كذلك.
 */
class InvoicesController extends Controller
{
    public function __construct(private InvoiceService $svc)
    {
    }

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $seg = $r->query('seg');
        $q = Invoice::with('client', 'campaign')->latest('id');

        if ($seg === 'open') {
            $q->whereIn('status', Invoice::OPEN);
        } elseif ($seg && $seg !== 'all') {
            $q->where('status', $seg);
        }
        if ($term = $r->query('q')) {
            $q->where(fn ($w) => $w->where('invoice_number', 'ilike', "%{$term}%")
                ->orWhereHas('client', fn ($c) => $c->where('display_name', 'ilike', "%{$term}%")));
        }

        $count = fn (array|string $st) => Invoice::whereIn('status', (array) $st)->count();

        return Inertia::render('Invoices/Index', [
            'invoices' => $q->paginate(20)->through(fn (Invoice $i) => $this->row($i)),
            'filters' => $r->only('q', 'seg'),
            'canCreate' => $r->user()->can('create', Invoice::class),
            // معاينة الإنشاء تحسب الضريبة، فتأخذ النسبة من مصدرها لا من رقم ثابت
            'defaultTaxRateBp' => \App\Domain\Finance\Services\InvoiceService::DEFAULT_TAX_RATE_BP,
            'summary' => [
                'total' => Invoice::count(),
                'draft' => $count('draft'),
                'open' => $count(Invoice::OPEN),
                'paid' => $count('paid'),
                // المستحقّ = ما صدر ولم يُحصَّل. يُحسب من الدفتر لا من حقل مخزَّن.
                'outstandingMinor' => $this->outstandingMinor(),
                'collectedMinor' => (int) InvoicePayment::sum('amount_minor'),
            ],
            'options' => $this->options(),
        ]);
    }

    public function show(Request $r, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $invoice->load('client', 'campaign', 'brand', 'items', 'payments', 'statusHistory');

        $canManage = $r->user()->can('manage', $invoice);

        return Inertia::render('Invoices/Show', [
            'invoice' => $this->row($invoice) + [
                'notes' => $invoice->notes,
                'cancelReason' => $invoice->cancel_reason,
                'taxRateBp' => $invoice->tax_rate_bp,
                'subtotalMinor' => $invoice->subtotal_minor,
                'discountMinor' => $invoice->discount_minor,
                'taxMinor' => $invoice->tax_minor,
                'campaignId' => $invoice->campaign_id,
                'clientId' => $invoice->client_id,
            ],
            'items' => $invoice->items->map(fn ($i) => [
                'id' => $i->id, 'description' => $i->description, 'quantity' => $i->quantity,
                'unitPriceMinor' => $i->unit_price_minor, 'lineTotalMinor' => $i->line_total_minor,
            ]),
            'payments' => $invoice->payments->sortByDesc('received_at')->values()->map(fn ($p) => [
                'id' => $p->id, 'amountMinor' => $p->amount_minor, 'method' => $p->method,
                'methodLabel' => $p->methodLabel(), 'reference' => $p->provider_reference,
                'receivedAt' => $p->received_at?->format('Y-m-d'), 'note' => $p->note,
            ]),
            'history' => $invoice->statusHistory->sortByDesc('id')->values()->map(fn ($h) => [
                'from' => $h->from_status ? __('statuses.' . $h->from_status) : '—',
                'to' => __('statuses.' . $h->to_status),
                'reason' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
            ]),
            // كل زرّ بصلاحيته: الواجهة تعكس ما يسمح به الخادم لا أكثر
            'can' => [
                'edit' => $canManage && $invoice->isEditable(),
                'issue' => $canManage && $invoice->status === 'draft',
                'pay' => $r->user()->can('recordPayment', $invoice)
                    && in_array($invoice->status, Invoice::OPEN, true),
                'cancel' => $r->user()->can('cancel', $invoice)
                    && in_array($invoice->status, ['draft', ...Invoice::OPEN], true),
            ],
            'paymentMethods' => InvoicePayment::METHODS,
        ]);
    }

    public function store(Request $r): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $data = $this->validated($r);

        $client = Client::findOrFail($data['client_id']);
        $this->assertCampaignBelongsToClient($data['campaign_id'] ?? null, $client);

        $invoice = $this->svc->create((int) $client->tenant_id, $data, $data['items'], (int) $r->user()->id);

        return redirect(MountPrefix::path($r, "/invoices/{$invoice->id}"))
            ->with('ok', 'أُنشئت المسوّدة. الخطوة التالية: مراجعة البنود ثم الإصدار.');
    }

    public function update(Request $r, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $data = $this->validated($r, $invoice);

        try {
            $this->svc->updateDraft($invoice, $data, $data['items'], (int) $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُدّثت المسوّدة.');
    }

    public function issue(Request $r, Invoice $invoice): RedirectResponse
    {
        $this->authorize('manage', $invoice);

        try {
            $this->svc->issue($invoice, (int) $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('ok', 'صدرت الفاتورة. الخطوة التالية: متابعة التحصيل.');
    }

    public function pay(Request $r, Invoice $invoice): RedirectResponse
    {
        // صلاحية مستقلّة: مَن يُصدر الفاتورة ليس بالضرورة مَن يُقرّ تحصيلها
        $this->authorize('recordPayment', $invoice);

        $data = $r->validate([
            'amount_riyals' => 'required|numeric|min:0.01',
            'method' => 'required|string|in:' . implode(',', array_keys(InvoicePayment::METHODS)),
            'received_at' => 'required|date',
            'provider_reference' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
        ], [], ['amount_riyals' => 'المبلغ', 'method' => 'طريقة الدفع', 'received_at' => 'تاريخ الاستلام']);

        try {
            $this->svc->recordPayment($invoice, [
                'amount_minor' => (int) round($data['amount_riyals'] * 100),
                'method' => $data['method'],
                'received_at' => $data['received_at'],
                'provider_reference' => $data['provider_reference'] ?? null,
                'note' => $data['note'] ?? null,
            ], (int) $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('ok', 'سُجّلت الدفعة.');
    }

    public function cancel(Request $r, Invoice $invoice): RedirectResponse
    {
        $this->authorize('cancel', $invoice);
        $reason = $r->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'سبب الإلغاء'])['reason'];

        try {
            $this->svc->cancel($invoice, $reason, (int) $r->user()->id);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('ok', 'أُلغيت الفاتورة.');
    }

    /**
     * بنود مقترحة من مخرجات الحملة — لا يُعاد إدخال ما هو مسجّل أصلًا.
     * اقتراح لا إلزام: قد تُفوتَر الحملة على دفعات.
     */
    public function suggestItems(Request $r, Campaign $campaign): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $items = CampaignDeliverable::where('campaign_id', $campaign->id)->get()->map(fn ($d) => [
            'description' => trim(($d->type ?? 'مخرَج') . ' · ' . ($d->platform ?? '')),
            'quantity' => (int) ($d->quantity ?: 1),
            'unit_price_minor' => (int) ($d->fee_minor ?? 0),
            'deliverable_id' => $d->id,
        ])->values();

        return response()->json([
            'items' => $items,
            'campaign' => ['id' => $campaign->id, 'name' => $campaign->name, 'clientId' => $campaign->client_id],
        ]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $r, ?Invoice $invoice = null): array
    {
        $rules = [
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'due_date' => 'nullable|date',
            'issue_date' => 'nullable|date',
            'tax_rate_bp' => 'nullable|integer|min:0|max:10000',
            'discount_riyals' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:300',
            'items.*.quantity' => 'required|integer|min:1|max:10000',
            'items.*.unit_price_riyals' => 'required|numeric|min:0',
            'items.*.deliverable_id' => 'nullable|integer|exists:campaign_deliverables,id',
        ];
        if (! $invoice) {
            $rules['client_id'] = 'required|integer|exists:clients,id';
        }

        $data = $r->validate($rules, [], [
            'client_id' => 'العميل', 'items' => 'البنود',
            'items.*.description' => 'وصف البند', 'items.*.unit_price_riyals' => 'سعر الوحدة',
        ]);

        // الريالات تُحوَّل إلى وحدات صغرى عند الحدّ: لا عدد عشري يعبر إلى النطاق
        $data['discount_minor'] = (int) round(($data['discount_riyals'] ?? 0) * 100);
        $data['items'] = array_map(fn (array $i) => [
            'description' => $i['description'],
            'quantity' => (int) $i['quantity'],
            'unit_price_minor' => (int) round($i['unit_price_riyals'] * 100),
            'deliverable_id' => $i['deliverable_id'] ?? null,
        ], $data['items']);

        return $data;
    }

    private function assertCampaignBelongsToClient(?int $campaignId, Client $client): void
    {
        if (! $campaignId) {
            return;
        }
        $campaign = Campaign::findOrFail($campaignId);
        abort_unless($campaign->client_id === $client->id, 422, 'الحملة لا تتبع هذا العميل.');
    }

    private function outstandingMinor(): int
    {
        $open = Invoice::whereIn('status', Invoice::OPEN)->withSum('payments', 'amount_minor')->get();

        return (int) $open->sum(fn ($i) => max(0, $i->total_minor - (int) $i->payments_sum_amount_minor));
    }

    /** @return array<string,mixed> */
    private function options(): array
    {
        return [
            'clients' => Client::orderBy('display_name')->get(['id', 'display_name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name]),
            'campaigns' => Campaign::orderByDesc('id')->get(['id', 'name', 'client_id'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'clientId' => $c->client_id]),
        ];
    }

    /** @return array<string,mixed> */
    private function row(Invoice $i): array
    {
        $paid = $i->paidMinor();

        return [
            'id' => $i->id,
            'number' => $i->invoice_number,
            'client' => $i->client?->display_name,
            'campaign' => $i->campaign?->name,
            'status' => $i->status,
            'statusLabel' => __('statuses.' . $i->status),
            'statusTone' => __('statuses.tone.' . $i->status),
            'currency' => $i->currency,
            'totalMinor' => $i->total_minor,
            'paidMinor' => $paid,
            'balanceMinor' => max(0, $i->total_minor - $paid),
            'issueDate' => $i->issue_date?->format('Y-m-d'),
            'dueDate' => $i->due_date?->format('Y-m-d'),
            'isOverdue' => $i->isOverdue(),
        ];
    }
}
