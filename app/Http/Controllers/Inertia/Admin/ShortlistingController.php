<?php

namespace App\Http\Controllers\Inertia\Admin;

use App\Domain\AdminPool\Assistant\ShortlistAssistant;
use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\AdminPool\Services\PoolMatchService;
use App\Domain\CRM\Models\Client;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\{Inertia, Response};

/**
 * ترشيح المؤثرين — محرّك بحث/مساعد فوق قاعدة مدير النظام.
 *
 * يقبل طلبًا بلغة طبيعية (اليوم بقواعد، غدًا OpenAI بلا تغيير هنا)، يحوّله إلى
 * معايير، ويرتّب القاعدة بالملاءمة مع بيانات الحجز الكاملة. محميّ بـsystem_admin.
 */
class ShortlistingController extends Controller
{
    public function index(Request $r, ShortlistAssistant $assistant, PoolMatchService $matcher): Response
    {
        $query = trim((string) $r->query('query', ''));
        $understood = [];
        $driver = 'rule';

        // معايير صريحة من الفلاتر
        $criteria = array_filter([
            'platform' => $r->query('platform'),
            'categories' => array_filter(explode(',', (string) $r->query('categories'))),
            'min_followers' => (int) $r->query('min_followers') ?: null,
            'budget_riyals' => (int) $r->query('budget_riyals') ?: null,
        ]);

        // المساعد يفهم الطلب النصّي ويكمّل المعايير (الصريح يفوز)
        if ($query !== '') {
            $parsed = $assistant->interpret($query);
            $understood = $parsed['understood'];
            $driver = $parsed['driver'];
            $criteria = array_merge($parsed['criteria'], $criteria);
        }

        // تحويل الميزانية إلى وحدات صغرى للمُطابِق
        $matchCriteria = $criteria;
        if (! empty($criteria['budget_riyals'])) {
            $matchCriteria['budget_minor'] = (int) $criteria['budget_riyals'] * 100;
        }

        $results = collect();
        $analytics = null;
        $hasSearch = $query !== '' || $matcher->hasCriteria($matchCriteria);

        if ($hasSearch) {
            $base = PoolCreator::query();
            if (! empty($criteria['platform'])) $base->where('platform', $criteria['platform']);
            if (! empty($criteria['min_followers'])) $base->where('followers', '>=', $criteria['min_followers']);

            // عدد المطابقين للمعايير الصارمة (قبل الاقتصار) — سياق للتحليلات
            $candidates = (clone $base)->count();

            $results = $base->orderByDesc('followers')->limit(400)->get()
                ->map(fn (PoolCreator $c) => $c->toBookingArray($matcher->score($matchCriteria, $c)))
                ->sortByDesc('matchScore')
                ->take(30)
                ->values();

            $analytics = $this->analyze($results, $candidates);
        }

        return Inertia::render('Admin/Shortlisting', [
            'query' => $query,
            'criteria' => $criteria,
            'understood' => $understood,
            'results' => $results,
            'analytics' => $analytics,
            'hasSearch' => $hasSearch,
            'clients' => $this->clientsForTransfer(),
            'assistant' => [
                'driver' => $driver,
                // شفافية: هل OpenAI مربوط فعلًا؟
                'openaiReady' => app(ShortlistAssistant::class)->available() && config('services.pool_assistant.driver') === 'openai',
            ],
            'poolSize' => PoolCreator::count(),
        ]);
    }

    /**
     * تحليل مجموعة النتائج المعروضة — أرقام موجزة قابلة للقراءة الفورية.
     *
     * تُحسب على النتائج الظاهرة (لا على القاعدة كلها) كي تطابق ما يراه المدير،
     * مع تمرير عدد المطابقين الكلّي كسياق. الأسعار بالريال (وحدة كبرى).
     */
    private function analyze(\Illuminate\Support\Collection $results, int $candidates): array
    {
        $scores = $results->pluck('matchScore')->filter(fn ($s) => $s !== null);
        $cover = $results->pluck('sellCoverage')->filter(fn ($v) => $v !== null && $v > 0);

        return [
            'shown' => $results->count(),
            'candidates' => $candidates,
            'avgScore' => (int) round($scores->avg() ?? 0),
            'topScore' => (int) ($scores->max() ?? 0),
            'reach' => (int) $results->sum('followers'),
            'contactable' => $results->filter(fn ($r) => ! empty($r['phone']))->count(),
            'pricedCount' => $cover->count(),
            'avgCoverage' => $cover->isNotEmpty() ? (int) round($cover->avg()) : null,
            'minCoverage' => $cover->isNotEmpty() ? (int) $cover->min() : null,
            'maxCoverage' => $cover->isNotEmpty() ? (int) $cover->max() : null,
            // توزيعات للرسوم المصغّرة
            'platforms' => $results->groupBy('platformLabel')->map->count()->sortDesc(),
            'tiers' => $results->groupBy(fn ($r) => $r['tier'] ?? '—')->map->count(),
        ];
    }

    /**
     * العملاء المتاحون للتحويل — المدير عابر للمستأجرين فيراهم جميعًا.
     * @return \Illuminate\Support\Collection
     */
    private function clientsForTransfer()
    {
        $prev = TenantContext::bypassing();
        TenantContext::bypass(true);
        $clients = Client::query()->orderBy('display_name')->get(['id', 'display_name']);
        TenantContext::bypass($prev);

        return $clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name]);
    }
}
