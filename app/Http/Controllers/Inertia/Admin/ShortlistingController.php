<?php

namespace App\Http\Controllers\Inertia\Admin;

use App\Domain\AdminPool\Assistant\ShortlistAssistant;
use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\AdminPool\Services\PoolMatchService;
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

        $results = [];
        $hasSearch = $query !== '' || $matcher->hasCriteria($matchCriteria);

        if ($hasSearch) {
            $q = PoolCreator::query();
            if (! empty($criteria['platform'])) $q->where('platform', $criteria['platform']);
            if (! empty($criteria['min_followers'])) $q->where('followers', '>=', $criteria['min_followers']);

            $results = $q->orderByDesc('followers')->limit(400)->get()
                ->map(fn (PoolCreator $c) => $c->toBookingArray($matcher->score($matchCriteria, $c)))
                ->sortByDesc('matchScore')
                ->take(30)
                ->values();
        }

        return Inertia::render('Admin/Shortlisting', [
            'query' => $query,
            'criteria' => $criteria,
            'understood' => $understood,
            'results' => $results,
            'hasSearch' => $hasSearch,
            'assistant' => [
                'driver' => $driver,
                // شفافية: هل OpenAI مربوط فعلًا؟
                'openaiReady' => app(ShortlistAssistant::class)->available() && config('services.pool_assistant.driver') === 'openai',
            ],
            'poolSize' => PoolCreator::count(),
        ]);
    }
}
