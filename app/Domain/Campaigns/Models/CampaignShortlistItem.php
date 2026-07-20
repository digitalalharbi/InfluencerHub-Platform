<?php
namespace App\Domain\Campaigns\Models;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CampaignShortlistItem extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','shortlist_version_id','creator_id','is_backup','proposed_fee_minor','match_score','reasons','client_decision','decision_reason'];
    protected $casts = ['is_backup'=>'boolean','reasons'=>'array','proposed_fee_minor'=>'integer','match_score'=>'integer'];
    public function creator(): BelongsTo { return $this->belongsTo(Creator::class); }
    public function version(): BelongsTo { return $this->belongsTo(CampaignShortlistVersion::class, 'shortlist_version_id'); }
}
