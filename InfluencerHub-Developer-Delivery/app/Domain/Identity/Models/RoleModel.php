<?php
namespace App\Domain\Identity\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class RoleModel extends Model {
    protected $table = 'roles';
    protected $fillable = ['key','label'];
    public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class, 'permission_role', 'role_id', 'permission_id'); }
}
