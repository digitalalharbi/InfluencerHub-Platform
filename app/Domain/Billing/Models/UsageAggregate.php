<?php
namespace App\Domain\Billing\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class UsageAggregate extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','organization_id','feature_key','period_start','period_end','used'];
    protected $casts = ['used'=>'integer','period_start'=>'date','period_end'=>'date'];
}
