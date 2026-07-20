<?php
namespace App\Domain\Billing\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Plan extends Model {
    protected $fillable = ['key','name','is_active','applies_to_mode'];
    protected $casts = ['is_active' => 'boolean'];
    public function versions(): HasMany { return $this->hasMany(PlanVersion::class); }
}
