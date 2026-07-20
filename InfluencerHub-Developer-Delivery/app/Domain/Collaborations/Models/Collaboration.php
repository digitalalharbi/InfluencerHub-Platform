<?php
namespace App\Domain\Collaborations\Models;
use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Collaboration extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','collaboration_number','creator_id','campaign_id','deliverable_id','client_id',
        'title','brief','fee_minor','currency','status','due_date','decline_reason','submission_note',
        'offered_at','responded_at','submitted_at','completed_at','created_by'];
    protected $casts = ['fee_minor'=>'integer','due_date'=>'date','offered_at'=>'datetime','responded_at'=>'datetime','submitted_at'=>'datetime','completed_at'=>'datetime'];
    public const STATUSES = ['offered','accepted','declined','in_progress','submitted','approved','completed','cancelled'];
    public const CREATOR_ACTIONABLE = ['offered','accepted','in_progress'];
    public function creator(): BelongsTo { return $this->belongsTo(Creator::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function deliverable(): BelongsTo { return $this->belongsTo(CampaignDeliverable::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function statusHistory(): HasMany { return $this->hasMany(CollaborationStatusHistory::class); }
}
