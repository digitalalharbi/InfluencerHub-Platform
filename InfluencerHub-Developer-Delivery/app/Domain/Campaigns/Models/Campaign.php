<?php
namespace App\Domain\Campaigns\Models;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Campaign extends Model {
    use BelongsToTenant;
    protected $fillable = ['tenant_id','campaign_number','client_id','brand_id','source_request_id','name','objective',
        'brief','status','budget_minor','currency','start_date','end_date','created_by'];
    protected $casts = ['budget_minor'=>'integer','start_date'=>'date','end_date'=>'date'];
    public const STATUSES = ['draft','planning','active','paused','completed','cancelled'];
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function sourceRequest(): BelongsTo { return $this->belongsTo(ServiceRequest::class, 'source_request_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function deliverables(): HasMany { return $this->hasMany(CampaignDeliverable::class); }
    public function statusHistory(): HasMany { return $this->hasMany(CampaignStatusHistory::class); }
    public function collaborations(): HasMany { return $this->hasMany(\App\Domain\Collaborations\Models\Collaboration::class); }
    public function contentItems(): HasMany { return $this->hasMany(\App\Domain\Content\Models\ContentItem::class); }
    /** إجمالي أجور المخرجات (وحدات صغرى) — لمقارنته بالميزانية. */
    public function committedMinor(): int { return (int) $this->deliverables->sum(fn($d) => (int)($d->fee_minor ?? 0) * (int)$d->quantity); }
    public function isEditable(): bool { return in_array($this->status, ['draft','planning'], true); }
}
