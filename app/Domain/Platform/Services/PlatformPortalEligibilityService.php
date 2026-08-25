<?php

namespace App\Domain\Platform\Services;

use App\Domain\CRM\Models\ClientMember;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorEntitlementService;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember};
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * مصدر واحد لأهلية البوّابات (§ P2-hardening). يعكس **بالضبط** ما تشترطه حرّاس
 * البوّابات الفعلية (EnsureAgencyMember/EnsureClientMember/EnsureCreator/
 * EnsurePartnerMember) فلا تتكرّر منطق الأهلية. يستعمله كلٌّ من:
 *  - تفاصيل المستأجر في P2 (أي بوّابات متاحة لهذا المستأجر)،
 *  - معاينة P3 (سياقات المستخدم المؤهَّلة + إيجاد مستخدم مؤهَّل لبوّابة).
 * يعمل بـwithBypass (عبر المستأجرين).
 */
class PlatformPortalEligibilityService
{
    /** أدوار البوّابة التي لا تفتح الوكالة (مطابق EnsureAgencyMember::PORTAL_ROLES). */
    private const AGENCY_PORTAL_ROLES = ['influencer', 'ugc_creator', 'influencer_and_ugc'];

    public function __construct(private CreatorEntitlementService $entitlements)
    {
    }

    /**
     * كل سياقات البوّابة المؤهَّلة لمستخدم — قد تكون متعدّدة وعبر عدّة مستأجرين.
     * لا نكتفي بأول عضوية مؤسسة. كل عنصر:
     * {portal, tenantId, organizationId|null, entityId} — entityId = معرّف الكيان
     * الذي يجعله مؤهَّلًا (org/client/creator/agency).
     * @return list<array{portal:string,tenantId:int,organizationId:?int,entityId:int}>
     */
    public function contextsForUser(User $user): array
    {
        return TenantContext::withBypass(function () use ($user) {
            $ctx = [];

            // الوكالة: عضوية نشطة بدور ليس دور بوابة.
            foreach (OrganizationMembership::where('user_id', $user->id)->where('status', 'active')->get() as $m) {
                if (! in_array($m->role, self::AGENCY_PORTAL_ROLES, true)) {
                    $ctx[] = ['portal' => 'agency', 'tenantId' => (int) $m->tenant_id, 'organizationId' => (int) $m->organization_id, 'entityId' => (int) $m->organization_id];
                }
            }
            // العميل: عضوية عميل نشطة.
            foreach (ClientMember::where('user_id', $user->id)->where('status', 'active')->get() as $cm) {
                $ctx[] = ['portal' => 'client', 'tenantId' => (int) $cm->tenant_id, 'organizationId' => null, 'entityId' => (int) $cm->client_id];
            }
            // صانع المحتوى: ملفّ مربوط بالمستخدم + بوّابة مفعَّلة في خطة الوكالة.
            foreach (Creator::where('user_id', $user->id)->get() as $cr) {
                if ($this->creatorPortalEnabled((int) $cr->tenant_id)) {
                    $ctx[] = ['portal' => 'creator', 'tenantId' => (int) $cr->tenant_id, 'organizationId' => null, 'entityId' => (int) $cr->id];
                }
            }
            // الشريك: عضوية شريك نشطة + وكالة معتمدة.
            foreach (ExternalAgencyMember::where('user_id', $user->id)->where('status', 'active')->get() as $em) {
                $agency = ExternalAgency::withoutGlobalScopes()->find($em->external_agency_id);
                if ($agency && $agency->status === 'approved') {
                    $ctx[] = ['portal' => 'partner', 'tenantId' => (int) $em->tenant_id, 'organizationId' => null, 'entityId' => (int) $em->external_agency_id];
                }
            }

            return $ctx;
        });
    }

    /**
     * البوّابات المتاحة في مستأجر (وجود مستخدم مؤهَّل واحد على الأقل لكلٍّ) — بنفس
     * شروط الحرّاس. تُستعمل في تفاصيل المستأجر (P2) وكمنطلق لمعاينة P3.
     * @return array{agency:bool,client:bool,creator:bool,partner:bool}
     */
    public function tenantPortals(int $tenantId): array
    {
        return TenantContext::withBypass(function () use ($tenantId) {
            $approvedAgencyIds = ExternalAgency::withoutGlobalScopes()->where('status', 'approved')->pluck('id');

            return [
                'agency' => OrganizationMembership::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereNotIn('role', self::AGENCY_PORTAL_ROLES)->exists(),
                'client' => ClientMember::where('tenant_id', $tenantId)->where('status', 'active')->exists(),
                'creator' => $this->creatorPortalEnabled($tenantId)
                    && Creator::where('tenant_id', $tenantId)->whereNotNull('user_id')->exists(),
                'partner' => ExternalAgencyMember::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereIn('external_agency_id', $approvedAgencyIds)->exists(),
            ];
        });
    }

    /**
     * أول مستخدم مؤهَّل فعلًا لبوّابة داخل مستأجر (لمعاينة P3) أو null — بلا اختلاق
     * هوية. يُرجع {userId, tenantId, organizationId|null, entityId}.
     * @return array{userId:int,portal:string,tenantId:int,organizationId:?int,entityId:int}|null
     */
    public function eligibleUserForPortal(int $tenantId, string $portal): ?array
    {
        return TenantContext::withBypass(function () use ($tenantId, $portal) {
            return match ($portal) {
                'agency' => ($m = OrganizationMembership::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereNotIn('role', self::AGENCY_PORTAL_ROLES)->orderByRaw("case when role in ('agency_admin','super_admin') then 0 else 1 end")->first())
                    ? ['userId' => (int) $m->user_id, 'portal' => 'agency', 'tenantId' => $tenantId, 'organizationId' => (int) $m->organization_id, 'entityId' => (int) $m->organization_id] : null,
                'client' => ($cm = ClientMember::where('tenant_id', $tenantId)->where('status', 'active')->first())
                    ? ['userId' => (int) $cm->user_id, 'portal' => 'client', 'tenantId' => $tenantId, 'organizationId' => null, 'entityId' => (int) $cm->client_id] : null,
                'creator' => (! $this->creatorPortalEnabled($tenantId)) ? null
                    : (($cr = Creator::where('tenant_id', $tenantId)->whereNotNull('user_id')->first())
                        ? ['userId' => (int) $cr->user_id, 'portal' => 'creator', 'tenantId' => $tenantId, 'organizationId' => null, 'entityId' => (int) $cr->id] : null),
                'partner' => ($em = ExternalAgencyMember::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereIn('external_agency_id', ExternalAgency::withoutGlobalScopes()->where('status', 'approved')->pluck('id'))->first())
                    ? ['userId' => (int) $em->user_id, 'portal' => 'partner', 'tenantId' => $tenantId, 'organizationId' => null, 'entityId' => (int) $em->external_agency_id] : null,
                default => null,
            };
        });
    }

    /** هل هذا المستخدم مؤهَّل فعلًا لهذه البوّابة في هذا المستأجر؟ (تحقّق معاينة P3). */
    public function isUserEligible(int $userId, int $tenantId, string $portal): bool
    {
        $user = User::withoutGlobalScopes()->find($userId);
        if (! $user) {
            return false;
        }
        foreach ($this->contextsForUser($user) as $c) {
            if ($c['portal'] === $portal && $c['tenantId'] === $tenantId) {
                return true;
            }
        }
        return false;
    }

    private function creatorPortalEnabled(int $tenantId): bool
    {
        $org = $this->entitlements->orgForTenant($tenantId);
        return $org !== null && $this->entitlements->portalEnabled($org);
    }
}
