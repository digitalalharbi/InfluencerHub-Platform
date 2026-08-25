<?php

namespace App\Domain\Nomination\Access;

use App\Domain\Identity\Models\User;
use App\Domain\Nomination\Support\NominationAbilities;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * المصدر الموحّد لقرار الوصول إلى «ترشيح المؤثرين» (influencer_nomination).
 *
 * قرارٌ واحد يركّب الأبعاد الخمسة، بدل تكرارها في كل متحكّم وكل مشاركة Inertia:
 *   feature enabled  ← الإتاحة المُدارة من المنصّة (FeatureAvailabilityResolver)
 *   AND tenant/ scope entitled ← نفس الإتاحة على نطاق المستأجر/المساحة/البوّابة
 *   AND surface(portal) enabled ← بُعد البوّابة في الإتاحة + حرّاس البوّابة على المسار
 *   AND role/user permitted    ← NominationAbilities (RBAC، منع بالافتراض)
 *   AND context valid          ← TenantContext (يُغلق فشلًا عند الغياب)
 *
 * يستهلكه: middleware الحافّة (منع 403 عند الإطفاء)، مشاركة nav (إخفاء الروابط)،
 * والمتحكّمات (فحص صلاحيات دقيقة). الإطفاء لا يمحو بيانات — إعادة التفعيل تُرجعها.
 */
final class NominationAccess
{
    public function __construct(private FeatureAvailabilityResolver $availability) {}

    /** هل الميزة مُتاحة لهذا المستأجر/البوّابة؟ (بُعد الإتاحة وحده، بلا دور). */
    public function availableForTenant(?int $tenantId, string $portal = 'agency', ?int $workspaceId = null): bool
    {
        if ($tenantId === null) {
            return false;
        }

        return $this->availability->enabled(NominationAbilities::KEY, $tenantId, $workspaceId, $portal);
    }

    /** القرار الكامل للمستخدم الحالي ضمن بوّابة (المصدر الوحيد). */
    public function decide(?User $user, string $portal = 'agency', ?int $workspaceId = null): NominationDecision
    {
        $tenantId = TenantContext::tenantId();

        // السياق + المصادقة: غيابهما يُغلق فشلًا.
        if ($user === null) {
            return new NominationDecision(false, false, $portal, null, [], 'no_auth');
        }
        if ($tenantId === null) {
            return new NominationDecision(false, false, $portal, null, [], 'no_context');
        }

        // الإتاحة (feature enabled + scope entitled + surface).
        if (! $this->availability->enabled(NominationAbilities::KEY, $tenantId, $workspaceId, $portal)) {
            return new NominationDecision(false, false, $portal, null, [], 'feature_disabled');
        }

        // الدور: بوّابة الوكالة/الإدارة عبر مصفوفة الأدوار؛ البوّابات الأخرى بالعضوية.
        $oid = TenantContext::organizationId();
        $role = $oid ? TenantContext::withBypass(fn () => $user->roleIn($oid)) : null;

        if ($portal === 'agency' || $portal === 'admin') {
            $isAdmin = (bool) $user->is_system_admin;
            $view = $isAdmin || NominationAbilities::can($role, NominationAbilities::VIEW);
            $map = NominationAbilities::agencyMap($role);
            if ($isAdmin) {
                $map = array_map(fn () => true, $map);
            }

            return new NominationDecision($view, true, $portal, $role, $map, $view ? null : 'role_denied');
        }

        // بوّابات العميل/العلامة/المبدع/الشريك: العضوية مفروضة على المسار؛ الإتاحة تكفي للعرض.
        $abilities = $portal === 'client' ? ['client_view' => true] : ['view' => true];

        return new NominationDecision(true, true, $portal, $role, $abilities, null);
    }

    /** اختصار: هل يملك المستخدم عرض الميزة في البوّابة؟ (لِـ nav والحافّة). */
    public function canView(?User $user, string $portal = 'agency'): bool
    {
        return $this->decide($user, $portal)->allowed;
    }

    /** اختصار: هل يملك المستخدم صلاحية دقيقة (view/create/update/...) في البوّابة؟ */
    public function can(?User $user, string $ability, string $portal = 'agency'): bool
    {
        return $this->decide($user, $portal)->can($ability);
    }
}
