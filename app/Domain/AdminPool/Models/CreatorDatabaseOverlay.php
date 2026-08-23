<?php

namespace App\Domain\AdminPool\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * تراكب المؤسسة على مبدع قاعدة المؤثرين — بيانات علاقة خاصّة معزولة بالمستأجر.
 *
 * BelongsToTenant يضمن ألّا تُقرأ/تُكتب تراكبات مستأجر من سياق مستأجر آخر. لا
 * علاقة Eloquent بالملف العالمي هنا؛ الربط بـ pool_creator_id فقط.
 */
class CreatorDatabaseOverlay extends Model
{
    use BelongsToTenant;

    protected $table = 'creator_database_overlays';

    protected $fillable = [
        'tenant_id', 'organization_id', 'pool_creator_id',
        'favorite', 'tags', 'notes', 'negotiated_rate_minor',
        'relationship_status', 'tenant_rating', 'assigned_to', 'last_contacted_at',
    ];

    protected $casts = [
        'favorite' => 'bool',
        'tags' => 'array',
        'negotiated_rate_minor' => 'int',
        'last_contacted_at' => 'datetime',
    ];

    /** @return array<string,mixed> تمثيل التراكب المعروض للمستأجر (خاصّ به وحده). */
    public function toArrayForTenant(): array
    {
        return [
            'favorite' => $this->favorite,
            'tags' => $this->tags ?? [],
            'notes' => $this->notes,
            'negotiatedRate' => $this->negotiated_rate_minor !== null ? intdiv($this->negotiated_rate_minor, 100) : null,
            'relationshipStatus' => $this->relationship_status,
            'tenantRating' => $this->tenant_rating,
            'lastContactedAt' => optional($this->last_contacted_at)?->toDateString(),
        ];
    }
}
