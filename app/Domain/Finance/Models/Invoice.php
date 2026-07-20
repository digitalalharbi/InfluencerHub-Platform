<?php

namespace App\Domain\Finance\Models;

use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * فاتورة العميل.
 *
 * المسوّدة وحدها قابلة للتعديل: الفاتورة الصادرة وثيقة وصلت العميل، وتعديلها
 * بأثر رجعي يجعل ما لديه مخالفًا لما لدينا.
 */
class Invoice extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const STATUSES = ['draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled'];
    /** حالات يُنتظَر فيها تحصيل. */
    public const OPEN = ['issued', 'partially_paid', 'overdue'];

    protected $fillable = [
        'tenant_id', 'invoice_number', 'client_id', 'campaign_id', 'brand_id', 'status', 'currency',
        'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'tax_rate_bp',
        'issue_date', 'due_date', 'issued_at', 'paid_at', 'cancelled_at', 'notes', 'cancel_reason', 'created_by',
    ];

    protected $casts = [
        'subtotal_minor' => 'integer', 'discount_minor' => 'integer',
        'tax_minor' => 'integer', 'total_minor' => 'integer', 'tax_rate_bp' => 'integer',
        'issue_date' => 'date', 'due_date' => 'date',
        'issued_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany { return $this->hasMany(InvoicePayment::class); }
    public function statusHistory(): HasMany { return $this->hasMany(InvoiceStatusHistory::class); }

    public function isEditable(): bool { return $this->status === 'draft'; }

    /** المحصَّل فعلًا — يُجمع من المدفوعات لا من حقل يُحدَّث يدويًّا. */
    public function paidMinor(): int
    {
        return (int) ($this->relationLoaded('payments')
            ? $this->payments->sum('amount_minor')
            : $this->payments()->sum('amount_minor'));
    }

    public function balanceMinor(): int
    {
        return max(0, $this->total_minor - $this->paidMinor());
    }

    /** متأخّرة: استحقّت ولم تُسدَّد بالكامل. الحالة المحفوظة قد تسبق التاريخ. */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && in_array($this->status, self::OPEN, true)
            && $this->balanceMinor() > 0;
    }
}
