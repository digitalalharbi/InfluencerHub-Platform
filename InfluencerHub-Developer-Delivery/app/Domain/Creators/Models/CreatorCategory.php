<?php
namespace App\Domain\Creators\Models;
use Illuminate\Database\Eloquent\Model;
class CreatorCategory extends Model {
    protected $fillable = ['tenant_id','slug','name_ar','name_en','sort_order','is_active'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];
}
