<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class CustomFieldOption extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','definition_id','value','label','sort_order'];
    protected $casts = ['sort_order' => 'integer'];
}
