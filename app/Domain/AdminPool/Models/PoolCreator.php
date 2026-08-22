<?php

namespace App\Domain\AdminPool\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * مبدع في قاعدة مدير النظام.
 *
 * لا BelongsToTenant: هذه قاعدة مركزية لمدير النظام، لا تخصّ مستأجرًا. الوصول
 * محكوم بـ`is_system_admin` في السياسة، لا بنطاق المستأجر.
 */
class PoolCreator extends Model
{
    protected $table = 'admin_creator_pool';

    protected $fillable = [
        'name', 'phone', 'platform', 'account_url', 'followers', 'tier', 'gender',
        'categories', 'price_post_minor', 'price_coverage_minor', 'cost_post_minor', 'cost_coverage_minor', 'shows_face',
        'region', 'city', 'rating', 'likes', 'store', 'source_type', 'imported_at',
    ];

    protected $casts = [
        'categories' => 'array',
        'shows_face' => 'bool',
        'followers' => 'int',
        'likes' => 'int',
        'price_post_minor' => 'int',
        'price_coverage_minor' => 'int',
        'cost_post_minor' => 'int',
        'cost_coverage_minor' => 'int',
        'imported_at' => 'datetime',
    ];

    /** @return array<string,mixed> صفّ ببيانات الحجز الكاملة (لمدير النظام). */
    public function toBookingArray(?array $match = null): array
    {
        $riyals = fn (?int $m) => $m ? intdiv($m, 100) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'platformLabel' => self::PLATFORM_LABELS[$this->platform] ?? $this->platform,
            'accountUrl' => $this->account_url,
            'phone' => $this->phone,
            'followers' => $this->followers,
            'tier' => $this->tier,
            'gender' => $this->gender,
            'categories' => $this->categories ?? [],
            'costPost' => $riyals($this->cost_post_minor),
            'costCoverage' => $riyals($this->cost_coverage_minor),
            'sellPost' => $riyals($this->price_post_minor),
            'sellCoverage' => $riyals($this->price_coverage_minor),
            'showsFace' => $this->shows_face,
            'region' => $this->region,
            'city' => $this->city,
            'rating' => $this->rating,
            'likes' => $this->likes,
            'store' => $this->store,
            'sourceType' => $this->source_type,
            'matchScore' => $match['score'] ?? null,
            'matchReasons' => $match['reasons'] ?? [],
            'matchFlags' => $match['flags'] ?? [],
        ];
    }

    public const PLATFORM_LABELS = [
        'snapchat' => 'سناب شات', 'tiktok' => 'تيك توك',
        'linkedin' => 'لينكدإن', 'x' => 'إكس',
    ];
}
