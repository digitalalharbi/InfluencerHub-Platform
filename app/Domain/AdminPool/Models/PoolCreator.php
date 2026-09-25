<?php

namespace App\Domain\AdminPool\Models;

use App\Domain\AdminPool\Support\CreatorNormalizer;
use App\Support\Platforms\PlatformRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * مبدع في قاعدة مدير النظام.
 *
 * لا BelongsToTenant: هذه قاعدة مركزية لمدير النظام، لا تخصّ مستأجرًا. الوصول
 * محكوم بـ`is_system_admin` في السياسة، لا بنطاق المستأجر.
 */
class PoolCreator extends Model
{
    protected $table = 'admin_creator_pool';

    protected $fillable = [
        'name', 'phone', 'platform', 'account_url', 'followers', 'tier', 'gender',
        'categories', 'price_post_minor', 'price_coverage_minor', 'cost_post_minor', 'cost_coverage_minor', 'shows_face',
        'region', 'city', 'rating', 'likes', 'store', 'source_type', 'imported_at',
    ];

    protected $casts = [
        'categories' => 'array',
        'shows_face' => 'bool',
        'followers' => 'int',
        'likes' => 'int',
        'price_post_minor' => 'int',
        'price_coverage_minor' => 'int',
        'cost_post_minor' => 'int',
        'cost_coverage_minor' => 'int',
        'imported_at' => 'datetime',
    ];

    /** @return array<string,mixed> صفّ ببيانات الحجز الكاملة (لمدير النظام). */
    public function toBookingArray(?array $match = null): array
    {
        $riyals = fn (?int $m) => $m ? intdiv($m, 100) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'platformLabel' => self::platformLabel($this->platform),
            'accountUrl' => $this->account_url,
            'phone' => $this->phone,
            'followers' => $this->followers,
            'tier' => $this->tier,
            'gender' => $this->gender,
            'categories' => $this->categories ?? [],
            'costPost' => $riyals($this->cost_post_minor),
            'costCoverage' => $riyals($this->cost_coverage_minor),
            'sellPost' => $riyals($this->price_post_minor),
            'sellCoverage' => $riyals($this->price_coverage_minor),
            'showsFace' => $this->shows_face,
            'region' => $this->region,
            'city' => $this->city,
            'rating' => $this->rating,
            'likes' => $this->likes,
            'store' => $this->store,
            'sourceType' => $this->source_type,
            'matchScore' => $match['score'] ?? null,
            'matchReasons' => $match['reasons'] ?? [],
            'matchFlags' => $match['flags'] ?? [],
        ];
    }

    /** تصنيف المبدع المعروض للمستأجر (لا «مصدر»): celebrity→مؤثّر، ugc→صانع UGC. مصدر احتياطيّ عربيّ. */
    public const CREATOR_TYPE_LABELS = [
        'celebrity' => 'مؤثّر',
        'ugc' => 'صانع UGC',
    ];

    /** اسم المنصّة بلغة الطلب — من المصدر الوحيد PlatformRegistry (لا خريطة مكرّرة). */
    public static function platformLabel(?string $key): string
    {
        return PlatformRegistry::label((string) $key);
    }

    /** تسمية تصنيف المبدع بلغة الطلب، مع رجوع للثابت العربيّ إن غابت الترجمة. */
    public static function creatorTypeLabel(string $type): string
    {
        $t = trans("creator_database.type_$type");

        return (is_string($t) && $t !== "creator_database.type_$type") ? $t : (self::CREATOR_TYPE_LABELS[$type] ?? $type);
    }

    /**
     * تمثيل «قاعدة المؤثرين» المعروض للمستأجر — منتج اكتشاف المبدعين.
     *
     * يُقصى نهائيًّا كلّ ما يكشف المصدر أو الخصوصية: المتجر/المصدر/الموظّف،
     * التكلفة الداخلية، والبيانات البنكية/العنوان/الشحنات (غير مخزَّنة أصلًا).
     * `source_type` يُعرَض كـ«نوع المبدع» (تصنيف) لا كمصدر. وسائل التواصل تظهر
     * فقط عند تمرير `$withContact=true` (محكوم بصلاحية RBAC في المتحكّم).
     *
     * @param  array<string,mixed>|null  $match  درجة/أسباب المطابقة (اختياري)
     * @return array<string,mixed>
     */
    public function toSharedArray(bool $withContact = false, ?array $match = null): array
    {
        $riyals = fn (?int $m) => $m !== null ? intdiv($m, 100) : null;
        $type = $this->source_type === 'ugc' ? 'ugc' : 'celebrity';

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'platformLabel' => self::platformLabel($this->platform),
            'accountUrl' => $this->account_url,
            'followers' => $this->followers,
            'likes' => $this->likes,
            'tier' => $this->tier,
            'gender' => $this->gender,
            'categories' => $this->categories ?? [],
            'showsFace' => $this->shows_face,
            'region' => $this->region,
            'city' => $this->city,
            'rating' => $this->rating,
            // تصنيف لا مصدر — الكلمة «مصدر» لا تُستخدم في المنتج
            'creatorType' => $type,
            'creatorTypeLabel' => self::creatorTypeLabel($type),
            // سعر مرجعي غير مضمون (سعر البيع فقط، لا التكلفة). التفاوض الفعلي في بيانات المستأجر
            'referenceRate' => $riyals($this->price_coverage_minor ?? $this->price_post_minor),
            'referenceRateNote' => trans('creator_database.reference_rate_note'),
            'dataFreshness' => trans('creator_database.data_freshness'),
            'lastImportedAt' => optional($this->imported_at)?->toDateString(),
            'matchScore' => $match['score'] ?? null,
            'matchReasons' => $match['reasons'] ?? [],
        ];

        if ($withContact) {
            $phone = $this->phone ? CreatorNormalizer::phone($this->phone) : null;
            $data['contact'] = [
                'phone' => $phone,
                'phoneDisplay' => CreatorNormalizer::phoneDisplay($phone),
                'whatsapp' => $phone,           // نفس الرقم القانوني إن كان جوّالًا صالحًا
                'hasPhone' => $phone !== null,
            ];
        }

        return $data;
    }
}
