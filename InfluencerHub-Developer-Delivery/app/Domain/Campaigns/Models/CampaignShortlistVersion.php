<?php
namespace App\Domain\Campaigns\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class CampaignShortlistVersion extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','shortlist_id','version','status','submitted_at','decided_at'];
    protected $casts = ['submitted_at'=>'datetime','decided_at'=>'datetime'];
    public function shortlist(): BelongsTo { return $this->belongsTo(CampaignShortlist::class, 'shortlist_id'); }
    public function items(): HasMany { return $this->hasMany(CampaignShortlistItem::class, 'shortlist_version_id'); }
}
