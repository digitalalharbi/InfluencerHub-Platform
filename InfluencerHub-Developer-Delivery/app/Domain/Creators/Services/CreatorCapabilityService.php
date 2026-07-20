<?php

namespace App\Domain\Creators\Services;

use App\Domain\Creators\Models\{Creator, CreatorCapability};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * المصدر الوحيد لقراءة وكتابة قدرات صانع المحتوى.
 *
 * قبل هذا كان كل موضع يفسّر `creators.type` بنفسه: قائمة الوكالة تكتب
 * `where type = X or type = both`، والتحليلات تكرّر نفس الشرط، والقبول يشتقّ
 * الدور من النص. تكرار التفسير هو ما يجعل التطبيع يتسرّب: يكفي أن ينسى موضع
 * واحد «both» ليختفي نصف المبدعين من نتيجة. لذلك جُمع التفسير هنا مرة واحدة.
 *
 * الكتابة مزدوجة عن قصد (القدرات + العمود القديم) — انظر sync().
 */
class CreatorCapabilityService
{
    /**
     * خريطة النوع القديم → القدرات المكافئة.
     * نسخة مطابقة لخريطة الهجرة، وتبقى هنا لأن القراءة الاحتياطية للصفوف غير
     * المنقولة تحتاجها في وقت التشغيل لا في وقت الهجرة فقط.
     */
    public const LEGACY_TO_CAPS = [
        'influencer' => ['influencer'],
        'ugc_creator' => ['ugc'],
        'both' => ['influencer', 'ugc'],
    ];

    /** @return array<int,string> مفاتيح القدرات المعروفة */
    public static function keys(): array
    {
        return array_keys(CreatorCapability::LABELS);
    }

    /** خيارات العرض (مفتاح → تسمية عربية) للواجهات. */
    public static function options(): array
    {
        return CreatorCapability::LABELS;
    }

    /**
     * قواعد التحقّق. الحدّ الأدنى قدرة واحدة: صانع بلا قدرة لا يمكن ترشيحه
     * لأي عمل، فالسماح بصفر يُنشئ ملفًّا ميتًا يظهر في القوائم ولا يُطابق شيئًا.
     */
    public static function rules(string $field = 'capabilities', bool $required = true): array
    {
        $allowed = implode(',', self::keys());

        return [
            $field => ($required ? 'required|' : 'nullable|') . 'array|min:1',
            "{$field}.*" => "required|string|in:{$allowed}",
        ];
    }

    /** رسائل عربية صريحة (الرسالة الافتراضية لـ min:1 على مصفوفة غير مفهومة للمستخدم). */
    public static function messages(string $field = 'capabilities'): array
    {
        return [
            "{$field}.required" => 'اختر قدرة واحدة على الأقل.',
            "{$field}.min" => 'اختر قدرة واحدة على الأقل.',
            "{$field}.*.in" => 'قدرة غير معروفة.',
        ];
    }

    /** يُبقي المعروف فقط ويزيل التكرار — مدخل المستخدم لا يُصدَّق كما هو. */
    public static function normalize(array $caps): array
    {
        return array_values(array_intersect(self::keys(), array_unique(array_filter($caps, 'is_string'))));
    }

    /**
     * يشتقّ قيمة `creators.type` القديمة من القدرات المختارة.
     *
     * العمود القديم يعرف ثلاث قيم فقط، والقدرات إحدى عشرة؛ الاشتقاق بطبيعته
     * فاقد للمعلومة ولا يمكن أن يكون غير ذلك. من يملك قدرة إنتاجية بحتة
     * (تصوير، مونتاج، تعليق صوتي) دون نشر على حسابه يُسقَط على `ugc_creator`
     * لأنه أقرب ما يصفه العمود القديم: ينتج محتوى ولا يوزّعه بجمهوره.
     * القراءة الصحيحة تبقى من جدول القدرات؛ هذه القيمة للتوافق الخلفي فقط.
     */
    public static function legacyType(array $caps): string
    {
        $caps = self::normalize($caps);
        $influencer = in_array('influencer', $caps, true);
        $ugc = in_array('ugc', $caps, true);

        return match (true) {
            $influencer && $ugc => 'both',
            $influencer => 'influencer',
            default => 'ugc_creator',
        };
    }

