<?php

namespace App\Domain\Collaborations\Models;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignDeliverable;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collaboration extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'collaboration_number', 'creator_id', 'campaign_id', 'deliverable_id', 'shortlist_item_id', 'client_id',
        'title', 'brief', 'fee_minor', 'currency', 'status', 'due_date', 'decline_reason', 'submission_note',
        'offered_at', 'responded_at', 'submitted_at', 'completed_at', 'created_by'];

    protected $casts = ['fee_minor' => 'integer', 'due_date' => 'date', 'offered_at' => 'datetime', 'responded_at' => 'datetime', 'submitted_at' => 'datetime', 'completed_at' => 'datetime'];

    public const STATUSES = ['offered', 'accepted', 'declined', 'in_progress', 'submitted', 'approved', 'completed', 'cancelled'];

    public const CREATOR_ACTIONABLE = ['offered', 'accepted', 'in_progress'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(CampaignDeliverable::class);
    }

    public function shortlistItem(): BelongsTo
    {
        return $this->belongsTo(CampaignShortlistItem::class, 'shortlist_item_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CollaborationStatusHistory::class);
    }
}
