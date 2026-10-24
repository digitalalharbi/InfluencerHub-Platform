<?php

namespace App\Support\I18n;

/**
 * تقسيم حزمة الترجمة (PERF-01) — بدل إرسال كامل القاموس (~99KB) مع كل صفحة:
 *   • CORE: مجموعات صغيرة مشتركة تُرسَل دائمًا (navigation/actions/statuses/entities/common).
 *   • حسب المكوّن: مجموعة/مجموعتان خاصّتان بالسطح الحالي تُحقَنان في استجابة Inertia.
 *
 * الخريطة مشتقّة من الاستخدام الفعلي لـt() في كل مجلّد Pages (مُتحقَّق منها باختبار حارس).
 * المكوّن غير المذكور يحصل على CORE فقط (لا مفاتيح خام: كل تلك المكوّنات لا تستخدم إلا CORE).
 */
class PageTranslations
{
    /** مجموعات أساسية تُرسَل مع كل صفحة. */
    public const CORE = ['common', 'navigation', 'actions', 'statuses', 'entities'];

    /** الجزء الأول من اسم مكوّن Inertia → مجموعات إضافية (بلا CORE). */
    private const MAP = [
        'Admin' => ['admin_dashboard', 'dashboard'],
        'Automation' => ['automation'],
        'Brands' => ['brands'],
        'Campaigns' => ['campaigns'],
        'ClientPortal' => ['client_dashboard', 'client_portal', 'dashboard'],
        'Clients' => ['clients'],
        'Collaborations' => ['collaborations'],
        'Content' => ['content'],
        'Contracts' => ['contracts'],
        'CreatorDatabase' => ['creator_database'],
        'CreatorPortal' => ['creator_dashboard', 'dashboard'],
        'Creators' => ['creators'],
        'Dashboard' => ['dashboard'],
        'Invoices' => ['invoices'],
        'PartnerPortal' => ['partner_dashboard', 'dashboard'],
        'Payouts' => ['payouts'],
        'Reports' => ['reports'],
        'ServiceRequests' => ['service_requests'],
        'Shortlisting' => ['shortlisting'],
    ];

    /** الترجمات الأساسية محلولة باللغة الحالية. @return array<string,mixed> */
    public static function core(): array
    {
        return self::resolve(self::CORE);
    }

    /** مجموعات الترجمة الإضافية لمكوّن (الجزء الأول قبل «/»)، بلا CORE. @return list<string> */
    public static function namespacesFor(?string $component): array
    {
        if (! $component) {
            return [];
        }

        return self::MAP[explode('/', $component)[0]] ?? [];
    }

    /** يحلّ قائمة مجموعات إلى مصفوفة ترجمات. @return array<string,mixed> */
    public static function resolve(array $namespaces): array
    {
        $out = [];
        foreach ($namespaces as $ns) {
            $out[$ns] = (array) trans($ns);
        }

        return $out;
    }

    /** الخريطة الكاملة (للاختبار الحارس). @return array<string,list<string>> */
    public static function map(): array
    {
        return self::MAP;
    }
}
