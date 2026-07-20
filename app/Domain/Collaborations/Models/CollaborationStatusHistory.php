<?php
namespace App\Domain\Collaborations\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CollaborationStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'collaboration_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','collaboration_id','from_status','to_status','actor_id','actor_type','reason','occurred_at'];
    protected $casts = ['occurred_at'=>'datetime'];
}