    /**
     * الأنواع القديمة التي كانت تعني هذه القدرة — تُستعمل في المسار الاحتياطي
     * للقراءة، فلا يختفي صانع لم تُنقل قدراته بعد.
     *
     * @return array<int,string>
     */
    public static function legacyTypesFor(string $capability): array
    {
        return array_keys(array_filter(
            self::LEGACY_TO_CAPS,
            fn (array $caps) => in_array($capability, $caps, true)
        ));
    }

    /**
     * يكتب قدرات الصانع كما اختارها بالضبط (يحذف ما أُلغي، يضيف ما جُدّد).
     *
     * ⚠️ كتابة مزدوجة مقصودة، وليست تكرارًا سهوًا:
     * العمود `creators.type` لم يُحذف بعد لأنه ما يزال مسارَ قراءة احتياطيًّا
     * (Creator::hasCapability يرجع إليه لصانع بلا صفوف قدرات). ولو توقّفنا عن
     * تحديثه لأصبح يكذب: صانع يضيف قدرة UGC اليوم يبقى `influencer` في العمود،
     * فيختفي من أي قراءة قديمة لم تُهاجَر بعد. لذلك يُحدَّث معه في نفس المعاملة.
     * يُحذف العمود — ومعه هذا السطر — بعد إثبات أن لا قارئ له.
     */
    public static function sync(Creator $creator, array $caps, string $source = 'manual'): array
    {
        $caps = self::normalize($caps);

        return self::withTenant($creator->tenant_id, function () use ($creator, $caps, $source) {
            return DB::transaction(function () use ($creator, $caps, $source) {
                // ما لم يعد مختارًا يُحذف: القدرة ادّعاء حالي، لا سجلّ تاريخي
                CreatorCapability::where('creator_id', $creator->id)
                    ->whereNotIn('capability', $caps ?: [''])
                    ->delete();

                foreach ($caps as $cap) {
                    CreatorCapability::updateOrCreate(
                        ['creator_id' => $creator->id, 'capability' => $cap],
                        ['tenant_id' => $creator->tenant_id, 'is_enabled' => true, 'source' => $source],
                    );
                }

                // الشقّ الثاني من الكتابة المزدوجة (انظر شرح التوثيق أعلاه)
                $creator->forceFill(['type' => self::legacyType($caps)])->save();
                $creator->setRelation('capabilities', CreatorCapability::where('creator_id', $creator->id)->get());

                return $caps;
            });
        });
    }

    /**
     * فلترة استعلام المبدعين بقدرة واحدة.
     *
     * الشقّ الثاني من الشرط ليس زينة: لو اكتفينا بـ whereHas لسقط صامتًا أي
     * صانع أُنشئ خارج هذا المسار (استيراد، بذرة، صفّ لم تلحقه الهجرة). إسقاط
     * صفوف بصمت أسوأ من إظهار صفّ زائد، لأن أحدًا لا يلاحظه.
     */
    public static function filter(Builder $query, string $capability): Builder
    {
        $legacy = self::legacyTypesFor($capability);

        return $query->where(fn ($w) => $w
            ->whereHas('capabilities', fn ($c) => $c->where('capability', $capability)->where('is_enabled', true))
            ->orWhere(fn ($q) => $q->whereDoesntHave('capabilities')->whereIn('type', $legacy ?: ['']))
        );
    }

    /**
     * يشغّل عملية ضمن سياق مستأجر معيّن ثم يعيد السياق السابق كما كان.
     *
     * كانت هنا نسخة يدوية تحمل عيبها في وضح النهار: تأخذ لقطة في `$__ctx`
     * **ولا تستعيدها أبدًا**، بل تُعيد ثلاثة حقول بيدها ثم التجاوز. أي حقل
     * يُضاف إلى السياق مستقبلًا يسقط هنا صامتًا. `TenantContext::withTenant`
     * تستعيد اللقطة كاملةً، وتُبقي المؤسسة عند إعادة تأكيد المستأجر نفسه.
     */
    private static function withTenant(int $tenantId, callable $fn)
    {
        return TenantContext::withTenant($tenantId, $fn);
    }
}
