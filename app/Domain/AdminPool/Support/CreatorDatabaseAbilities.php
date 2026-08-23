<?php

namespace App\Domain\AdminPool\Support;

/**
 * صلاحيات «قاعدة المؤثرين» (المنتج المميّز) داخل المؤسسة المُصرَّح لها.
 *
 * طبقتان مستقلّتان من الحوكمة:
 *  - الاستحقاق (Entitlement) يقرّر هل تملك المؤسسة الوصول أصلًا (خطة/إضافة/تجاوز).
 *  - هذه الصلاحيات (RBAC) تقرّر ماذا يفعل المستخدم داخل المؤسسة المُصرَّح لها.
 *
 * منع بالافتراض (deny-by-default). لا تُستخدم لبوّابات العميل/المبدع/الشريك —
 * قاعدة المؤثرين حكرٌ على بوّابة الوكالة.
 */
final class CreatorDatabaseAbilities
{
    /** تصفّح القاعدة والملفات (بلا كشف تواصل بالضرورة). يشمل «مُطّلع» للقراءة. */
    public const VIEW = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager', 'agency_employee', 'viewer'];

    /** كشف وسائل التواصل (هاتف/واتساب/بريد) — بيانات حسّاسة، لا تشمل «مُطّلع». */
    public const VIEW_CONTACT = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager', 'agency_employee'];

    /** ترشيح مبدع من القاعدة إلى حملة (يُنشئ علاقة مبدع للمستأجر). */
    public const USE_IN_CAMPAIGN = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager'];

    /** تصدير جماعي — مقيّد؛ يُدقَّق. لا يُتاح لكل من يرى القاعدة. */
    public const EXPORT = ['super_admin', 'agency_admin', 'operations_manager'];

    public static function can(?string $role, array $set): bool
    {
        return $role !== null && in_array($role, $set, true);
    }
}
