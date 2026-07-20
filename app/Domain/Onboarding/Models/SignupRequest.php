<?php

namespace App\Domain\Onboarding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * طلب فتح حساب من الموقع العام.
 * لا BelongsToTenant: الطلب يسبق المستأجر.
 */
class SignupRequest extends Model
{
    public const TYPES = ['client' => 'عميل أو علامة تجارية', 'agency' => 'وكالة'];

    /**
     * حقول المراجعة داخل القائمة عمدًا: كانت خارجها فتُسقَط بصمت عند الاعتماد
     * أو الرفض — يُطالَب المراجع بسبب، ويُرسَل للمتقدّم، ثم لا يُحفظ في السجلّ.
     */
    protected $fillable = [
        'reference', 'account_type', 'contact_name', 'email', 'phone', 'company_name',
        'website', 'country_code', 'team_size', 'monthly_campaigns', 'notes', 'status', 'ip_address',
        'review_notes', 'reviewed_by', 'reviewed_at', 'created_tenant_id',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->reference ??= 'SU-' . strtoupper(Str::random(8));
        });
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->account_type] ?? $this->account_type;
    }
}
