<?php
namespace App\Domain\Content\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ContentStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'content_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','content_item_id','from_status','to_status','actor_id','actor_type','reason','occurred_at'];
    protected $casts = ['occurred_at'=>'datetime'];
}
