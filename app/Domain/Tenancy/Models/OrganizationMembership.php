<?php
namespace App\Domain\Tenancy\Models;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OrganizationMembership extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','organization_id','workspace_id','user_id','role','status'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
}
