<?php
namespace App\Domain\Campaigns\Models;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CampaignDeliverable extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','campaign_id','creator_id','platform','type','quantity','fee_minor','currency','due_date','status','notes'];
    protected $casts = ['quantity'=>'integer','fee_minor'=>'integer','due_date'=>'date'];
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function creator(): BelongsTo { return $this->belongsTo(Creator::class); }
}
