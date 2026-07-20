<?php
namespace App\Domain\Tenancy\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class Invitation extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','organization_id','workspace_id','email','role','token','invited_by','status','expires_at','accepted_at'];
    protected $casts = ['expires_at' => 'datetime','accepted_at' => 'datetime'];
    public function isPending(): bool { return $this->status === 'pending' && (! $this->expires_at || $this->expires_at->isFuture()); }
}
