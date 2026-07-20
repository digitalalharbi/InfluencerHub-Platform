<?php

namespace App\Domain\Creators\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** دعوة صانع محتوى موجود إلى بوابته. الرمز الخام لا يُخزَّن — Hash فقط. */
class CreatorInvitation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'creator_id', 'email', 'phone', 'token_hash',
        'email_code', 'email_verified_at', 'phone_code', 'phone_verified_at',
        'invited_by', 'expires_at', 'accepted_at', 'revoked_at', 'sent_count', 'last_sent_at'];

    protected $casts = [
        'email_verified_at' => 'datetime', 'phone_verified_at' => 'datetime',
        'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime',
        'last_sent_at' => 'datetime', 'sent_count' => 'integer',
    ];

    public function creator(): BelongsTo { return $this->belongsTo(Creator::class); }

    /** صالحة = لم تُقبل ولم تُلغَ ولم تنتهِ. الثلاثة تُفحص معًا دائمًا. */
    public function isUsable(): bool
    {
        return ! $this->accepted_at
            && ! $this->revoked_at
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /** سبب عدم الصلاحية — يُقال للمستخدم بدل «رابط غير صالح» المبهمة. */
    public function unusableReason(): ?string
    {
        if ($this->accepted_at) return 'هذه الدعوة استُخدمت من قبل. سجّل الدخول أو اطلب استعادة كلمة المرور.';
        if ($this->revoked_at) return 'أُلغيت هذه الدعوة. تواصل مع الوكالة لإرسال دعوة جديدة.';
        if ($this->expires_at && $this->expires_at->isPast()) return 'انتهت صلاحية الدعوة. اطلب من الوكالة إعادة إرسالها.';

        return null;
    }

    public function isFullyVerified(): bool
    {
        return $this->email_verified_at && $this->phone_verified_at;
    }
}
