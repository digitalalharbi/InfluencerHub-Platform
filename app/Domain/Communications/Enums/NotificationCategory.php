<?php

namespace App\Domain\Communications\Enums;

/**
 * المصدر القانونيّ الوحيد لفئات الإشعارات — يوحّد قائمتين متضاربتين كانتا في متحكّم
 * الإشعارات ونموذج التفضيلات. الفئة تربط الحدث بتفضيل المستخدم (داخل التطبيق/البريد/…).
 *
 * التوافق الخلفيّ: أي قيمة قديمة/غير معروفة تُطبَّع إلى General ({@see normalize}) فلا يكسر
 * أي بيان تفضيل سابق (القيم المهجورة تُتجاهَل بأمان، بلا حذف).
 */
enum NotificationCategory: string
{
    case General = 'general';
    case Campaigns = 'campaigns';
    case Finance = 'finance';
    case Requests = 'requests';
    case Creators = 'creators';
    case Reviews = 'reviews';
    case System = 'system';

    /** تسمية بشريّة عربيّة بسيطة (لا مصطلح تقنيّ). */
    public function label(): string
    {
        return match ($this) {
            self::General => 'عام',
            self::Campaigns => 'الحملات',
            self::Finance => 'المالية',
            self::Requests => 'الطلبات',
            self::Creators => 'المبدعون',
            self::Reviews => 'المراجعات',
            self::System => 'تنبيهات النظام',
        };
    }

    /**
     * خريطة العرض في الإعدادات — value => label. مصدر واحد لكل شاشات التفضيلات.
     *
     * @return array<string,string>
     */
    public static function map(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->label();
        }

        return $out;
    }

    /** @return array<int,string> قيم الفئات (للتحقّق in:). */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** يطبّع قيمة (قد تكون قديمة/فارغة) إلى فئة قانونيّة — توافق خلفيّ آمن. */
    public static function normalize(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::General;
    }
}
