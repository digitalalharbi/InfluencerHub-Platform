<?php

namespace App\Support\Navigation;

use App\Domain\AdminPool\Models\PoolRecommendation;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\ClientProfileChangeRequest;
use App\Domain\CRM\Models\ClientDocument;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * عدّادات التنقّل — أرقام حقيقية من PostgreSQL (مُنطّقة تلقائيًا بالمستأجر عبر TenantScope).
 * تُحسب مرّة واحدة لكل طلب (ذاكرة ثابتة)، والعدّادات على مستوى المؤسسة تُخزَّن مؤقتًا
 * ~30 ثانية بمفتاح يشمل المستأجر+المؤسسة (PERF-01: لا سبع استعلامات على كل تنقّل، بلا تسرّب
 * عبر المستأجرين). كل عدّاد محميّ بـ try/catch. تُعرض فقط «الأعمال المعلّقة» — لا أرقام تجميلية.
 */
class NavigationBadges
{
    private static ?array $cache = null;

    private const TTL = 30;

    /** @return array<string,int> مفتاح badge → عدد (يُحذف الصفر). */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $tid = self::safe(fn () => (int) TenantContext::tenantId());
        $oid = self::safe(fn () => (int) TenantContext::organizationId());

        // عدّادات على مستوى المؤسسة (مُنطّقة بالمستأجر) — تُخزَّن مؤقتًا بمفتاح آمن.
        $org = ($tid && $oid)
            ? Cache::remember("navbadges:t{$tid}:o{$oid}", self::TTL, fn () => self::orgCounts())
            : self::orgCounts();

        // ترشيحات العميل النشِط — خاصّة بالطلب (تعتمد على activeClient) فلا تُخزَّن عالميًّا.
        $counts = $org + [
            'client_recommendations' => self::safe(fn () => ($cl = request()?->attributes->get('activeClient'))
                ? PoolRecommendation::where('client_id', $cl->id)->where('status', 'recommended')->count() : 0),
        ];

        // احذف الأصفار — الشارة تظهر فقط عند وجود عمل معلّق فعليًا.
        return self::$cache = array_filter($counts, fn ($n) => $n > 0);
    }

    /** العدّادات على مستوى المؤسسة (الجزء القابل للتخزين المؤقت). @return array<string,int> */
    private static function orgCounts(): array
    {
        return [
            'service_requests'     => self::safe(fn () => ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)->count()),
            'creator_applications' => self::safe(fn () => CreatorApplication::whereIn('status', ['submitted', 'under_review'])->count()),
            'content'              => self::safe(fn () => ContentItem::where('status', 'agency_review')->count()),
            'brand_reviews'        => self::safe(fn () => Brand::whereIn('status', ['submitted', 'under_review'])->count()),
            'client_reviews'       => self::safe(fn () => ClientProfileChangeRequest::whereIn('status', ['submitted', 'under_review'])->count()
                                                        + ClientDocument::where('status', 'pending')->count()),
        ];
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
