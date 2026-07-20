<?php
namespace App\Domain\Campaigns\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class CampaignShortlist extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','campaign_id','current_version','status','created_by'];
    protected $casts = ['current_version'=>'integer'];
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function versions(): HasMany { return $this->hasMany(CampaignShortlistVersion::class, 'shortlist_id'); }
    public function currentVersion(): ?CampaignShortlistVersion {
        return $this->versions()->where('version', $this->current_version)->first();
    }
}
