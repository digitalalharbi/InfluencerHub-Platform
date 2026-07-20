<?php
namespace App\Domain\Tenancy\Support;

/**
 * حامل سياق المستأجر الحالي (يُضبط بعد المصادقة). عزل fail-closed.
 *
 * ## لماذا `withTenant` هي الواجهة المفضَّلة
 *
 * `set()` يكتب **الحقول الثلاثة**: فاستدعاء `set($tenantId)` وحده يمسح
 * المؤسسة وورشة العمل. و`reset()` يمسح كل شيء بما فيه `bypass`. فمن يضبط
 * السياق مؤقّتًا ثم «يُعيده» بـ`reset()` لا يُعيده — بل يُفرغه.
 *
 * الأثر لم يكن استثناءً بل نمطًا تكرّر ثماني مرّات:
 * - استعلام بعد `reset()` يعود فارغًا **بصمت** فيُقرأ «لا سجلّ» أو «لا مستقبِل»،
 *   فتسقط إشعارات ويُتخطّى حارس تكرار كأن لا تعارض.
 * - `set($tenantId)` قبل `authorize()` يمسح المؤسسة، فيعود `roleIn($orgId)`
 *   فارغًا ويُردّ كل أحد 403.
 *
 * القاعدة: **لا تضبط السياق يدويًّا ثم تُعيده بنفسك.** استعمل `withTenant`
 * أو `withBypass` — تحفظ ما كان وتستعيده حتّى عند الاستثناء.
 */
class TenantContext
{
    protected static ?int $tenantId = null;
    protected static ?int $organizationId = null;
    protected static ?int $workspaceId = null;
    protected static bool $bypass = false; // system_admin / وظائف خلفية صريحة

    public static function set(?int $tenantId, ?int $organizationId = null, ?int $workspaceId = null): void
    {
        static::$tenantId = $tenantId;
        static::$organizationId = $organizationId;
        static::$workspaceId = $workspaceId;
    }

    public static function tenantId(): ?int { return static::$tenantId; }
    public static function organizationId(): ?int { return static::$organizationId; }
    public static function workspaceId(): ?int { return static::$workspaceId; }
    public static function check(): bool { return static::$tenantId !== null; }

    public static function bypass(bool $on = true): void { static::$bypass = $on; }
    public static function bypassing(): bool { return static::$bypass; }

    public static function reset(): void
    {
        static::$tenantId = static::$organizationId = static::$workspaceId = null;
        static::$bypass = false;
    }

    /**
     * لقطة من السياق الحالي — للحفظ قبل تغييره.
     *
     * @return array{tenant:?int,organization:?int,workspace:?int,bypass:bool}
     */
    public static function snapshot(): array
    {
        return [
            'tenant' => static::$tenantId,
            'organization' => static::$organizationId,
            'workspace' => static::$workspaceId,
            'bypass' => static::$bypass,
        ];
    }

    /** يستعيد لقطة سابقة كما هي — بما فيها `bypass`. */
    public static function restore(array $snapshot): void
    {
        static::$tenantId = $snapshot['tenant'] ?? null;
        static::$organizationId = $snapshot['organization'] ?? null;
        static::$workspaceId = $snapshot['workspace'] ?? null;
        static::$bypass = (bool) ($snapshot['bypass'] ?? false);
    }

    /**
     * ينفّذ عملًا داخل سياق مستأجر، ثم يستعيد السياق السابق — حتّى عند الاستثناء.
     *
     * يحفظ المؤسسة وورشة العمل إذا لم تُمرَّرا صراحةً وكان المستأجر هو نفسه،
     * فلا يفقد الطلب مؤسسته لمجرّد أن كودًا داخليًّا أعاد تأكيد مستأجره.
     *
     * @template T
     * @param  callable():T  $fn
     * @return T
     */
    public static function withTenant(?int $tenantId, callable $fn, ?int $organizationId = null, ?int $workspaceId = null)
    {
        $prev = static::snapshot();

        // إعادة تأكيد المستأجر نفسه لا تُسقط المؤسسة القائمة
        $sameTenant = $tenantId !== null && $tenantId === $prev['tenant'];
        static::set(
            $tenantId,
            $organizationId ?? ($sameTenant ? $prev['organization'] : null),
            $workspaceId ?? ($sameTenant ? $prev['workspace'] : null),
        );

        try {
            return $fn();
        } finally {
            static::restore($prev);
        }
    }

    /**
     * ينفّذ عملًا متجاوزًا النطاق (قراءة عبر المستأجرين)، ثم يستعيد ما كان.
     *
     * التجاوز أداة إدارية: تُفتح لعمل محدَّد وتُغلق بعده. تركه مفتوحًا يُبطل
     * العزل لبقيّة الطلب بلا أن يظهر ذلك في أي استعلام.
     *
     * @template T
     * @param  callable():T  $fn
     * @return T
     */
    public static function withBypass(callable $fn)
    {
        $prev = static::snapshot();
        static::bypass(true);

        try {
            return $fn();
        } finally {
            static::restore($prev);
        }
    }
}
