<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
class PlanEntitlement extends Model {
    protected static function booted(): void {
        static::updating(function ($m) {
            if (PlanVersion::whereKey($m->plan_version_id)->value('is_locked')) {
                throw new \RuntimeException('لا يمكن تعديل نسخة خطة مقفلة (مستخدمة تاريخيًا). أنشئ نسخة جديدة.');
            }
        });
    }

    protected $fillable = ['plan_version_id','feature_key','value','is_unlimited'];
    protected $casts = ['value'=>'integer','is_unlimited'=>'boolean'];
}
