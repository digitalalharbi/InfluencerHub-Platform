<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class BrandSocialAccount extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','brand_id','platform','handle','url'];
}
