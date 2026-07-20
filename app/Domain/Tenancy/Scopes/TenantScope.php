<?php
namespace App\Domain\Tenancy\Scopes;

use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** يفرض عزل المستأجر على كل استعلام. لا سياق = لا بيانات (fail-closed). */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::bypassing()) {
            return; // system_admin / مهمة خلفية صريحة
        }
        if (TenantContext::check()) {
            $builder->where($model->getTable() . '.tenant_id', TenantContext::tenantId());
        } else {
            $builder->whereRaw('1 = 0'); // أمان افتراضي مغلق
        }
    }
}
