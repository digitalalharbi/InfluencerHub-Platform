<?php

namespace App\Domain\Campaigns\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignShortlistVersion extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'shortlist_id', 'version', 'status', 'submitted_at', 'decided_at'];

    protected $casts = ['submitted_at' => 'datetime', 'decided_at' => 'datetime'];

    /** مصدر واحد لتسميات حالة الإصدار — تشاركها كل السطوح (workspace + سياق العلامة). */
    public const STATUS_LABELS = ['draft' => 'مسودة', 'submitted' => 'بانتظار العميل', 'approved' => 'مُعتمَد',
        'partially_approved' => 'اعتماد جزئي', 'changes_requested' => 'مطلوب بديل', 'rejected' => 'مرفوض'];

    public static function statusLabel(?string $s): string
    {
        return self::STATUS_LABELS[$s ?? 'draft'] ?? ($s ?? 'مسودة');
    }

    public function shortlist(): BelongsTo
    {
        return $this->belongsTo(CampaignShortlist::class, 'shortlist_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CampaignShortlistItem::class, 'shortlist_version_id');
    }
}
