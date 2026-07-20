<?php

namespace App\Domain\Onboarding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * حالة التسجيل الذاتي. لا BelongsToTenant: يسبق المستأجر.
 */
class SelfSignup extends Model
{
    /** ترتيب الخطوات كما يراها المستخدم — يقود شريط التقدّم والخطوة التالية. */
    public const STEPS = [
        'email_verification_pending' => 'تأكيد البريد',
        'verified' => 'بيانات المالك',
        'organization_pending' => 'إعداد المؤسسة',
        'active' => 'جاهز للاستخدام',
    ];

    protected $fillable = [
        'reference', 'account_type', 'email', 'status', 'verification_code_hash',
        'code_expires_at', 'verification_attempts', 'email_verified_at',
        'completed_steps', 'created_tenant_id', 'created_user_id', 'ip_address',
    ];

    protected $casts = [
        'completed_steps' => 'array',
        'code_expires_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->reference ??= 'SS-' . strtoupper(Str::random(8)));
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** الخطوة التالية بالاسم — الواجهة تعرضها بدل أن يخمّن المستخدم. */
    public function nextStepLabel(): string
    {
        return self::STEPS[$this->status] ?? 'متابعة';
    }
}
