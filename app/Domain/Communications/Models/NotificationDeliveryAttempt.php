<?php
namespace App\Domain\Communications\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class NotificationDeliveryAttempt extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','notification_id','channel','provider','recipient','provider_message_id','status','queued_at','delivered_at','read_at','failed_at','failure_code','retry_count','detail','attempted_at'];
    protected $casts = ['attempted_at'=>'datetime','queued_at'=>'datetime','delivered_at'=>'datetime','read_at'=>'datetime','failed_at'=>'datetime','retry_count'=>'integer'];
}
