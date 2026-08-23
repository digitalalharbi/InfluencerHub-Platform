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

    /**
     * الأدوار الداخلية للوكالة التي تملك حقّ الدخول إلى بوابة الوكالة (/login → /app).
     * يشمل «مُطّلع» (Viewer) وهو دور قراءة فقط داخل الوكالة، ممنوح صلاحيات العرض في
     * CRM والمالية والمبدعين ولوحة العمليات — لذا يجب أن يستطيع الدخول (بلا صلاحيات تعديل).
     * مصدر الحقيقة الوحيد لبوابة الوكالة؛ تستخدمه بوابتا الدخول العادي وGoogle.
     */
    public static function agencyPortalRoles(): array
    {
        return [
            self::SystemAdmin,
            self::SuperAdmin,
            self::AgencyAdmin,
            self::AgencyEmployee,
            self::OperationsManager,
            self::CampaignManager,
            self::CreatorManager,
            self::Finance,
            self::ContentReviewer,
            self::Viewer,
        ];
    }

    /** قِيَم أدوار بوابة الوكالة (سلاسل) للاستخدام في استعلامات whereIn. */
    public static function agencyPortalRoleValues(): array
    {
        return array_map(fn (self $r) => $r->value, self::agencyPortalRoles());
    }

    public function isOrgAdmin(): bool
    {
        return in_array($this, self::orgAdminRoles(), true);
    }
}
