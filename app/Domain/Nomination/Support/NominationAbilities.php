<?php

namespace App\Domain\Nomination\Support;

use App\Domain\Nomination\Access\NominationAccess;

/**
 * صلاحيات «ترشيح المؤثرين» (influencer_nomination) — طبقة RBAC داخل المؤسسة المُتاحة لها.
 *
 * منع بالافتراض (deny-by-default). ثلاث طبقات مستقلّة للحوكمة تتركّب في مصدر واحد
 * ({@see NominationAccess}):
 *  - الإتاحة (Availability) تقرّر هل الميزة مُفعّلة لهذا النطاق أصلًا (تديرها المنصّة).
 *  - هذه الصلاحيات (RBAC) تقرّر ماذا يفعل المستخدم داخل مؤسسة مُتاحة لها الميزة.
 *  - السياق (TenantContext) يقرّر أي مستأجر، ويُغلق فشلًا عند غيابه.
 *
 * المفاتيح المنقّطة (influencer_nomination.view ...) هي أسماء الصلاحيات القانونية؛
 * والتنفيذ الفعلي عبر مصفوفة الأدوار أدناه (نفس نمط CreatorDatabaseAbilities).
 */
final class NominationAbilities
{
    /** مفتاح الميزة القانوني الموحّد. */
    public const KEY = 'influencer_nomination';

    /** influencer_nomination.view — تصفّح الترشيحات وقراءتها (يشمل «مُطّلع»). */
    public const VIEW = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager', 'agency_employee', 'content_reviewer', 'viewer'];

    /** influencer_nomination.create — بدء ترشيح/إصدار جديد. */
    public const CREATE = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager'];

    /** influencer_nomination.update — تعديل ترشيح قائم. */
    public const UPDATE = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager'];

    /** influencer_nomination.manage_candidates — إضافة/إزالة مرشّحين وضبط الأساسي/الاحتياطي. */
    public const MANAGE_CANDIDATES = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager'];

    /** influencer_nomination.approve — الاعتماد الداخلي/الإرسال لقرار العميل. */
    public const APPROVE = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager'];

    /** influencer_nomination.export — تصدير قائمة الترشيح (داخلي). مقيّد ومُدقَّق. */
    public const EXPORT = ['super_admin', 'agency_admin', 'operations_manager'];

    /** influencer_nomination.share — توليد/مشاركة مقترح آمن للعميل. */
    public const SHARE = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager'];

    /**
     * influencer_nomination.client_view — عرض العميل للترشيحات المُشاركة له واتخاذ القرار.
     * تُفرض بعضوية بوّابة العميل (client_member) + إتاحة بوّابة client، لا بدور وكالة.
     */
    public const CLIENT_VIEW = ['client_member'];

    /**
     * influencer_nomination.manage_feature — إدارة إتاحة الميزة (تشغيل/إخفاء) لكل نطاق.
     * صلاحية على مستوى المنصّة (Platform Owner / مدير النظام) — ليست دورًا داخل الوكالة،
     * وتُفرض عبر حرّاس المنصّة لا عبر هذه المصفوفة.
     */
    public const MANAGE_FEATURE = [];

    public static function can(?string $role, array $set): bool
    {
        return $role !== null && in_array($role, $set, true);
    }

    /**
     * خريطة صلاحيات الوكالة لدور — للعرض (nav/decision) وللفحص الموحّد.
     *
     * @return array<string,bool>
     */
    public static function agencyMap(?string $role): array
    {
        return [
            'view' => self::can($role, self::VIEW),
            'create' => self::can($role, self::CREATE),
            'update' => self::can($role, self::UPDATE),
            'manage_candidates' => self::can($role, self::MANAGE_CANDIDATES),
            'approve' => self::can($role, self::APPROVE),
            'export' => self::can($role, self::EXPORT),
            'share' => self::can($role, self::SHARE),
        ];
    }
}
