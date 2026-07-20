<?php
namespace App\Domain\Billing\Models;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Subscription extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','organization_id','plan_version_id','status','billing_provider','provider_ref','trial_ends_at','current_period_start','current_period_end','overrides'];
    protected $casts = ['trial_ends_at'=>'datetime','current_period_start'=>'datetime','current_period_end'=>'datetime','overrides'=>'array'];
    public function planVersion(): BelongsTo { return $this->belongsTo(PlanVersion::class); }
    public function isActiveLike(): bool { return in_array($this->status, ['trialing','active'], true); }
}
