<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorService extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','creator_id','service_type','price_minor','currency','delivery_days','revision_rounds','usage_rights_days','description','is_available'];
    protected $casts = ['is_available' => 'boolean', 'price_minor' => 'integer'];
}
