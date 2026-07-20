<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class PlanVersion extends Model {
    protected $fillable = ['plan_id','version','is_active','is_locked','effective_from'];
    protected $casts = ['is_active'=>'boolean','is_locked'=>'boolean','effective_from'=>'datetime'];
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function entitlements(): HasMany { return $this->hasMany(PlanEntitlement::class); }
    public function prices(): HasMany { return $this->hasMany(PlanPrice::class); }
}
