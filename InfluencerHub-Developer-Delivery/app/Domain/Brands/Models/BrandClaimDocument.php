<?php

namespace App\Domain\Brands\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** مستند إثبات ملكية مرفوع مع طلب مطالبة. يُخزَّن على قرص خاصّ. */
class BrandClaimDocument extends Model
{
    protected $fillable = [
        'claim_request_id', 'type', 'path', 'original_name', 'mime', 'size_bytes', 'uploaded_by',
    ];

    protected $casts = ['size_bytes' => 'integer'];

    public const TYPES = ['commercial_registration', 'authorization_letter', 'trademark', 'other'];

    public function claimRequest(): BelongsTo
    {
        return $this->belongsTo(BrandClaimRequest::class, 'claim_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
