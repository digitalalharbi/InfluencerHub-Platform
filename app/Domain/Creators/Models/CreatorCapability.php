<?php

namespace App\Domain\Creators\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قدرة واحدة لصانع المحتوى (مؤثّر، UGC، تصوير، تعليق صوتي…).
 *
 * القدرات تُجمَع ولا تُقصي بعضها: الصانع نفسه قد ينشر منشورًا مموّلًا وينتج
 * فيديو UGC لا يُنشر على حسابه. لذلك هي سجلّات لا قيمة نصية واحدة.
 */
class CreatorCapability extends Model
{
    use BelongsToTenant;

    /** القدرات المعروفة → التسمية العربية. المفتاح ثابت، التسمية للعرض. */
    public const LABELS = [
        'influencer' => 'مؤثّر',
        'ugc' => 'محتوى من صنع المستخدم (UGC)',
        'publisher' => 'ناشر',
        'photographer' => 'مصوّر فوتوغرافي',
        'videographer' => 'مصوّر فيديو',
        'voiceover' => 'تعليق صوتي',
        'editor' => 'مونتاج',
        'livestream' => 'بثّ مباشر',
        'scriptwriter' => 'كتابة نصوص',
        'product_review' => 'مراجعة منتجات',
        'event_coverage' => 'تغطية فعاليات',
    ];

    protected $fillable = [
        'tenant_id', 'creator_id', 'capability', 'is_enabled', 'approval_status',
        'experience_level', 'base_rate_minor', 'delivery_days', 'included_revisions', 'source',
    ];

    protected $casts = [
        'is_enabled' => 'bool',
        'base_rate_minor' => 'int',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? $key;
    }
}
