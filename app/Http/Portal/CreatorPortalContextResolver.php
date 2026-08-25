<?php

namespace App\Http\Portal;

use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorEntitlementService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * سياق بوّابة المبدع — نفس ما يبنيه EnsureCreator (الخاصية/المشاركة `creator`)،
 * بالقاعدة القانونية الوحيدة fail-closed `portalEligible` (ملف + مؤسسة + بوّابة مفعّلة).
 * للمبدع ملفّ واحد لكل حساب، فلا مبدّل ولا اختيار متعدّد.
 */
final class CreatorPortalContextResolver implements PortalContextResolver
{
    public function __construct(private CreatorEntitlementService $entitlements)
    {
    }

    public function resolve(User $user, ?int $entityId, bool $exact): ?PortalContext
    {
        $creator = TenantContext::withBypass(fn () => Creator::where('user_id', $user->id)->first());
        if ($creator === null) {
            return null;
        }
        // المعاينة تطلب مطابقة الكيان تمامًا (لا اختلاق مبدع آخر).
        if ($exact && $entityId !== null && (int) $creator->id !== $entityId) {
            return null;
        }
        if (! $this->entitlements->portalEligible($creator)) {
            return null;
        }

        return new PortalContext(
            tenantId: (int) $creator->tenant_id,
            organizationId: null,
            attributes: ['creator' => $creator],
            share: ['creator' => $creator],
        );
    }
}
