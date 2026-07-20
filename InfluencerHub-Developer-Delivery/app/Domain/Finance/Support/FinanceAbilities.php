<?php

namespace App\Domain\Finance\Support;

/**
 * صلاحيات مالية دقيقة — منفصلة عن صلاحيات المستندات.
 *
 * كانت المستحقات تُدار بـ`MANAGE_DOCS` التي تضمّ `campaign_manager`، فكان مدير
 * الحملة يعتمد المستحق ويسجّل صرفه. إدارة مستند شيء، وإقرار أن مالًا خرج شيء
 * آخر: الأوّل يُصحَّح بتعديل، والثاني لا يُسترجَع.
 *
 * القاعدة: مَن يطلب لا يعتمد، ومَن يعتمد لا يُقرّ الصرف بالضرورة. الفصل هنا
 * ليس تعقيدًا إداريًّا بل ما يمنع شخصًا واحدًا من إخراج مال بلا شاهد.
 */
class FinanceAbilities
{
    /** قراءة الأرقام المالية. */
    public const VIEW = [
        'super_admin', 'agency_admin', 'operations_manager', 'finance', 'campaign_manager', 'viewer',
    ];

    /** إنشاء الفواتير وإصدارها وإلغاؤها. */
    public const INVOICE_MANAGE = ['super_admin', 'agency_admin', 'operations_manager', 'finance'];

    /** قيد تحصيل على فاتورة — إقرار بدخول مال. */
    public const PAYMENT_RECORD = ['super_admin', 'agency_admin', 'operations_manager', 'finance'];

    /**
     * طلب مستحق لمبدع. مدير الحملة يعرف ما أُنجز فيطلب — ولا يعتمد.
     */
    public const PAYOUT_REQUEST = [
        'super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'finance',
    ];

    /** اعتماد المستحق: إقرار بأن المبلغ مستحقّ فعلًا. */
    public const PAYOUT_APPROVE = ['super_admin', 'agency_admin', 'operations_manager', 'finance'];

    /**
     * تسجيل الصرف — الفعل الذي لا رجعة فيه.
     * مقصور على المالية والإدارة العليا: لا يُقرّ خروج المال إلا من يملك ذلك صراحةً.
     */
    public const PAYOUT_MARK_PAID = ['super_admin', 'agency_admin', 'finance'];

    /** إلغاء مستحق أو فاتورة. */
    public const CANCEL = ['super_admin', 'agency_admin', 'operations_manager', 'finance'];

    /**
     * أدوار تتجاوز فصل الواجبات: الإدارة العليا قد تعتمد ما طلبته حين لا يوجد
     * غيرها. تُستثنى صراحةً لا ضمنًا، ويبقى الأثر في سجلّ التدقيق.
     */
    public const SEGREGATION_EXEMPT = ['super_admin', 'agency_admin'];

    public static function can(?string $role, array $set): bool
    {
        return $role !== null && in_array($role, $set, true);
    }

    /**
     * فصل الواجبات: طالبُ المستحق لا يعتمده.
     *
     * بلا هذا يستطيع شخص واحد أن يطلب ويعتمد ويصرف في ثلاث نقرات، ولا يبقى في
     * السلسلة شاهد واحد.
     */
    public static function mayApproveOwnRequest(?string $role): bool
    {
        return self::can($role, self::SEGREGATION_EXEMPT);
    }
}
