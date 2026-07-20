<?php

namespace App\Domain\Finance\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Finance\Models\{Invoice, InvoiceItem, InvoicePayment, InvoiceStatusHistory};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * دورة حياة الفاتورة: مسوّدة ← صادرة ← (مدفوعة جزئيًّا) ← مدفوعة.
 *
 * كل المبالغ أعداد صحيحة بوحدات صغرى. لا عدد عشري عائم في المال: جمع
 * 0.1 + 0.2 لا يساوي 0.3 في الفاصلة العائمة، وفرق هللة واحدة في تسوية
 * يعني دفترًا لا يتّزن.
 *
 * الانتقالات تُرفع بأسباب مفهومة لا تُعاد صامتة: مَن يضغط زرًّا يستحقّ أن
 * يعرف لماذا لم يحدث شيء.
 */
class InvoiceService
{
    /**
     * نسبة الضريبة الافتراضية بالنقاط الأساسية (15٪ = 1500).
     *
     * كانت الواجهة تحمل نسخة ثابتة ثانية من الرقم في معاينة الإنشاء، فتُظهر
     * 15٪ مهما كانت نسبة الفاتورة الفعلية. الرقم يُنشر من هنا وحده.
     */
    public const DEFAULT_TAX_RATE_BP = 1500;

    /** الانتقالات المسموحة — ما عداها مرفوض صراحةً. */
    private const ALLOWED = [
        'draft' => ['issued', 'cancelled'],
        'issued' => ['partially_paid', 'paid', 'overdue', 'cancelled'],
        'partially_paid' => ['paid', 'overdue', 'cancelled'],
        'overdue' => ['partially_paid', 'paid', 'cancelled'],
        'paid' => [],
        'cancelled' => [],
    ];

    /** @param array<int,array{description:string,quantity:int,unit_price_minor:int,deliverable_id?:int|null}> $items */
    public function create(int $tenantId, array $data, array $items, int $actorId): Invoice
    {
        return DB::transaction(function () use ($tenantId, $data, $items, $actorId) {
            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'invoice_number' => $this->nextNumber($tenantId),
                'client_id' => $data['client_id'],
                'campaign_id' => $data['campaign_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'status' => 'draft',
                'currency' => $data['currency'] ?? 'SAR',
                'tax_rate_bp' => $data['tax_rate_bp'] ?? self::DEFAULT_TAX_RATE_BP,
                'discount_minor' => (int) ($data['discount_minor'] ?? 0),
                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->replaceItems($invoice, $items);
            $this->recordStatus($invoice, null, 'draft', $actorId, 'إنشاء المسوّدة');
            AuditLogger::log('invoice.created', $invoice, ['total' => $invoice->total_minor], $tenantId, $actorId);

            return $invoice->fresh(['items']);
        });
    }

    /** @param array<int,array<string,mixed>> $items */
    public function updateDraft(Invoice $invoice, array $data, array $items, int $actorId): Invoice
    {
        $this->assertEditable($invoice);

        return DB::transaction(function () use ($invoice, $data, $items, $actorId) {
            $invoice->update(array_intersect_key($data, array_flip([
                'campaign_id', 'brand_id', 'currency', 'tax_rate_bp',
                'discount_minor', 'issue_date', 'due_date', 'notes',
            ])));
            $this->replaceItems($invoice, $items);
            AuditLogger::log('invoice.updated', $invoice, [], (int) $invoice->tenant_id, $actorId);

            return $invoice->fresh(['items']);
        });
    }

    /** إصدار الفاتورة — بعده تصير وثيقة لا تُعدَّل. */
    public function issue(Invoice $invoice, int $actorId): Invoice
    {
        $this->assertTransition($invoice, 'issued');

        if ($invoice->items()->count() === 0) {
            throw new \RuntimeException('أضِف بندًا واحدًا على الأقل قبل الإصدار.');
        }
        if ($invoice->total_minor <= 0) {
            throw new \RuntimeException('إجمالي الفاتورة صفر — راجع البنود أو الخصم.');
        }

        return DB::transaction(function () use ($invoice, $actorId) {
            $from = $invoice->status;
            $invoice->update([
                'status' => 'issued',
                'issued_at' => now(),
                'issue_date' => $invoice->issue_date ?? now()->toDateString(),
                'due_date' => $invoice->due_date ?? now()->addDays(30)->toDateString(),
            ]);
            $this->recordStatus($invoice, $from, 'issued', $actorId, 'إصدار الفاتورة');
            AuditLogger::log('invoice.issued', $invoice, ['total' => $invoice->total_minor], (int) $invoice->tenant_id, $actorId);

            return $invoice->fresh();
        });
    }

    /**
     * تسجيل دفعة. المبلغ يُقيَّد بالمتبقّي: تحصيل أكثر من المستحقّ خطأ إدخال
     * لا واقعة مالية، وقبوله يُفسد التسوية.
     */
    public function recordPayment(Invoice $invoice, array $data, int $actorId): InvoicePayment
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            throw new \RuntimeException('لا تُسجَّل دفعة على فاتورة ' . ($invoice->status === 'draft' ? 'لم تُصدَر بعد' : 'ملغاة') . '.');
        }

