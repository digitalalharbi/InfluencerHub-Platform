<?php
namespace App\Domain\Campaigns\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CampaignStatusHistory extends Model {
    use BelongsToTenant;
    protected $table = 'campaign_status_history';
    public $timestamps = false;
    protected $fillable = ['tenant_id','campaign_id','from_status','to_status','actor_id','reason','occurred_at'];
    protected $casts = ['occurred_at'=>'datetime'];
}
