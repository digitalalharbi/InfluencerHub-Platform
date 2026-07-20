<?php
namespace App\Domain\Tenancy\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class Organization extends Model {
    use SoftDeletes, BelongsToTenant;
    protected $fillable = ['tenant_id','name','slug','type','status','contact_email','settings'];
    protected $casts = ['settings' => 'array'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function workspaces(): HasMany { return $this->hasMany(Workspace::class); }
    public function memberships(): HasMany { return $this->hasMany(OrganizationMembership::class); }
}
