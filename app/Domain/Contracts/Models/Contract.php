<?php
namespace App\Domain\Contracts\Models;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Contract extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','contract_number','party_type','creator_id','client_id','collaboration_id','campaign_id',
        'title','terms','value_minor','currency','start_date','end_date','status','sent_at','signed_at','signed_by_name','signed_by_user','termination_reason','created_by'];
    protected $casts = ['value_minor'=>'integer','start_date'=>'date','end_date'=>'date','sent_at'=>'datetime','signed_at'=>'datetime'];
    public const STATUSES = ['draft','sent','signed','active','completed','terminated','cancelled'];
    public function creator(): BelongsTo { return $this->belongsTo(Creator::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function collaboration(): BelongsTo { return $this->belongsTo(Collaboration::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function signer(): BelongsTo { return $this->belongsTo(User::class, 'signed_by_user'); }
    public function statusHistory(): HasMany { return $this->hasMany(ContractStatusHistory::class); }
    public function isEditable(): bool { return $this->status === 'draft'; }
}
