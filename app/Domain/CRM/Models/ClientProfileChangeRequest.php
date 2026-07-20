<?php
namespace App\Domain\CRM\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ClientProfileChangeRequest extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','client_id','requested_by','changes','status','reviewer_note','reviewed_by','reviewed_at'];
    protected $casts = ['changes' => 'array', 'reviewed_at' => 'datetime'];
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
}
