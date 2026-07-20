<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomFieldValue extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','definition_id','entity_type','entity_id','value'];

    /** تعريف الحقل (الاسم المعروض ونوعه). */
    public function definition(): BelongsTo { return $this->belongsTo(CustomFieldDefinition::class, 'definition_id'); }
}
