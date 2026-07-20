<?php

namespace App\Domain\CRM\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * علاقة علامة بمساحة عمل — الملكية والتفويض مُصرَّحان لا مُستنتَجان.
 *
 * **لا `BelongsToTenant` هنا عمدًا.** الجدول يصل *بين* مستأجرين: علامة تملك
 * نفسها في مستأجرها، ووكالة مفوَّضة في مستأجرها. نطاق مستأجر واحد على الصفّ
 * يجعل كل طرف أعمى عن العلاقة من جهته. الحراسة تكون في الاستعلام والسياسة:
 * لا يُقرأ صفّ إلا من طرفَيه.
 */
class BrandWorkspaceRelationship extends Model
{
    /** الملكية. صفّ واحد لكل علامة، ولا يُنقَل بربط وكالة. */
    public const OWNER = 'owner';
    /** وكالة تدير نيابةً عن العلامة ضمن نطاق. */
    public const MANAGING_AGENCY = 'managing_agency';
    /** مزوّد خدمة محدَّدة (تصوير/إعلانات…). */
    public const SERVICE_PROVIDER = 'service_provider';
    public const COLLABORATOR = 'collaborator';
    public const VIEWER = 'viewer';

    public const TYPES = [self::OWNER, self::MANAGING_AGENCY, self::SERVICE_PROVIDER, self::COLLABORATOR, self::VIEWER];

    /** نطاقات الخدمات المتاحة للتفويض. */
    public const SERVICES = ['campaigns', 'shortlists', 'content', 'contracts', 'finance', 'reports', 'ads', 'commerce', 'analytics'];

    protected $fillable = ['brand_id', 'tenant_id', 'relationship_type', 'status',
        'permissions_scope', 'services_scope', 'started_at', 'ended_at', 'invited_by', 'approved_by'];

    protected $casts = [
        'permissions_scope' => 'array',
        'services_scope' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function invitedBy(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function isLive(): bool
    {
        return $this->status === 'active' && ! $this->ended_at;
    }

    /** التفويض لا يُمنح شاملًا: خدمة غير مذكورة = غير مفوَّضة. */
    public function grants(string $service): bool
    {
        return $this->isLive() && in_array($service, $this->services_scope ?? [], true);
    }
}
