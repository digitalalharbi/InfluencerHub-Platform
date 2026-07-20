<?php
namespace App\Domain\Identity\Enums;

enum Role: string
{
    case SystemAdmin          = 'system_admin';
    case SuperAdmin           = 'super_admin';
    case AgencyAdmin          = 'agency_admin';
    case AgencyEmployee       = 'agency_employee';
    case OperationsManager    = 'operations_manager';
    case CampaignManager      = 'campaign_manager';
    case CreatorManager       = 'creator_manager';
    case Finance              = 'finance';
    case ContentReviewer      = 'content_reviewer';
    case Influencer           = 'influencer';
    case UgcCreator           = 'ugc_creator';
    case InfluencerAndUgc     = 'influencer_and_ugc';
    case BrandAdmin           = 'brand_admin';
    case BrandMember          = 'brand_member';
    case ExternalAgencyAdmin  = 'external_agency_admin';
    case ExternalAgencyMember = 'external_agency_member';
    case Viewer               = 'viewer';

    /** أدوار إدارية للمؤسسة (تدير الأعضاء والإعدادات). */
    public static function orgAdminRoles(): array
    {
        return [self::SuperAdmin, self::AgencyAdmin, self::BrandAdmin, self::ExternalAgencyAdmin];
    }

    public function isOrgAdmin(): bool
    {
        return in_array($this, self::orgAdminRoles(), true);
    }
}
