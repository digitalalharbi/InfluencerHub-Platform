<?php
namespace App\Domain\Partners\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ExternalAgency extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','agency_number','name','legal_name','status','contact_name','contact_email',
        'contact_phone','country_code','website','specialization','notes','submitted_at','reviewed_at','reviewed_by','changes_reason','created_by'];
    protected $casts = ['submitted_at'=>'datetime','reviewed_at'=>'datetime'];
    public const STATUSES = ['draft','submitted','under_review','approved','suspended','archived'];
    public function members(): HasMany { return $this->hasMany(ExternalAgencyMember::class); }
    public function invitations(): HasMany { return $this->hasMany(ExternalAgencyInvitation::class); }
    public function statusHistory(): HasMany { return $this->hasMany(ExternalAgencyStatusHistory::class); }
    public function links(): HasMany { return $this->hasMany(PartnerClientLink::class); }
    public function isActivePartner(): bool { return $this->status === 'approved'; }
}
