<?php

namespace App\Support\Platforms;

/**
 * قارئ سجل المنصّات — مصدر واحد لخيارات المنصّات في النماذج والتحقّق والـWorkflow.
 * لا تُعرض أو تُقبل إلا المنصّات المتاحة فعلًا (status متاح) والتي تدعم القدرة المطلوبة.
 */
class PlatformRegistry
{
    /** كل المنصّات المسجّلة مرتّبة بالأولوية (يشمل غير المتاحة — للإدارة فقط). */
    public static function all(): array
    {
        $reg = config('platforms.registry', []);
        uasort($reg, fn ($a, $b) => ($a['order'] ?? 999) <=> ($b['order'] ?? 999));

        return $reg;
    }

    /** هل المنصّة بحالة متاحة؟ */
    public static function isAvailable(string $key): bool
    {
        $p = config("platforms.registry.$key");

        return $p && in_array($p['status'] ?? '', config('platforms.available_statuses', []), true);
    }

    /** هل تدعم المنصّة قدرة معيّنة؟ */
    public static function supports(string $key, string $capability): bool
    {
        $p = config("platforms.registry.$key");

        return $p && in_array($capability, $p['capabilities'] ?? [], true);
    }

    /**
     * المنصّات المتاحة للاختيار (مرتّبة بالأولوية)، اختياريًا مقيّدة بقدرة معيّنة.
     * التسمية تتبع لغة الطلب (label_en في الإنجليزية، وإلا label_ar).
     *
     * @return array<string,string> key => label
     */
    public static function options(?string $capability = null): array
    {
        $out = [];
        foreach (self::all() as $key => $p) {
            if (! self::isAvailable($key)) {
                continue;
            }
            if ($capability !== null && ! in_array($capability, $p['capabilities'] ?? [], true)) {
                continue;
            }
            $out[$key] = self::localizedLabel($p);
        }

        return $out;
    }

    /** أسماء المنصّات علامات تجارية؛ label_en صيغتها اللاتينية القياسية. الافتراضي عربي. */
    private static function localizedLabel(array $p): string
    {
        return app()->getLocale() === 'en' && ! empty($p['label_en']) ? $p['label_en'] : ($p['label_ar'] ?? '');
    }

    /** مفاتيح المنصّات المتاحة (لقاعدة التحقّق in:...). */
    public static function availableKeys(?string $capability = null): array
    {
        return array_keys(self::options($capability));
    }

    /** قاعدة تحقّق Laravel: required|in:<available keys> (تمنع تجاوز Backend لأي خيار مخفي). */
    public static function rule(?string $capability = null, bool $required = true): string
    {
        $keys = implode(',', self::availableKeys($capability));

        return ($required ? 'required' : 'nullable').'|in:'.$keys;
    }

    /** تسمية منصّة بلغة الطلب (حتى لو غير متاحة، لعرض البيانات القديمة). */
    public static function label(string $key): string
    {
        $p = config("platforms.registry.$key");

        return $p ? self::localizedLabel($p) : $key;
    }
}
