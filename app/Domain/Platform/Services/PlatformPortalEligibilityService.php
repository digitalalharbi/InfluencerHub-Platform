<?php

namespace App\Domain\Platform\Services;

use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorEntitlementService;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember};
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;

/**
 * مصدر واحد لأهلية البوّابات — يعكس شروط الحرّاس الفعلية. قواعد صارمة (fail-closed):
 *  - المستأجر يُشتقّ من **الكيان المرجعيّ** (Organization/Client/ExternalAgency/Creator)،
 *    لا من tenant_id المكرّر في صفّ العضوية؛ وأي تعارض ⇒ لا سياق (§3 hardening).
 *  - أهلية بوّابة المبدع من القاعدة القانونية الوحيدة CreatorEntitlementService::portalEligible.
 *  - المستخدم الهدف يجب أن يكون نشطًا (is_active) — لأن معاينة P3 ستُبدّل الفاعل.
 *  - يعيد **كل** السياقات المؤهَّلة (بلا اقتطاع).
 * يستعمله: تفاصيل مستأجر P2، ومعاينة P3 (السياق الدقيق).
 */
class PlatformPortalEligibilityService
{
    /** أدوار البوّابة التي لا تفتح الوكالة (مطابق EnsureAgencyMember::PORTAL_ROLES). */
    private const AGENCY_PORTAL_ROLES = ['influencer', 'ugc_creator', 'influencer_and_ugc'];

    public function __construct(private CreatorEntitlementService $entitlements)
    {
    }

    /**
     * كل سياقات البوّابة المؤهَّلة لمستخدم نشِط — قد تكون متعدّدة وعبر عدّة مستأجرين،
     * وتُعاد **كاملةً** بلا اقتطاع. كل عنصر {portal, tenantId, organizationId|null, entityId}.
     * @return list<array{portal:string,tenantId:int,organizationId:?int,entityId:int}>
     */
    public function contextsForUser(User $user): array
    {
        if (! $user->is_active) {
            return [];
        }

        return TenantContext::withBypass(function () use ($user) {
            $ctx = [];

            foreach (OrganizationMembership::where('user_id', $user->id)->where('status', 'active')->get() as $m) {
                if (in_array($m->role, self::AGENCY_PORTAL_ROLES, true)) {
                    continue;
                }
                $org = Organization::withoutGlobalScopes()->find($m->organization_id);
                if (! $org || (int) $org->tenant_id !== (int) $m->tenant_id) {
                    continue;   // مرجع مفقود أو تعارض مستأجر ⇒ fail-closed
                }
                $ctx[] = ['portal' => 'agency', 'tenantId' => (int) $org->tenant_id, 'organizationId' => (int) $org->id, 'entityId' => (int) $org->id];
            }

            foreach (ClientMember::where('user_id', $user->id)->where('status', 'active')->get() as $cm) {
                $client = Client::withoutGlobalScopes()->find($cm->client_id);
                if (! $client || (int) $client->tenant_id !== (int) $cm->tenant_id) {
                    continue;
                }
                $ctx[] = ['portal' => 'client', 'tenantId' => (int) $client->tenant_id, 'organizationId' => null, 'entityId' => (int) $client->id];
            }

            foreach (Creator::where('user_id', $user->id)->get() as $cr) {
                if ($this->entitlements->portalEligible($cr)) {   // القاعدة القانونية الوحيدة
                    $ctx[] = ['portal' => 'creator', 'tenantId' => (int) $cr->tenant_id, 'organizationId' => null, 'entityId' => (int) $cr->id];
                }
            }

            foreach (ExternalAgencyMember::where('user_id', $user->id)->where('status', 'active')->get() as $em) {
                $agency = ExternalAgency::withoutGlobalScopes()->find($em->external_agency_id);
                if (! $agency || $agency->status !== 'approved' || (int) $agency->tenant_id !== (int) $em->tenant_id) {
                    continue;
                }
                $ctx[] = ['portal' => 'partner', 'tenantId' => (int) $agency->tenant_id, 'organizationId' => null, 'entityId' => (int) $agency->id];
            }

            return $ctx;
        });
    }

