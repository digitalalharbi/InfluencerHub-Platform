<?php

namespace App\Domain\Onboarding\Support;

/**
 * أنواع الحسابات — **مصدر المحتوى الوحيد**.
 *
 * كانت هذه القائمة مكتوبة مرّتين: في `SiteController::ACCOUNT_TYPES` للصفحة
 * الرئيسية، وفي `GatewayController::ROLES` لصفحة البدء. فاختلفت الصياغتان
 * («تُطلق حملاتك وتدير مبدعيك» مقابل «أطلق حملاتك مع وكالة»)، واختلف المسار
 * أيضًا: إحداهما تشير إلى `/register/client` والأخرى إلى `/register/brand`.
 * ونسختان من نصٍّ واحد تفترقان دائمًا؛ السؤال متى لا هل.
 *
 * فصار هنا التعريف، ويقرأ منه الجميع.
 *
 * ## المفاتيح ثابتة
 *
 * `brand` · `agency` · `creator` — تظهر في `/start?type=…` وتُحفظ مع سجلّ
 * التسجيل. تغييرها يكسر روابط منشورة، فتُعامَل كعقد لا كتسمية.
 */
final class AccountTypes
{
    public const BRAND = 'brand';

    public const AGENCY = 'agency';

    public const CREATOR = 'creator';

    public const KEYS = [self::BRAND, self::AGENCY, self::CREATOR];

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'key' => self::BRAND,
                'label' => 'علامة تجارية',
                'title' => 'علامة تجارية أو عميل',
                'icon' => 'building',
                'hint' => 'أطلق حملاتك وتابع نتائجها',
                'summary' => 'أطلق حملاتك وتابع نتائجها في مساحة تخصّك.',
                'benefits' => [
                    'اطلب حملة وتابع مسارها خطوة بخطوة',
                    'راجع المرشَّحين واعتمد من يناسبك',
                    'اعتمد المحتوى قبل النشر واطلب تعديلاتك',
                ],
                // مسار التسجيل لهذا النوع — يُقصَد إليه بعد اختيار النوع
                'register' => '/register/brand',
                'login' => '/login',
                'portal' => '/brand',
            ],
            [
                'key' => self::AGENCY,
                'label' => 'وكالة',
                'title' => 'وكالة',
                'icon' => 'briefcase',
                'hint' => 'أدِر عملاءك وحملاتهم بمكان واحد',
                'summary' => 'مساحة كاملة لإدارة عملائك وحملاتهم وصنّاع المحتوى.',
                'benefits' => [
                    'أدِر عملاءك وعلاماتهم وحملاتهم بمكان واحد',
                    'اختر صنّاع المحتوى ورشّحهم لعملائك',
                    'تابع الاتّفاقات والفواتير والمستحقّات',
                ],
                'register' => '/register/agency',
                'login' => '/login',
                'portal' => '/app',
            ],
            [
                'key' => self::CREATOR,
                'label' => 'مؤثّر أو صانع محتوى',
                'title' => 'مؤثّر أو صانع محتوى',
                'icon' => 'spark',
                'hint' => 'استقبل عروض التعاون واستلم مستحقّاتك',
                'summary' => 'حساب واحد يجمع كل ما تقدّمه — نشر وإنتاج وتصوير.',
                'benefits' => [
                    'حساب واحد لكل ما تقدّمه — لا حسابات متفرّقة',
                    'استقبل العروض وتفاوض عليها',
                    'سلّم عملك وتابع أرباحك ومستحقّاتك',
                ],
                'register' => '/join/creator',
                'login' => '/creator/login',
                'portal' => '/creator',
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public static function find(?string $key): ?array
    {
        foreach (self::all() as $type) {
            if ($type['key'] === $key) {
                return $type;
            }
        }

        return null;
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && in_array($key, self::KEYS, true);
    }
}
