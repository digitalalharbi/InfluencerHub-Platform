<?php
namespace App\Domain\Contracts\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ContractStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'contract_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','contract_id','from_status','to_status','actor_id','actor_type','reason','occurred_at'];
    protected $casts = ['occurred_at'=>'datetime'];
}