    /**
     * البوّابات المتاحة في مستأجر (وجود مستخدم **نشِط** مؤهَّل واحد على الأقل لكلٍّ)،
     * بمراجع أصلية (لا tenant_id مكرّر). @return array{agency:bool,client:bool,creator:bool,partner:bool}
     */
    public function tenantPortals(int $tenantId): array
    {
        return TenantContext::withBypass(function () use ($tenantId) {
            $activeUserIds = User::where('is_active', true)->pluck('id');
            $orgIds = Organization::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');
            $clientIds = Client::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');
            $approvedAgencyIds = ExternalAgency::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'approved')->pluck('id');

            $creatorAvailable = false;
            $org = $this->entitlements->orgForTenant($tenantId);
            if ($org && $this->entitlements->portalEnabled($org)) {
                $creatorAvailable = Creator::where('tenant_id', $tenantId)->whereNotNull('user_id')
                    ->whereIn('user_id', $activeUserIds)->exists();
            }

            return [
                'agency' => OrganizationMembership::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereNotIn('role', self::AGENCY_PORTAL_ROLES)->whereIn('organization_id', $orgIds)
                    ->whereIn('user_id', $activeUserIds)->exists(),
                'client' => ClientMember::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereIn('client_id', $clientIds)->whereIn('user_id', $activeUserIds)->exists(),
                'creator' => $creatorAvailable,
                'partner' => ExternalAgencyMember::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereIn('external_agency_id', $approvedAgencyIds)->whereIn('user_id', $activeUserIds)->exists(),
            ];
        });
    }

    /**
     * اقتراح افتراضي: أول مستخدم **نشِط** مؤهَّل لبوّابة داخل مستأجر (أو null) — بلا
     * اختلاق هوية. الاختيار الدقيق للمستخدم يقرّره المالك في P3.
     * @return array{userId:int,portal:string,tenantId:int,organizationId:?int,entityId:int}|null
     */
    public function eligibleUserForPortal(int $tenantId, string $portal): ?array
    {
        return TenantContext::withBypass(function () use ($tenantId, $portal) {
            $active = User::where('is_active', true)->pluck('id');

            return match ($portal) {
                'agency' => ($m = OrganizationMembership::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereNotIn('role', self::AGENCY_PORTAL_ROLES)->whereIn('user_id', $active)
                    ->whereIn('organization_id', Organization::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id'))
                    ->orderByRaw("case when role in ('agency_admin','super_admin') then 0 else 1 end")->first())
                    ? ['userId' => (int) $m->user_id, 'portal' => 'agency', 'tenantId' => $tenantId, 'organizationId' => (int) $m->organization_id, 'entityId' => (int) $m->organization_id] : null,
                'client' => ($cm = ClientMember::where('tenant_id', $tenantId)->where('status', 'active')->whereIn('user_id', $active)
                    ->whereIn('client_id', Client::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id'))->first())
                    ? ['userId' => (int) $cm->user_id, 'portal' => 'client', 'tenantId' => $tenantId, 'organizationId' => null, 'entityId' => (int) $cm->client_id] : null,
                'creator' => ($cr = Creator::where('tenant_id', $tenantId)->whereNotNull('user_id')->whereIn('user_id', $active)->get()
                    ->first(fn ($c) => $this->entitlements->portalEligible($c)))
                    ? ['userId' => (int) $cr->user_id, 'portal' => 'creator', 'tenantId' => $tenantId, 'organizationId' => null, 'entityId' => (int) $cr->id] : null,
                'partner' => ($em = ExternalAgencyMember::where('tenant_id', $tenantId)->where('status', 'active')->whereIn('user_id', $active)
                    ->whereIn('external_agency_id', ExternalAgency::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'approved')->pluck('id'))->first())
                    ? ['userId' => (int) $em->user_id, 'portal' => 'partner', 'tenantId' => $tenantId, 'organizationId' => null, 'entityId' => (int) $em->external_agency_id] : null,
                default => null,
            };
        });
    }

    /**
     * قائمة المستخدمين المؤهَّلين فعلًا لبوّابة داخل مستأجر (لاختيار المالك الدقيق §7).
     * كلٌّ نشِط، والمستأجر مشتقّ من الكيان المرجعيّ. محدودة العدد.
     * @return list<array{userId:int,userName:string,entityId:int,entityLabel:string,organizationId:?int}>
     */
    public function eligibleContextsForTenantPortal(int $tenantId, string $portal): array
    {
        return TenantContext::withBypass(function () use ($tenantId, $portal) {
            $active = User::where('is_active', true)->pluck('id');
            $u = fn (int $id) => User::withoutGlobalScopes()->find($id);
            $out = [];

            if ($portal === 'agency') {
                $orgIds = Organization::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');
                foreach (OrganizationMembership::where('tenant_id', $tenantId)->where('status', 'active')
                    ->whereNotIn('role', self::AGENCY_PORTAL_ROLES)->whereIn('organization_id', $orgIds)
                    ->whereIn('user_id', $active)->limit(25)->get() as $m) {
                    $org = Organization::withoutGlobalScopes()->find($m->organization_id);
                    $out[] = ['userId' => (int) $m->user_id, 'userName' => $u((int) $m->user_id)?->name ?? '—', 'entityId' => (int) $m->organization_id, 'entityLabel' => ($org?->name ?? '—') . ' · ' . $m->role, 'organizationId' => (int) $m->organization_id];
                }
            } elseif ($portal === 'client') {
                $clientIds = Client::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');
                foreach (ClientMember::where('tenant_id', $tenantId)->where('status', 'active')->whereIn('client_id', $clientIds)->whereIn('user_id', $active)->limit(25)->get() as $cm) {
                    $client = Client::withoutGlobalScopes()->find($cm->client_id);
                    $out[] = ['userId' => (int) $cm->user_id, 'userName' => $u((int) $cm->user_id)?->name ?? '—', 'entityId' => (int) $cm->client_id, 'entityLabel' => $client?->display_name ?? '—', 'organizationId' => null];
                }
            } elseif ($portal === 'creator') {
                foreach (Creator::where('tenant_id', $tenantId)->whereNotNull('user_id')->whereIn('user_id', $active)->limit(25)->get() as $cr) {
                    if ($this->entitlements->portalEligible($cr)) {
                        $out[] = ['userId' => (int) $cr->user_id, 'userName' => $u((int) $cr->user_id)?->name ?? '—', 'entityId' => (int) $cr->id, 'entityLabel' => $cr->display_name ?? '—', 'organizationId' => null];
                    }
                }
            } elseif ($portal === 'partner') {
                $agencyIds = ExternalAgency::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('status', 'approved')->pluck('id');
                foreach (ExternalAgencyMember::where('tenant_id', $tenantId)->where('status', 'active')->whereIn('external_agency_id', $agencyIds)->whereIn('user_id', $active)->limit(25)->get() as $em) {
                    $agency = ExternalAgency::withoutGlobalScopes()->find($em->external_agency_id);
                    $out[] = ['userId' => (int) $em->user_id, 'userName' => $u((int) $em->user_id)?->name ?? '—', 'entityId' => (int) $em->external_agency_id, 'entityLabel' => $agency?->name ?? '—', 'organizationId' => null];
                }
            }

            return $out;
        });
    }

    /**
     * تحقّق دقيق من صحّة الرباعية الكاملة (لتفويض معاينة P3) — لا يكفي «المستخدم ينتمي
     * لمكان ما في المستأجر». يشترط: مستخدم موجود ونشِط + الرابط الدقيق للكيان
     * (entityId) + مطابقة مستأجر الكيان المرجعيّ + (للوكالة) مطابقة المؤسسة. fail-closed.
     */
    public function isContextEligible(int $userId, int $tenantId, string $portal, int $entityId, ?int $organizationId = null): bool
    {
        $user = User::withoutGlobalScopes()->find($userId);
        if (! $user || ! $user->is_active) {
            return false;
        }

        return TenantContext::withBypass(function () use ($user, $tenantId, $portal, $entityId, $organizationId) {
            switch ($portal) {
                case 'agency':
                    if ($organizationId !== null && $organizationId !== $entityId) {
                        return false;
                    }
                    $org = Organization::withoutGlobalScopes()->find($entityId);
                    if (! $org || (int) $org->tenant_id !== $tenantId) {
                        return false;
                    }
                    return OrganizationMembership::where('user_id', $user->id)->where('organization_id', $entityId)
                        ->where('status', 'active')->whereNotIn('role', self::AGENCY_PORTAL_ROLES)->exists();
                case 'client':
                    // المؤسسة لا تنطبق على العميل: أي قيمة غير null رباعيةٌ غير متّسقة ⇒ حظر.
                    if ($organizationId !== null) {
                        return false;
                    }
                    $client = Client::withoutGlobalScopes()->find($entityId);
                    if (! $client || (int) $client->tenant_id !== $tenantId) {
                        return false;
                    }
                    return ClientMember::where('user_id', $user->id)->where('client_id', $entityId)->where('status', 'active')->exists();
                case 'creator':
                    if ($organizationId !== null) {
                        return false;
                    }
                    $creator = Creator::withoutGlobalScopes()->find($entityId);
                    if (! $creator || (int) $creator->tenant_id !== $tenantId || (int) $creator->user_id !== $user->id) {
                        return false;
                    }
                    return $this->entitlements->portalEligible($creator);
                case 'partner':
                    if ($organizationId !== null) {
                        return false;
                    }
                    $agency = ExternalAgency::withoutGlobalScopes()->find($entityId);
                    if (! $agency || (int) $agency->tenant_id !== $tenantId || $agency->status !== 'approved') {
                        return false;
                    }
                    return ExternalAgencyMember::where('user_id', $user->id)->where('external_agency_id', $entityId)->where('status', 'active')->exists();
                default:
                    return false;
            }
        });
    }
}
