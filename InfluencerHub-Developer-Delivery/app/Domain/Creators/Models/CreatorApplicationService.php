<?php
namespace App\Domain\Creators\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CreatorApplicationService extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','application_id','service_type','price_minor','currency','delivery_days','revision_rounds','usage_rights_days','description','is_available'];
}
