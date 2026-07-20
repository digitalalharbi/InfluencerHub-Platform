<?php
namespace App\Domain\CRM\Support;

/**
 * مصفوفة صلاحيات CRM حسب دور المؤسسة. مصدر واحد للحقيقة تستخدمه كل السياسات.
 * الأدوار المذكورة صراحةً فقط تملك القدرة؛ ما عداها مرفوض (deny-by-default).
 */
final class CrmAbilities {
    // من يرى عملاء/علامات الوكالة
    public const VIEW = ['super_admin','agency_admin','operations_manager','campaign_manager','agency_employee','creator_manager','content_reviewer','finance','viewer'];
    // من ينشئ/يُحدّث
    public const WRITE = ['super_admin','agency_admin','operations_manager','campaign_manager'];
    // من يؤرشف/يحذف
    public const DELETE = ['super_admin','agency_admin','operations_manager'];
    // من يدير أعضاء بوابة العميل (دعوة/تعليق/إلغاء)
    public const MANAGE_PORTAL = ['super_admin','agency_admin','operations_manager'];
    // من يدير المستندات (رفع/حذف)
    public const MANAGE_DOCS = ['super_admin','agency_admin','operations_manager','campaign_manager','finance'];

    // الصلاحيات المالية انتقلت إلى App\Domain\Finance\Support\FinanceAbilities:
    // مفصولة بالفعل (طلب/اعتماد/صرف) لا بمجموعة واحدة، ومصدرها هناك وحده.

    public static function can(?string $role, array $set): bool {
        return $role !== null && in_array($role, $set, true);
    }
}
