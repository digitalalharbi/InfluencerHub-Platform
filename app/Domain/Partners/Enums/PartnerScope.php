<?php
namespace App\Domain\Partners\Enums;
/** نطاقات الوصول الممنوحة لشريك على عميل/علامة. */
enum PartnerScope: string {
    case ViewBriefs = 'view_briefs';
    case SubmitContent = 'submit_content';
    case ViewReports = 'view_reports';
    case ManageCreators = 'manage_creators';
    case ViewContracts = 'view_contracts';
    public static function values(): array { return array_map(fn($c)=>$c->value, self::cases()); }
    public static function labels(): array {
        return ['view_briefs'=>'عرض البريفات','submit_content'=>'تقديم المحتوى','view_reports'=>'عرض التقارير','manage_creators'=>'إدارة المبدعين','view_contracts'=>'عرض العقود'];
    }
}
