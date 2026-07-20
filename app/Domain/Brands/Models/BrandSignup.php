<?php

namespace App\Domain\Brands\Models;

use App\Domain\CRM\Models\Brand;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رحلة تسجيل علامة لنفسها — الحالة قبل وجود مستأجر.
 *
 * لا `BelongsToTenant` هنا عمدًا: السجلّ يسبق المستأجر، ولو نُطّق لَما أمكن
 * قراءته في الرحلة التي أنشأته.
 */
class BrandSignup extends Model
{
    protected $fillable = [
        'reference', 'email', 'phone',
        'email_code_hash', 'email_verified_at', 'email_attempts',
        'phone_code_hash', 'phone_verified_at', 'phone_attempts',
        'sent_count', 'last_sent_at', 'expires_at', 'status',
        'organization_data', 'brand_data',
        'match_decision', 'match_score', 'match_signals', 'matched_brand_id',
        'created_tenant_id', 'created_brand_id', 'created_user_id', 'ip',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'organization_data' => 'array',
        'brand_data' => 'array',
        'match_signals' => 'array',
        'email_attempts' => 'integer',
        'phone_attempts' => 'integer',
        'sent_count' => 'integer',
        'match_score' => 'integer',
    ];

    /** الرموز لا تخرج من النموذج أبدًا — لا في JSON ولا في تفريغ تصحيحي. */
    protected $hidden = ['email_code_hash', 'phone_code_hash'];

    public const DECISION_NONE = 'none';

    public const DECISION_POSSIBLE = 'possible';

    public const DECISION_STRONG = 'strong';

    public function matchedBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'matched_brand_id');
    }

    public function createdTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'created_tenant_id');
    }

    public function createdBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'created_brand_id');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function emailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function phoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /** الجوال اختياري؛ فإن لم يُدخَل لم يكن التحقّق منه شرطًا. */
    public function fullyVerified(): bool
    {
        return $this->emailVerified() && ($this->phone === null || $this->phoneVerified());
    }

    /** التزويد يقع مرّة واحدة — ووجود مستأجر مُنشَأ هو الدليل. */
    public function isProvisioned(): bool
    {
        return $this->created_tenant_id !== null;
    }
}
