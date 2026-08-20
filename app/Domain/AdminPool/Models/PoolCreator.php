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

    public const PLATFORM_LABELS = [
        'snapchat' => 'سناب شات', 'tiktok' => 'تيك توك',
        'linkedin' => 'لينكدإن', 'x' => 'إكس',
    ];
}
