<?php
namespace App\Domain\Finance\Models;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Payout extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','payout_number','creator_id','collaboration_id','contract_id','campaign_id',
        'description','amount_minor','currency','status','iban_last4','due_date','paid_at','payment_reference','failure_reason','created_by'];
    protected $casts = ['amount_minor'=>'integer','due_date'=>'date','paid_at'=>'datetime'];
    public const STATUSES = ['pending','approved','scheduled','waiting_for_provider','paid','failed','cancelled'];
    public const OPEN = ['pending','approved','scheduled','waiting_for_provider'];
    public function creator(): BelongsTo { return $this->belongsTo(Creator::class); }
    public function collaboration(): BelongsTo { return $this->belongsTo(Collaboration::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function statusHistory(): HasMany { return $this->hasMany(PayoutStatusHistory::class); }
    public function isEditable(): bool { return $this->status === 'pending'; }
}
