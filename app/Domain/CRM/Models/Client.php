<?php
namespace App\Domain\CRM\Models;
use App\Domain\CRM\Enums\ClientStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};
class Client extends Model {
    use BelongsToTenant, SoftDeletes;
    protected $fillable = ['tenant_id','client_number','type','legal_name','display_name','status','sector','website','email','phone','whatsapp','country_code','city','address','commercial_registration_number','commercial_registration_expiry','tax_number','vat_registered','preferred_language','acquisition_source','account_manager_id','created_by','updated_by','archived_at','import_batch_id','logo_path'];
    protected $casts = ['vat_registered'=>'boolean','commercial_registration_expiry'=>'date','archived_at'=>'datetime'];
    public function brands(): HasMany { return $this->hasMany(Brand::class); }
    public function contacts(): HasMany { return $this->hasMany(ClientContact::class); }
    public function documents(): HasMany { return $this->hasMany(ClientDocument::class); }
    public function members(): HasMany { return $this->hasMany(ClientMember::class); }
    public function accountManager(): BelongsTo { return $this->belongsTo(\App\Domain\Identity\Models\User::class, 'account_manager_id'); }
    public function campaigns(): HasMany { return $this->hasMany(\App\Domain\Campaigns\Models\Campaign::class); }
    public function serviceRequests(): HasMany { return $this->hasMany(\App\Domain\Requests\Models\ServiceRequest::class); }
    public function contracts(): HasMany { return $this->hasMany(\App\Domain\Contracts\Models\Contract::class); }
    public function collaborations(): HasMany { return $this->hasMany(\App\Domain\Collaborations\Models\Collaboration::class); }
    public function contentItems(): HasMany { return $this->hasMany(\App\Domain\Content\Models\ContentItem::class); }
    public function counts(): bool { return ClientStatus::from($this->status)->counts() && ! $this->archived_at && ! $this->trashed(); }
}
