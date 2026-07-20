<?php
namespace App\Domain\Content\Models;
use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class ContentItem extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','content_number','collaboration_id','campaign_id','deliverable_id','creator_id',
        'client_id','title','type','platform','caption','media_url','status','version','scheduled_at','published_at','created_by',
        'published_url','proof_note','proof_by','proof_at',
        'reach','impressions','engagements','clicks','results_source','results_at'];
    protected $casts = ['version'=>'integer','scheduled_at'=>'datetime','published_at'=>'datetime',
        'proof_at'=>'datetime','results_at'=>'datetime',
        'reach'=>'integer','impressions'=>'integer','engagements'=>'integer','clicks'=>'integer'];

    /** إثبات النشر = رابط المنشور الحيّ، لا رابط الأصل الإبداعي (`media_url`). */
    public function hasPublishProof(): bool { return (bool) $this->published_url; }
    public const STATUSES = ['draft','submitted','agency_review','changes_requested','client_review','approved','scheduled','published','rejected'];
    public const CREATOR_EDITABLE = ['draft','changes_requested'];
    public function collaboration(): BelongsTo { return $this->belongsTo(Collaboration::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function deliverable(): BelongsTo { return $this->belongsTo(CampaignDeliverable::class); }
    public function creator(): BelongsTo { return $this->belongsTo(Creator::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function approvals(): HasMany { return $this->hasMany(ContentApproval::class); }
    public function statusHistory(): HasMany { return $this->hasMany(ContentStatusHistory::class); }
}
