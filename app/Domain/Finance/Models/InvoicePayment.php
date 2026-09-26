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

    /** المصدر الاحتياطيّ العربيّ + مفاتيح التحقّق. التسمية للعرض تأتي من methods()/methodLabel() الواعيتين باللغة. */
    public const METHODS = [
        'bank_transfer' => 'حوالة بنكية',
        'cash' => 'نقدًا',
        'cheque' => 'شيك',
        'provider' => 'بوابة دفع',
    ];

    /** خيارات طرق الدفع بلغة الطلب (مفتاح → تسمية) — للنماذج. */
    public static function methods(): array
    {
        $out = [];
        foreach (array_keys(self::METHODS) as $key) {
            $out[$key] = self::methodLabelFor($key);
        }

        return $out;
    }

    /** تسمية طريقة دفع بلغة الطلب، مع رجوع للثابت العربيّ إن غابت الترجمة. */
    public static function methodLabelFor(string $key): string
    {
        $t = trans("payment_methods.$key");

        return (is_string($t) && $t !== "payment_methods.$key") ? $t : (self::METHODS[$key] ?? $key);
    }

    protected $fillable = [
        'tenant_id', 'invoice_id', 'amount_minor', 'currency', 'method',
        'provider', 'provider_reference', 'received_at', 'note', 'recorded_by',
    ];

    protected $casts = ['amount_minor' => 'integer', 'received_at' => 'date'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function methodLabel(): string
    {
        return self::methodLabelFor($this->method);
    }
}
