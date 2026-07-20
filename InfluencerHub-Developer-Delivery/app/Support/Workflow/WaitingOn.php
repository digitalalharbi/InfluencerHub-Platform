<?php

namespace App\Support\Workflow;

/**
 * مَن الكرة في ملعبه الآن، وما الذي ينتظره.
 *
 * صفحات التفاصيل كانت تعرض قائمة إجراءات فارغة (أو «إلغاء» وحده) في الحالات
 * التي تنتظر الطرف الآخر: تعاون «معروض» ينتظر قبول المبدع، ومحتوى في «مراجعة
 * العميل» ينتظر قراره. لا شيء يقول ذلك — فيبدو الأمر عطلًا أو صلاحية ناقصة،
 * والمستخدم يبحث عن زرّ لا وجود له.
 *
 * الانتظار حالة مشروعة في سير العمل، لكنه يجب أن يُعلَن لا أن يُستنتَج.
 */
class WaitingOn
{
    /** الكيان ← الحالة ← [الطرف، ما ينتظره، هل يمكن للوكالة تذكيره]. */
    private const MAP = [
        'collaboration' => [
            'offered' => ['المبدع', 'قبول العرض أو رفضه', true],
            'accepted' => ['المبدع', 'بدء التنفيذ ورفع التسليمات', true],
            'in_progress' => ['المبدع', 'رفع التسليم النهائي', true],
        ],
        'content' => [
            'draft' => ['المبدع', 'رفع المحتوى وإرساله', true],
            'changes_requested' => ['المبدع', 'تنفيذ التعديلات وإعادة الإرسال', true],
            'client_review' => ['العميل', 'اعتماد المحتوى أو طلب تعديل', true],
            'scheduled' => ['النظام', 'حلول موعد النشر المجدول', false],
        ],
        'contract' => [
            'sent' => ['الطرف الآخر', 'توقيع العقد من بوابته', true],
        ],
        'brand' => [
            'submitted' => ['فريق المراجعة', 'بدء المراجعة', false],
        ],
        'shortlist' => [
            'submitted' => ['العميل', 'اعتماد المرشّحين أو رفضهم', true],
        ],
        'payout' => [
            'waiting_for_provider' => ['المالية', 'تسجيل الصرف بعد تنفيذ الحوالة', false],
        ],
        'invoice' => [
            'issued' => ['العميل', 'سداد الفاتورة', true],
            'partially_paid' => ['العميل', 'سداد المتبقّي', true],
        ],
    ];

    /**
     * @return array{party:string,expects:string,canRemind:bool}|null
     *   null يعني أن الدور على صاحب الشاشة لا على غيره.
     */
    public static function for(string $entity, string $status): ?array
    {
        $row = self::MAP[$entity][$status] ?? null;

        return $row ? ['party' => $row[0], 'expects' => $row[1], 'canRemind' => $row[2]] : null;
    }
}
