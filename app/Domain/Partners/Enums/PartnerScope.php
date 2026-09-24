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
    /** التسمية تُحلّ باللغة الحالية عبر trans('partner_dashboard.scope_*'). */
    public static function labels(): array {
        return [
            'view_briefs' => trans('partner_dashboard.scope_view_briefs'),
            'submit_content' => trans('partner_dashboard.scope_submit_content'),
            'view_reports' => trans('partner_dashboard.scope_view_reports'),
            'manage_creators' => trans('partner_dashboard.scope_manage_creators'),
            'view_contracts' => trans('partner_dashboard.scope_view_contracts'),
        ];
    }
}
