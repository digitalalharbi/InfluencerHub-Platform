<?php
namespace App\Domain\Billing\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class UsageRecord extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','organization_id','feature_key','amount','period_start','period_end','idempotency_key','actor_user_id'];
    protected $casts = ['amount'=>'integer','period_start'=>'date','period_end'=>'date'];
}
