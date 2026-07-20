<?php
namespace App\Domain\CRM\Support;
/** صلاحيات بوابة العميل حسب دور العضوية. */
final class ClientPortalAbilities {
    public const EDIT_PROFILE = ['client_admin'];
    public const EDIT_BILLING = ['client_admin', 'client_finance'];
    public const MANAGE_TEAM  = ['client_admin'];
    public const MANAGE_DOCS   = ['client_admin', 'client_finance'];
    public const MANAGE_BRANDS = ['client_admin', 'client_campaign_manager'];

    // حقول قانونية حساسة → تمرّ عبر طلب مراجعة، لا تُطبَّق مباشرة
    public const SENSITIVE = ['legal_name', 'commercial_registration_number', 'commercial_registration_expiry', 'tax_number', 'vat_registered'];
    // حقول لا يجوز للعميل تغييرها إطلاقًا
    public const FORBIDDEN = ['tenant_id', 'status', 'account_manager_id', 'client_number', 'id'];

    public static function can(?string $role, array $set): bool { return $role !== null && in_array($role, $set, true); }
}
