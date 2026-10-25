<?php

namespace App\Domain\Creators\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مهمّة علاقة على مبدع (CREATOR-01) — مُنطّقة بالمستأجر (fail-closed). متابعة حقيقية
 * (عنوان + استحقاق + حالة open/done). تُغذّي الخطّ الزمني و«المطلوب مني الآن».
 */
class CreatorTask extends Model
{
    use BelongsToTenant;

    public const OPEN = 'open';

    public const DONE = 'done';

    protected $fillable = ['tenant_id', 'creator_id', 'created_by', 'assigned_to', 'title', 'due_at', 'status', 'completed_at'];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
