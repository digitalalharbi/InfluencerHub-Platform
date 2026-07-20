<?php
namespace App\Domain\Requests\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ServiceRequestStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'service_request_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','service_request_id','from_status','to_status','actor_id','reason','occurred_at'];
    protected $casts = ['occurred_at'=>'datetime'];
}
