<?php

namespace App\Http\Controllers\Inertia\Admin;

use App\Domain\AdminPool\Models\{PoolCreator, PoolRecommendation};
use App\Domain\AdminPool\Services\PoolMatchService;
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
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
    public function index(Request $r, PoolMatchService $matcher): Response
    {
        $q = PoolCreator::query();

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

        // معايير محرّك الترشيح
        $criteria = [
            'platform' => $r->query('platform') ?: null,
            'categories' => array_filter(explode(',', (string) $r->query('match_categories'))),
            'min_followers' => (int) $r->query('min_followers') ?: null,
            'budget_minor' => ($b = (int) $r->query('budget_riyals')) ? $b * 100 : null,
        ];
        $matching = $matcher->hasCriteria($criteria);

        // في وضع الترشيح: نسجّل ونرتّب بالدرجة. وإلا نرتّب بالمتابعين.
        if ($matching) {
            $q->orderByRaw('followers DESC NULLS LAST');
            $ranked = $q->limit(300)->get()
                ->map(function (PoolCreator $c) use ($matcher, $criteria) {
                    $m = $matcher->score($criteria, $c);

                    return [$c, $m];
                })
                ->sortByDesc(fn ($pair) => $pair[1]['score'])
                ->values();
            $page = new \Illuminate\Pagination\LengthAwarePaginator(
                $ranked->take(24), $ranked->count(), 24, 1,
                ['path' => $r->url(), 'query' => $r->query()],
            );
            $rows = $page->through(fn ($pair) => $this->row($pair[0], $pair[1]));
        } else {
            $rows = $q->orderByRaw('followers DESC NULLS LAST')->paginate(24)->through(fn (PoolCreator $c) => $this->row($c));
        }

        return Inertia::render('Admin/CreatorPool', [
            'matching' => $matching,
            'pool' => $rows,
            'filters' => $r->only('platform', 'source', 'tier', 'region', 'min_followers', 'q', 'match_categories', 'budget_riyals'),
            // العملاء المتاحون للتحويل — المدير يتجاوز نطاق المستأجر ليراهم جميعًا
            'clients' => $this->clientsForTransfer(),
            'facets' => [
                'total' => PoolCreator::count(),
                'platforms' => PoolCreator::selectRaw('platform, count(*) c')->groupBy('platform')->pluck('c', 'platform'),
                'sources' => PoolCreator::selectRaw('source_type, count(*) c')->groupBy('source_type')->pluck('c', 'source_type'),
                'tiers' => PoolCreator::whereNotNull('tier')->selectRaw('tier, count(*) c')->groupBy('tier')->pluck('c', 'tier'),
                'regions' => PoolCreator::whereNotNull('region')->distinct()->orderBy('region')->pluck('region')->take(30)->values(),
                // مؤشّرات موجزة للبطاقات العلوية
                'reach' => (int) PoolCreator::sum('followers'),
                'priced' => PoolCreator::where(fn ($w) => $w->whereNotNull('price_coverage_minor')->orWhereNotNull('price_post_minor'))->count(),
                'contactable' => PoolCreator::whereNotNull('phone')->count(),
            ],
        ]);
    }

    private function row(PoolCreator $c, ?array $match = null): array
    {
        return $c->toBookingArray($match);
    }

    /**
     * الملفّ الكامل لمؤثر القاعدة — صفحة تبويبات احترافية لمدير النظام.
     *
     * تعرض بيانات السورسنغ والتسعير والتواصل الحقيقية فقط (لا سجلّ حملات مُلفّق:
     * مؤثرو القاعدة مستوردون للترشيح، لا يملكون تاريخ تنفيذ حتى يُحجزوا عبر عميل).
     */
    public function show(PoolCreator $poolCreator): Response
    {
        return Inertia::render('Admin/CreatorProfile', [
            'creator' => $poolCreator->toBookingArray(),
            'clients' => $this->clientsForTransfer(),
        ]);
    }

    /**
     * تعديل أسعار التكلفة والبيع لمؤثر القاعدة — أسعار ثابتة قابلة للتعديل والاعتماد.
     *
     * القيم تُدخَل بالريال وتُخزَّن بالهللة (×100). أي حقل فارغ يُمسح (يصبح بلا سعر).
     * لمدير النظام وحده (المجموعة محميّة بـsystem_admin).
     */
    public function updatePricing(Request $r, PoolCreator $poolCreator): RedirectResponse
    {
        $data = $r->validate([
            'cost_post' => 'nullable|numeric|min:0|max:100000000',
            'sell_post' => 'nullable|numeric|min:0|max:100000000',
            'cost_coverage' => 'nullable|numeric|min:0|max:100000000',
            'sell_coverage' => 'nullable|numeric|min:0|max:100000000',
        ], [], [
            'cost_post' => 'تكلفة المنشور', 'sell_post' => 'بيع المنشور',
            'cost_coverage' => 'تكلفة التغطية', 'sell_coverage' => 'بيع التغطية',
        ]);

        $toMinor = fn ($v) => ($v === null || $v === '') ? null : (int) round(((float) $v) * 100);

        $poolCreator->update([
            'cost_post_minor' => $toMinor($data['cost_post'] ?? null),
            'price_post_minor' => $toMinor($data['sell_post'] ?? null),
            'cost_coverage_minor' => $toMinor($data['cost_coverage'] ?? null),
            'price_coverage_minor' => $toMinor($data['sell_coverage'] ?? null),
        ]);

        return back()->with('ok', "حُدّثت أسعار «{$poolCreator->name}» واعتُمدت.");
    }

    /**
     * تحويل مبدعين مختارين إلى عميل — نسخة مستقلّة عن القاعدة.
     *
     * تأخذ snapshot لا مرجعًا: لو حُذفت القاعدة لاحقًا تبقى توصية العميل. ولا
     * تنقل الجوّال: التواصل شأن المدير، لا يُكشَف للعميل.
     */
    public function transfer(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'client_id' => 'required|integer',
            'campaign_id' => 'nullable|integer',
            'pool_ids' => 'required|array|min:1',
            'pool_ids.*' => 'integer',
        ], [], ['client_id' => 'العميل', 'pool_ids' => 'المبدعون']);

        // العميل يُقرأ بتجاوز النطاق (المدير عابر للمستأجرين)
        $prev = TenantContext::bypassing();
        TenantContext::bypass(true);
        $client = Client::find($data['client_id']);
        TenantContext::bypass($prev);
        abort_unless($client, 404, 'العميل غير موجود.');

        $creators = PoolCreator::whereIn('id', $data['pool_ids'])->get();
        $now = now();
        $made = 0;

        foreach ($creators as $c) {
            // منع التكرار: نفس المبدع لنفس العميل والحملة لا يُوصى مرّتين
            $exists = PoolRecommendation::withoutGlobalScopes()
                ->where('tenant_id', $client->tenant_id)
                ->where('client_id', $client->id)
                ->where('campaign_id', $data['campaign_id'] ?? null)
                ->where('platform', $c->platform)
                ->where('account_url', $c->account_url)
                ->exists();
            if ($exists) {
                continue;
            }

            PoolRecommendation::withoutGlobalScopes()->create([
                'tenant_id' => $client->tenant_id,
                'client_id' => $client->id,
                'campaign_id' => $data['campaign_id'] ?? null,
                // نسخة الحقول التي يراها العميل — بلا جوّال
                'name' => $c->name,
                'platform' => $c->platform,
                'account_url' => $c->account_url,
                'followers' => $c->followers,
                'categories' => $c->categories,
                'price_minor' => $c->price_coverage_minor ?? $c->price_post_minor,
                'region' => $c->region,
                'city' => $c->city,
                'source_type' => $c->source_type,
                'status' => 'recommended',
                'recommended_by' => $r->user()->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $made++;
        }

        return back()->with('ok', "حُوِّل {$made} مبدعًا إلى العميل «{$client->display_name}» كتوصية.");
    }

    /** @return \Illuminate\Support\Collection */
    private function clientsForTransfer()
    {
        $prev = TenantContext::bypassing();
        TenantContext::bypass(true);
        $clients = Client::query()->orderBy('display_name')->get(['id', 'display_name', 'tenant_id']);
        TenantContext::bypass($prev);

        return $clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name]);
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
