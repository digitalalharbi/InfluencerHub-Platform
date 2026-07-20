<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class ClientMemberInvitation extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','client_id','email','role','token_hash','invited_by','expires_at','accepted_at','revoked_at'];
    protected $casts = ['expires_at'=>'datetime','accepted_at'=>'datetime','revoked_at'=>'datetime'];
    public function isPending(): bool {
        return ! $this->accepted_at && ! $this->revoked_at && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
