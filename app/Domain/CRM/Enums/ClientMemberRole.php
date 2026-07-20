<?php
namespace App\Domain\CRM\Enums;
enum ClientMemberRole: string {
    case ClientAdmin='client_admin'; case ClientCampaignManager='client_campaign_manager';
    case ClientContentReviewer='client_content_reviewer'; case ClientFinance='client_finance';
    case ClientReportViewer='client_report_viewer'; case ClientMember='client_member';
    public static function values(): array { return array_map(fn($c)=>$c->value, self::cases()); }
}
