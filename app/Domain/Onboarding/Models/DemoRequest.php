<?php

namespace App\Domain\Onboarding\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * طلب عرض توضيحي من الموقع العام.
 *
 * لا BelongsToTenant: الطلب يسبق المستأجر — كما في SignupRequest.
 * بادئة المرجع `DM-` تفرّقه عن `SU-` في المحادثة مع مقدّم الطلب،
 * فلا يُخلط طلب العرض بطلب فتح الحساب عند المتابعة.
 */
class DemoRequest extends Model
{
    /** الجمهور الذي يُبنى عليه العرض — يحدّد ما يُعرض في الجلسة لا نوع حساب. */
    public const AUDIENCES = [
        'client' => 'عميل أو علامة تجارية',
        'agency' => 'وكالة',
        'creator' => 'صانع محتوى أو مؤثّر',
    ];

    protected $fillable = [
        'reference', 'audience', 'contact_name', 'email', 'phone', 'company_name',
        'role_title', 'team_size', 'preferred_time', 'interests', 'status', 'ip_address',
    ];

    protected $casts = ['scheduled_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->reference ??= 'DM-' . strtoupper(Str::random(8));
        });
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }
}
