<?php

namespace App\Domain\Publishers\Models;

use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ناشر مُكتشَف/مُحلَّل على منصّة (Publisher Intelligence). يحمل مصدره وتاريخ آخر مزامنة.
 * `converted_creator_id` يربطه بمؤثر CRM بعد التحويل (idempotent، منع التكرار).
 */
class Publisher extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'publisher_number', 'platform', 'handle', 'display_name', 'avatar_url',
        'followers_count', 'engagement_rate', 'growth_30d', 'content_types', 'categories', 'brands_worked_with',
        'city', 'language', 'audience_note', 'quality_score', 'source', 'last_synced_at', 'saved',
        'converted_creator_id', 'created_by',
    ];

    protected $casts = [
        'content_types' => 'array', 'categories' => 'array', 'brands_worked_with' => 'array',
        'followers_count' => 'integer', 'engagement_rate' => 'float', 'growth_30d' => 'float',
        'quality_score' => 'integer', 'saved' => 'boolean', 'last_synced_at' => 'datetime',
    ];

    public function convertedCreator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'converted_creator_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_creator_id !== null;
    }
}
