<?php

namespace App\Domain\AdminPool\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * توصية مدير النظام لعميل. مربوطة بالمستأجر (نطاق العميل).
 * نسخة مستقلّة عن القاعدة: تبقى قائمة وإن حُذفت القاعدة.
 */
class PoolRecommendation extends Model
{
    use BelongsToTenant;

    protected $table = 'admin_pool_recommendations';

    protected $fillable = [
        'tenant_id', 'client_id', 'campaign_id', 'name', 'platform', 'account_url',
        'followers', 'categories', 'price_minor', 'region', 'city', 'source_type',
        'status', 'decision_reason', 'decided_at', 'recommended_by',
    ];

    protected $casts = [
        'categories' => 'array', 'followers' => 'int', 'price_minor' => 'int', 'decided_at' => 'datetime',
    ];
}
