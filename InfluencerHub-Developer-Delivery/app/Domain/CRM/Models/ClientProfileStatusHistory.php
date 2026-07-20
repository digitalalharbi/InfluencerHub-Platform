<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientProfileStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'client_profile_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','change_request_id','from_status','to_status','actor_id','reason','occurred_at'];
    protected $casts = ['occurred_at' => 'datetime'];
}
