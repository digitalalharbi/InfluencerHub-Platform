<?php
namespace App\Domain\Partners\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ExternalAgencyStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'external_agency_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','external_agency_id','from_status','to_status','actor_id','reason','occurred_at'];
    protected $casts = ['occurred_at'=>'datetime'];
}
