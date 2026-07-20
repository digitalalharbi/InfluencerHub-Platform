<?php
namespace App\Domain\Requests\Models;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\ExternalAgency;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class ServiceRequest extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','request_number','requester_type','requester_client_id','requester_agency_id',
        'requested_by','client_id','brand_id','type','title','description','priority','status','assigned_to','due_at','resolved_at','closed_at','sla_reminded_at','sla_breached_at',
        // موجز الحملة — يُلتقط مرّة واحدة وينتقل إلى الحملة عند التحويل
        'budget_minor','currency','preferred_start_date','preferred_end_date','platforms','scope_notes'];
    protected $casts = ['platforms'=>'array','budget_minor'=>'integer','preferred_start_date'=>'date','preferred_end_date'=>'date','due_at'=>'datetime','resolved_at'=>'datetime','closed_at'=>'datetime','sla_reminded_at'=>'datetime','sla_breached_at'=>'datetime'];
    public const STATUSES = ['submitted','triage','in_progress','needs_info','resolved','closed','cancelled'];
    public const OPEN_STATUSES = ['submitted','triage','in_progress','needs_info'];
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function requesterClient(): BelongsTo { return $this->belongsTo(Client::class, 'requester_client_id'); }
    public function requesterAgency(): BelongsTo { return $this->belongsTo(ExternalAgency::class, 'requester_agency_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function comments(): HasMany { return $this->hasMany(ServiceRequestComment::class); }
    public function statusHistory(): HasMany { return $this->hasMany(ServiceRequestStatusHistory::class); }
    public function isOverdue(): bool { return $this->due_at && in_array($this->status, self::OPEN_STATUSES, true) && $this->due_at->isPast(); }
}
