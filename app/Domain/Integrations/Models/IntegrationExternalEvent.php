<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حدث خارجيّ حقيقيّ من مزوّد (رسالة Gmail/حدث Calendar) مربوط بمبدع/عميل حين يُطابَق.
 * مصدرٌ للخطّ الزمني — صفوف فعليّة بتواريخها، لا اختلاق. مُعرَّف بمعرّف المزوّد لمنع التكرار،
 * مُستأجَرٌ بإحكام (fail-closed) عبر BelongsToTenant.
 */
class IntegrationExternalEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'provider', 'capability', 'external_id', 'kind', 'creator_id', 'client_id',
        'occurred_at', 'title', 'summary', 'link', 'direction', 'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    /** أنواع أحداث التقويم المعياريّة (GOOGLE-01) — مرتبطة بدورة حياة المبدع/الحملة. */
    public const CALENDAR_KINDS = [
        'meeting', 'briefing', 'shoot', 'content_deadline', 'publication', 'rights_expiry', 'payout_due', 'event',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }
}
