<?php
namespace App\Domain\Communications\Models;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
class Notification extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','user_id','type','category','title','body','action_url','data','subject_type','subject_id','read_at'];
    protected $casts = ['data'=>'array','read_at'=>'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subject(): MorphTo { return $this->morphTo(); }
    public function deliveryAttempts(): HasMany { return $this->hasMany(NotificationDeliveryAttempt::class); }
    public function isRead(): bool { return $this->read_at !== null; }
}
