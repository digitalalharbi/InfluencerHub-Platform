<?php

namespace App\Support\Navigation;

use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\ClientProfileChangeRequest;
use App\Domain\CRM\Models\ClientDocument;
use Throwable;

/**
 * عدّادات التنقّل — أرقام حقيقية من PostgreSQL (مُنطّقة تلقائيًا بالمستأجر عبر TenantScope).
 * تُحسب مرّة واحدة لكل طلب (ذاكرة ثابتة). كل عدّاد محميّ بـ try/catch حتى لا يُعطّل القائمة أبدًا.
 * تُعرض فقط "الأعمال المعلّقة التي تحتاج إجراءً" — لا أرقام تجميلية.
 */
class NavigationBadges
{
    private static ?array $cache = null;

    /** @return array<string,int> مفتاح badge → عدد (يُحذف الصفر). */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $counts = [
            'service_requests'     => self::safe(fn () => ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)->count()),
            'creator_applications' => self::safe(fn () => CreatorApplication::whereIn('status', ['submitted', 'under_review'])->count()),
            'content'              => self::safe(fn () => ContentItem::where('status', 'agency_review')->count()),
            'brand_reviews'        => self::safe(fn () => Brand::whereIn('status', ['submitted', 'under_review'])->count()),
            'client_reviews'       => self::safe(fn () => ClientProfileChangeRequest::whereIn('status', ['submitted', 'under_review'])->count()
                                                        + ClientDocument::where('status', 'pending')->count()),
        ];

        // احذف الأصفار — الشارة تظهر فقط عند وجود عمل معلّق فعليًا.
        return self::$cache = array_filter($counts, fn ($n) => $n > 0);
    }

    public static function for(string $key): int
    {
        return self::all()[$key] ?? 0;
    }

    /** تصفير الذاكرة (للاختبارات أو بعد تغيّر سياق المستأجر). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function safe(callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (Throwable) {
            return 0;
        }
    }
}
