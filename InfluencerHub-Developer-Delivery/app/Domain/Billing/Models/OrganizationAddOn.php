<?php
namespace App\Domain\Billing\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class OrganizationAddOn extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','organization_id','add_on_id','quantity','status'];
    public function addOn() { return $this->belongsTo(AddOn::class); }
}
