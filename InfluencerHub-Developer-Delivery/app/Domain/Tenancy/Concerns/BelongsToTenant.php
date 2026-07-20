<?php
namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Scopes\TenantScope;
use App\Domain\Tenancy\Support\TenantContext;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (TenantContext::bypassing()) {
                return; // القيمة تُضبط صراحةً
            }
            if (TenantContext::check()) {
                $model->tenant_id = TenantContext::tenantId();
                return;
            }
            if (! empty($model->tenant_id)) {
                return; // إنشاء داخلي بقيمة صريحة
            }
            throw new \RuntimeException('تعذّر إنشاء السجل: لا يوجد سياق مستأجر (tenant).');
        });
    }
}
