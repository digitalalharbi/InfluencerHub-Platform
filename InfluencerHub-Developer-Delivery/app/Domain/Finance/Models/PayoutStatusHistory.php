<?php
namespace App\Domain\Finance\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class PayoutStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'payout_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','payout_id','from_status','to_status','actor_id','reason','occurred_at'];
    protected $casts = ['occurred_at'=>'datetime'];
}