        $amount = (int) $data['amount_minor'];
        if ($amount <= 0) {
            throw new \RuntimeException('مبلغ الدفعة يجب أن يكون أكبر من صفر.');
        }

        $balance = $invoice->balanceMinor();
        if ($amount > $balance) {
            throw new \RuntimeException('المبلغ يتجاوز المتبقّي على الفاتورة (' . number_format($balance / 100, 2) . ').');
        }

        return DB::transaction(function () use ($invoice, $data, $amount, $actorId) {
            $payment = InvoicePayment::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'amount_minor' => $amount,
                'currency' => $invoice->currency,
                'method' => $data['method'],
                'provider' => $data['provider'] ?? 'manual',
                'provider_reference' => $data['provider_reference'] ?? null,
                'received_at' => $data['received_at'],
                'note' => $data['note'] ?? null,
                'recorded_by' => $actorId,
            ]);

            $this->refreshPaymentStatus($invoice->fresh(['payments']), $actorId);
            AuditLogger::log('invoice.payment_recorded', $invoice, ['amount' => $amount], (int) $invoice->tenant_id, $actorId);

            return $payment;
        });
    }

    public function cancel(Invoice $invoice, string $reason, int $actorId): Invoice
    {
        $this->assertTransition($invoice, 'cancelled');

        // فاتورة حُصِّل عليها شيء لا تُلغى: الإلغاء يمحو أثر مبلغ استُلم فعلًا
        if ($invoice->paidMinor() > 0) {
            throw new \RuntimeException('استُلمت دفعات على هذه الفاتورة — أصدِر إشعارًا دائنًا بدل الإلغاء.');
        }

        return DB::transaction(function () use ($invoice, $reason, $actorId) {
            $from = $invoice->status;
            $invoice->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->recordStatus($invoice, $from, 'cancelled', $actorId, $reason);
            AuditLogger::log('invoice.cancelled', $invoice, ['reason' => $reason], (int) $invoice->tenant_id, $actorId);

            return $invoice->fresh();
        });
    }

    /** الحالة تُشتقّ من المدفوعات لا تُضبَط يدويًّا، فلا تتناقض مع الدفتر. */
    private function refreshPaymentStatus(Invoice $invoice, int $actorId): void
    {
        $from = $invoice->status;
        $balance = $invoice->balanceMinor();

        $to = match (true) {
            $balance === 0 => 'paid',
            $invoice->isOverdue() => 'overdue',
            default => 'partially_paid',
        };

        if ($to === $from) {
            return;
        }

        $invoice->update(['status' => $to, 'paid_at' => $to === 'paid' ? now() : null]);
        $this->recordStatus($invoice, $from, $to, $actorId, 'تحديث بعد تسجيل دفعة');
    }

    /** @param array<int,array<string,mixed>> $items */
    private function replaceItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        $order = 0;
        foreach ($items as $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $unit = (int) $row['unit_price_minor'];
            InvoiceItem::create([
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'description' => $row['description'],
                'quantity' => $qty,
                'unit_price_minor' => $unit,
                'line_total_minor' => $qty * $unit,
                'deliverable_id' => $row['deliverable_id'] ?? null,
                'sort_order' => $order++,
            ]);
        }

        $this->recalculate($invoice);
    }

    /**
     * إعادة حساب الإجماليات بالأعداد الصحيحة.
     * الضريبة تُحسب بعد الخصم — هذا ترتيب الحساب المعتمَد، وعكسه يغيّر المبلغ.
     */
    private function recalculate(Invoice $invoice): void
    {
        $subtotal = (int) $invoice->items()->sum('line_total_minor');
        $discount = min((int) $invoice->discount_minor, $subtotal);
        $taxable = $subtotal - $discount;
        $tax = intdiv($taxable * (int) $invoice->tax_rate_bp, 10000);

        $invoice->update([
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'tax_minor' => $tax,
            'total_minor' => $taxable + $tax,
        ]);
    }

    private function assertEditable(Invoice $invoice): void
    {
        if (! $invoice->isEditable()) {
            throw new \RuntimeException('الفاتورة الصادرة وثيقة لدى العميل — لا تُعدَّل. ألغِها وأصدِر بديلًا.');
        }
    }

    private function assertTransition(Invoice $invoice, string $to): void
    {
        if (! in_array($to, self::ALLOWED[$invoice->status] ?? [], true)) {
            throw new \RuntimeException("لا يمكن الانتقال من «{$invoice->status}» إلى «{$to}».");
        }
    }

    private function recordStatus(Invoice $invoice, ?string $from, string $to, int $actorId, ?string $reason): void
    {
        InvoiceStatusHistory::create([
            'tenant_id' => $invoice->tenant_id, 'invoice_id' => $invoice->id,
            'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }

    private function nextNumber(int $tenantId): string
    {
        $n = TenantContext::withBypass(fn () => Invoice::withTrashed()->where('tenant_id', $tenantId)->count() + 1);

        return 'INV-' . $tenantId . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
