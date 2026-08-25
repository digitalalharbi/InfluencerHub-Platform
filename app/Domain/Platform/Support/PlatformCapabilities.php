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

    /** كل القدرات المعرَّفة (تشمل قدرات مراحل لاحقة لم تُبنَ بعد). @return list<string> */
    public static function all(): array
    {
        return [
            self::OWNER, self::TENANTS_VIEW, self::TENANTS_MANAGE, self::IMPERSONATE,
            self::PORTAL_PREVIEW, self::GLOBAL_SEARCH, self::SYSTEM_MANAGE,
        ];
    }

    /**
     * القدرات «الحيّة» — التي بُنيت شريحتها فعلًا. لا نمنح قدرة لميزة لم تُنفَّذ بعد
     * حتى لا يُوحى بأنها عاملة (§4). تنمو هذه القائمة مع كل شريحة (P2 بحثًا، P3 معاينة…).
     * @return list<string>
     */
    private static function live(): array
    {
        return [self::OWNER];   // P1: الهوية/الوصول فقط
    }

    /**
     * هل هذا المستخدم مالك منصّة؟ علامة مخصّصة `is_platform_owner` — لا يساوي كلَّ
     * system admin. الهرمية: Platform Owner ⊃ System Admin. (المالك عادةً system admin
     * أيضًا كي يعمل Gate::before/withBypass، لكن الفحص هنا على العلامة المخصّصة حصريًّا.)
     */
    public static function isOwner(?User $user): bool
    {
        return $user !== null && (bool) $user->is_platform_owner;
    }

    /**
     * يملك المالكُ قدرةً إن كانت شريحتها حيّة فقط. نقطة فحص واحدة مركزية تتوسّع لاحقًا.
     */
    public static function can(?User $user, string $capability): bool
    {
        return self::isOwner($user) && in_array($capability, self::live(), true);
    }
}
