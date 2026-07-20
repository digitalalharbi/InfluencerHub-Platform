<?php

namespace App\Domain\Brands\Models;

use App\Domain\CRM\Models\Brand;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * طلب المطالبة بعلامة قائمة.
 *
 * لا `BelongsToTenant`: الطلب يقع بين طالبٍ بلا مستأجر وعلامةٍ في مستأجر
 * قائم، فتنطيقه بأحدهما يُعمي الآخر. الحراسة على المراجع لا على النطاق.
 */
class BrandClaimRequest extends Model
{
    protected $fillable = [
        'reference', 'brand_id', 'signup_id', 'requester_email', 'requester_user_id',
        'status', 'evidence', 'match_signals', 'match_score', 'corporate_email_verified',
        'reviewed_by', 'reviewed_at', 'decision_reason', 'info_requested',
        'expires_at', 'cancelled_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'match_signals' => 'array',
        'match_score' => 'integer',
        'corporate_email_verified' => 'boolean',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const PENDING = 'pending';

    public const UNDER_REVIEW = 'under_review';

    public const MORE_INFO = 'more_info_requested';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    /** الحالات التي يشغل فيها الطلب «مكانه» فيمنع طلبًا آخر لنفس العلامة والبريد. */
    public const LIVE = [self::PENDING, self::UNDER_REVIEW, self::MORE_INFO];

    /**
     * الانتقالات المسموحة.
     *
     * `approved` و`rejected` نهائيّتان: قرارٌ اعتُمد ومُنح عليه وصول لا يُسحب
     * بتعديل حالة، بل بإلغاء العلاقة نفسها — وذاك مسار آخر له سجلّه.
     */
    public const ALLOWED = [
        self::PENDING => [self::UNDER_REVIEW, self::REJECTED, self::EXPIRED, self::CANCELLED],
        self::UNDER_REVIEW => [self::APPROVED, self::REJECTED, self::MORE_INFO, self::EXPIRED, self::CANCELLED],
        self::MORE_INFO => [self::UNDER_REVIEW, self::REJECTED, self::EXPIRED, self::CANCELLED],
        self::APPROVED => [],
        self::REJECTED => [],
        self::EXPIRED => [],
        self::CANCELLED => [],
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function signup(): BelongsTo
    {
        return $this->belongsTo(BrandSignup::class, 'signup_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BrandClaimDocument::class, 'claim_request_id');
    }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE, true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::ALLOWED[$this->status] ?? [], true);
    }
}
