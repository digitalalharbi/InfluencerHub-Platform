<?php
namespace App\Domain\Partners\Models;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ExternalAgencyMember extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','external_agency_id','user_id','role','status','invited_by','invited_at','accepted_at','suspended_at','revoked_at'];
    protected $casts = ['invited_at'=>'datetime','accepted_at'=>'datetime','suspended_at'=>'datetime','revoked_at'=>'datetime'];
    public function agency(): BelongsTo { return $this->belongsTo(ExternalAgency::class, 'external_agency_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function isActive(): bool { return $this->status === 'active'; }
}
