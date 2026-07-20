<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomFieldDefinition extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','entity_type','key','label','type','is_required','sort_order','is_active'];
    protected $casts = ['is_required' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    public function options(): HasMany { return $this->hasMany(CustomFieldOption::class, 'definition_id'); }
    public function values(): HasMany { return $this->hasMany(CustomFieldValue::class, 'definition_id'); }
}
