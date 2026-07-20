<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
class PlanPrice extends Model {
    protected static function booted(): void {
        static::updating(function ($m) {
            if (PlanVersion::whereKey($m->plan_version_id)->value('is_locked')) {
                throw new \RuntimeException('لا يمكن تعديل نسخة خطة مقفلة (مستخدمة تاريخيًا). أنشئ نسخة جديدة.');
            }
        });
    }

    protected $fillable = ['plan_version_id','currency','interval','amount_minor','is_active'];
    protected $casts = ['amount_minor'=>'integer','is_active'=>'boolean'];
}
