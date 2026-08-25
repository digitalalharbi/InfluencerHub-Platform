<?php

namespace App\Domain\Platform\Support;

use App\Domain\Identity\Models\User;

/**
 * قدرات «مالك المنصّة» (Platform Owner) — طبقة صلاحيات صريحة فوق النظام، لا دور
 * داخل مستأجر ولا تجاوز عشوائي. مالك المنصّة كيان خارج نطاق أي مستأجر (§1).
 *
 * التصميم: لا نُكرّر مفهومًا؛ نبني على العَلَم القائم `users.is_system_admin`
 * (المرساة التاريخية للطبقة العابرة للمستأجرين) ونُصرِّح القدرات باسم واضح
 * ومختبَر بدل الاعتماد وحده على `Gate::before(is_system_admin)`. هذا يفتح لاحقًا
 * بابَ أدوار منصّة أدقّ دون تغيير نقاط الفحص.
 *
 * كل قدرة تمرّ عبر: مستخدم مُصادَق ← middleware/سياسة ← تدقيق (لا باب خلفي §11).
 */
final class PlatformCapabilities
{
    public const OWNER = 'platform.owner';
    public const TENANTS_VIEW = 'platform.tenants.view';
    public const TENANTS_MANAGE = 'platform.tenants.manage';
    public const IMPERSONATE = 'platform.impersonate';
    public const PORTAL_PREVIEW = 'platform.portal.preview';
    public const GLOBAL_SEARCH = 'platform.global_search';
    public const SYSTEM_MANAGE = 'platform.system.manage';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::OWNER, self::TENANTS_VIEW, self::TENANTS_MANAGE, self::IMPERSONATE,
            self::PORTAL_PREVIEW, self::GLOBAL_SEARCH, self::SYSTEM_MANAGE,
        ];
    }

    /** هل هذا المستخدم مالك منصّة؟ (المرساة الحالية: is_system_admin). */
    public static function isOwner(?User $user): bool
    {
        return $user !== null && (bool) $user->is_system_admin;
    }

    /**
     * هل يملك المستخدم قدرة منصّة بعينها؟ في هذه المرحلة يملك المالكُ كلَّ القدرات؛
     * نقطة الفحص المصرَّحة تبقى واحدة كي تتطوّر لأدوار منصّة أدقّ لاحقًا بلا تغيير
     * في المتحكّمات/الـmiddleware.
     */
    public static function can(?User $user, string $capability): bool
    {
        if (! in_array($capability, self::all(), true)) {
            return false;
        }

        return self::isOwner($user);
    }
}
