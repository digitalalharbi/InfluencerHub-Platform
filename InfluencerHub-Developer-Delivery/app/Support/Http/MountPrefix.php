<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * بادئة تركيب الطلب الحالي (`/app`, `/beta`, `/beta/client`…).
 *
 * أثناء التحويل التدريجي من Blade تُقدَّم الصفحة نفسها تحت أكثر من بادئة،
 * فأي إعادة توجيه بمسار ثابت تُخرج المستخدم من مجموعته. تُشارَك القيمة مع
 * الواجهة عبر HandleInertiaRequests وتُستعمل هنا لبناء إعادة التوجيه.
 *
 * الترتيب من الأطول للأقصر ليطابق مسار البوابة قبل الجذر.
 */
final class MountPrefix
{
    private const PREFIXES = [
        'beta/client', 'beta/creator', 'beta/partner', 'beta/admin', 'beta',
        'client', 'creator', 'partner', 'admin', 'brand', 'app',
    ];

    public static function for(Request $request): string
    {
        $path = trim($request->path(), '/');
        foreach (self::PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return '/' . $prefix;
            }
        }

        return '/app';
    }

    /** مسار مطلق داخل مجموعة الطلب الحالية. */
    public static function path(Request $request, string $path): string
    {
        return self::for($request) . $path;
    }
}
