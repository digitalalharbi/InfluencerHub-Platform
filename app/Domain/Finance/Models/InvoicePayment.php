<?php

namespace App\Domain\Finance\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفعة على فاتورة.
 *
 * `provider = manual` يعني تسجيل دفعة وقعت خارج النظام (حوالة، نقد). لا يُقدَّم
 * ذلك قطّ على أنه تحصيل أجراه النظام — لا مزوّد دفع مربوط بعد.
 */
class InvoicePayment extends Model
{
    use BelongsToTenant;

    public const METHODS = [
        'bank_transfer' => 'حوالة بنكية',
        'cash' => 'نقدًا',
        'cheque' => 'شيك',
        'provider' => 'بوابة دفع',
    ];

    protected $fillable = [
        'tenant_id', 'invoice_id', 'amount_minor', 'currency', 'method',
        'provider', 'provider_reference', 'received_at', 'note', 'recorded_by',
    ];

    protected $casts = ['amount_minor' => 'integer', 'received_at' => 'date'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
