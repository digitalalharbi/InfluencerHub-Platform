<?php

namespace App\Http\Controllers\Inertia\Admin;

use App\Domain\AdminPool\Models\PoolCreator;
use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Storage;
use Inertia\{Inertia, Response};

/**
 * قاعدة مبدعي مدير النظام — للترشيح والتحويل إلى العملاء.
 *
 * محميّة بوسيط `system_admin` (المجموعة كلها): لا تظهر لوكالة ولا مبدع ولا عميل.
 * للاستخدام الشخصي لمدير النظام وحده، مع زرّ حذف كامل يمسحها قبل أي استعراض.
 */
class CreatorPoolController extends Controller
{
    public function index(Request $r): Response
    {
        $q = PoolCreator::query()->orderByDesc('followers');

        if ($p = $r->query('platform')) $q->where('platform', $p);
        if ($s = $r->query('source')) $q->where('source_type', $s);
        if ($t = $r->query('tier')) $q->where('tier', $t);
        if ($region = $r->query('region')) $q->where('region', $region);
        if ($min = (int) $r->query('min_followers')) $q->where('followers', '>=', $min);
        if ($term = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'ilike', "%{$term}%")
                ->orWhere('categories', 'ilike', "%{$term}%")
                ->orWhere('city', 'ilike', "%{$term}%"));
        }

        return Inertia::render('Admin/CreatorPool', [
            'pool' => $q->paginate(24)->through(fn (PoolCreator $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'platform' => $c->platform,
                'platformLabel' => PoolCreator::PLATFORM_LABELS[$c->platform] ?? $c->platform,
                'accountUrl' => $c->account_url,
                'phone' => $c->phone,
                'followers' => $c->followers,
                'tier' => $c->tier,
                'gender' => $c->gender,
                'categories' => $c->categories ?? [],
                'pricePost' => $c->price_post_minor ? intdiv($c->price_post_minor, 100) : null,
                'priceCoverage' => $c->price_coverage_minor ? intdiv($c->price_coverage_minor, 100) : null,
                'showsFace' => $c->shows_face,
                'region' => $c->region,
                'city' => $c->city,
                'rating' => $c->rating,
                'likes' => $c->likes,
                'store' => $c->store,
                'sourceType' => $c->source_type,
            ]),
            'filters' => $r->only('platform', 'source', 'tier', 'region', 'min_followers', 'q'),
            'facets' => [
                'total' => PoolCreator::count(),
                'platforms' => PoolCreator::selectRaw('platform, count(*) c')->groupBy('platform')->pluck('c', 'platform'),
                'sources' => PoolCreator::selectRaw('source_type, count(*) c')->groupBy('source_type')->pluck('c', 'source_type'),
                'regions' => PoolCreator::whereNotNull('region')->distinct()->orderBy('region')->pluck('region')->take(30),
            ],
        ]);
    }

    /**
     * حذف القاعدة بالكامل — كِلّ سويتش قبل استعراض النظام.
     *
     * يتطلّب تأكيدًا نصّيًّا صريحًا: الحذف نهائي لا رجعة فيه، وإعادة البناء
     * تحتاج ملفّ المصدر ثانيةً.
     */
    public function purge(Request $r): RedirectResponse
    {
        $r->validate(['confirm' => 'required|in:حذف'], [], ['confirm' => 'تأكيد الحذف']);

        $count = PoolCreator::count();
        PoolCreator::query()->delete();

        // نمسح ملفّ الاستيراد المؤقّت أيضًا كي لا يبقى أثر على القرص
        Storage::disk('local')->delete('pool/pool.json');

        return back()->with('ok', "حُذفت قاعدة المبدعين بالكامل ({$count} سجلًّا)، ومُسح ملفّ الاستيراد.");
    }
}
