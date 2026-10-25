<?php

namespace App\Domain\Creators\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ملاحظة علاقة على مبدع (CREATOR-01) — مُنطّقة بالمستأجر (fail-closed). نصّ حقيقي يكتبه عضو
 * الوكالة؛ تُغذّي الخطّ الزمني الموحّد. لا بيانات مُختلَقة.
 */
class CreatorNote extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'creator_id', 'author_id', 'body', 'pinned'];

    protected $casts = ['pinned' => 'boolean'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
