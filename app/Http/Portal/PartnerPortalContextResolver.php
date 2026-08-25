<?php

namespace App\Http\Portal;

use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember};
use App\Domain\Tenancy\Support\TenantContext;

/**
 * سياق بوّابة الشريك — نفس ما يبنيه EnsurePartnerMember (activeAgency،
 * partnerMembership، myAgencies)، مع الحدّ fail-closed: الوكالة معتمدة (approved).
 */
final class PartnerPortalContextResolver implements PortalContextResolver
{
    public function resolve(User $user, ?int $entityId, bool $exact): ?PortalContext
    {
        [$active, $agency, $myAgencies] = TenantContext::withBypass(function () use ($user, $entityId, $exact) {
            $memberships = ExternalAgencyMember::where('user_id', $user->id)->where('status', 'active')->get();
            $active = $entityId !== null ? $memberships->firstWhere('external_agency_id', $entityId) : null;
            if ($active === null && ! $exact) {
                $active = $memberships->first();
            }
            if ($active === null) {
                return [null, null, null];
            }
            $agency = ExternalAgency::withoutGlobalScopes()->find($active->external_agency_id);
            $myAgencies = ExternalAgency::withoutGlobalScopes()->whereIn('id', $memberships->pluck('external_agency_id'))->get();

            return [$active, $agency, $myAgencies];
        });

        // fail-closed: عضوية نشطة + وكالة معتمدة فقط.
        if ($active === null || $agency === null || $agency->status !== 'approved') {
            return null;
        }

        return new PortalContext(
            tenantId: (int) $agency->tenant_id,
            organizationId: null,
            attributes: ['activeAgency' => $agency, 'partnerMembership' => $active],
            share: ['activeAgency' => $agency, 'partnerMembership' => $active, 'myAgencies' => $myAgencies],
            sessionKey: 'active_agency_id',
            sessionValue: (int) $agency->id,
        );
    }
}
