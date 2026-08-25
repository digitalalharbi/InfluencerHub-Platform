<?php

namespace App\Domain\Nomination\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * صفّ إتاحة ميزة لنطاق مُحدَّد تديره المنصّة.
 *
 * جدولٌ عالميّ (بلا TenantScope): يحمل صفوفًا عامّة (tenant_id=null) وصفوفًا لكل
 * مستأجر/مساحة/بوّابة. تُدار حصريًّا من طبقة المنصّة (Platform Owner / مدير النظام).
 * غياب الصفّ = مُتاحة افتراضيًّا. لا يُخزَّن هنا أي بيان ترشيح — ضبطٌ فقط.
 *
 * @property string $feature_key
 * @property int|null $tenant_id
 * @property int|null $workspace_id
 * @property string|null $portal
 * @property bool $enabled
 */
class FeatureAvailability extends Model
{
    protected $table = 'feature_availabilities';

    protected $fillable = [
        'feature_key', 'tenant_id', 'workspace_id', 'portal', 'enabled', 'reason', 'set_by',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'workspace_id' => 'integer',
        'enabled' => 'boolean',
    ];
}
