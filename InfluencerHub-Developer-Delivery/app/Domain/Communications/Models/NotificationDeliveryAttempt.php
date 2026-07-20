<?php
namespace App\Domain\Communications\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class NotificationDeliveryAttempt extends Model {
    use BelongsToTenant;
    public $timestamps = false;
    protected $fillable = ['tenant_id','notification_id','channel','status','detail','attempted_at'];
    protected $casts = ['attempted_at'=>'datetime'];
}
