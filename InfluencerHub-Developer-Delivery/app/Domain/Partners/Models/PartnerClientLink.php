<?php
namespace App\Domain\Partners\Models;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PartnerClientLink extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','external_agency_id','client_id','brand_id','scopes','status','created_by'];
    protected $casts = ['scopes'=>'array'];
    public function agency(): BelongsTo { return $this->belongsTo(ExternalAgency::class, 'external_agency_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function hasScope(string $scope): bool { return in_array($scope, $this->scopes ?? [], true); }
}
