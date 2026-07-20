<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Publishers\Actions\ConvertPublisherToInfluencer;
use App\Domain\Publishers\Models\Publisher;
use App\Domain\Publishers\Support\PublisherConnectors;
use App\Support\Platforms\PlatformRegistry;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الناشرون — منصّة ذكاء واكتشاف (React/Inertia). بحث/تحليل حسابات المنصّات + حالات موصّلات صادقة.
 * تحويل ناشر إلى مؤثر (idempotent) + إضافة إلى قائمة/ترشيح. Policy(viewAny Client)، معزولة بالمستأجر.
 * لا أرقام وهمية — كل ناشر يحمل مصدره (source) وتاريخ آخر مزامنة.
 */
class PublishersController extends Controller
{
    private const SOURCE_LABEL = ['manual' => 'يدوي', 'import' => 'استيراد', 'sandbox' => 'تجريبي', 'live' => 'مباشر'];
    private const SOURCE_TONE = ['manual' => 'submitted', 'import' => 'submitted', 'sandbox' => 'under_review', 'live' => 'approved'];

    /** تبويبات الوحدة الواحدة «الناشرون» (لا عنصر قائمة مكرّر). */
    private const TABS = ['discovery', 'analytics', 'comparison', 'lists'];

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Client::class);
        $tab = in_array($r->query('tab'), self::TABS, true) ? $r->query('tab') : 'discovery';

        $q = Publisher::query()->latest();
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('handle', 'ilike', "%{$s}%")->orWhere('display_name', 'ilike', "%{$s}%"));
        }
        if ($p = $r->query('platform')) $q->where('platform', $p);
        if ($tab === 'lists' || $r->query('saved')) $q->where('saved', true);
        if ($cat = $r->query('category')) $q->whereJsonContains('categories', $cat);

        $publishers = $q->paginate(18)->withQueryString()->through(fn (Publisher $p) => $this->row($p));

        return Inertia::render('Publishers/Index', [
            'tab' => $tab,
            'publishers' => $publishers,
            'filters' => $r->only('q', 'platform', 'category'),
            'platforms' => PlatformRegistry::options('audience_data'),
            'connectors' => PublisherConnectors::all(),
            'connectorSummary' => PublisherConnectors::summary(),
            'summary' => [
                'total' => Publisher::count(),
                'saved' => Publisher::where('saved', true)->count(),
                'converted' => Publisher::whereNotNull('converted_creator_id')->count(),
            ],
            // تحليلات مجمّعة من بيانات فعلية فقط (تُحسب عند فتح التبويب)
            'analytics' => $tab === 'analytics' ? $this->analytics() : null,
            // خيارات المقارنة (قائمة مختصرة)
            'compareOptions' => $tab === 'comparison'
                ? Publisher::orderByDesc('followers_count')->limit(60)->get()->map(fn (Publisher $p) => $this->row($p))->values()
                : null,
        ]);
    }

    /** تجميعات صادقة من قاعدة البيانات — لا تقديرات ولا أرقام مُخترَعة. */
    private function analytics(): array
    {
        $all = Publisher::get();
        $byPlatform = $all->groupBy('platform')->map(fn ($g, $k) => [
            'platform' => $k, 'label' => PlatformRegistry::label($k), 'count' => $g->count(),
            'followers' => (int) $g->sum('followers_count'),
            'avgEngagement' => $g->whereNotNull('engagement_rate')->count() ? round($g->whereNotNull('engagement_rate')->avg('engagement_rate'), 2) : null,
        ])->values();

        $withEng = $all->whereNotNull('engagement_rate');
        $withGrowth = $all->whereNotNull('growth_30d');

        return [
            'totals' => [
                'publishers' => $all->count(),
                'followers' => (int) $all->sum('followers_count'),
                'avgEngagement' => $withEng->count() ? round($withEng->avg('engagement_rate'), 2) : null,
                'avgGrowth' => $withGrowth->count() ? round($withGrowth->avg('growth_30d'), 2) : null,
            ],
            'byPlatform' => $byPlatform,
            'topFollowers' => $all->sortByDesc('followers_count')->take(5)->map(fn (Publisher $p) => $this->row($p))->values(),
            'topEngagement' => $withEng->sortByDesc('engagement_rate')->take(5)->map(fn (Publisher $p) => $this->row($p))->values(),
            'topGrowth' => $withGrowth->sortByDesc('growth_30d')->take(5)->map(fn (Publisher $p) => $this->row($p))->values(),
        ];
    }

    public function show(Request $r, Publisher $publisher): Response
    {
        $this->authorize('viewAny', Client::class);

        return Inertia::render('Publishers/Show', [
            'publisher' => $this->row($publisher) + [
                'audienceNote' => $publisher->audience_note,
                'brands' => $publisher->brands_worked_with ?? [],
                'convertedCreatorId' => $publisher->converted_creator_id,
            ],
            'canConvert' => $r->user()->can('create', Creator::class),
        ]);
    }

    public function save(Request $r, Publisher $publisher)
    {
        $this->authorize('viewAny', Client::class);
        $publisher->update(['saved' => ! $publisher->saved]);
        return back()->with('ok', $publisher->saved ? 'حُفظ الناشر في قائمتك.' : 'أُزيل من قائمتك.');
    }

    public function convert(Request $r, Publisher $publisher, ConvertPublisherToInfluencer $action)
    {
        $this->authorize('create', Creator::class);
        $data = $r->validate(\App\Domain\Creators\Services\CreatorCapabilityService::rules('capabilities', false));
        // النوع القديم ما يزال مقبولًا من واجهات لم تُحدَّث بعد
        $caps = \App\Domain\Creators\Services\CreatorCapabilityService::normalize($data['capabilities'] ?? []);
        if (! $caps) {
            $legacy = $r->validate(['type' => 'nullable|in:influencer,ugc_creator'])['type'] ?? 'influencer';
            $caps = \App\Domain\Creators\Services\CreatorCapabilityService::LEGACY_TO_CAPS[$legacy];
        }
        $creator = $action->handle($publisher, $r->user(), $caps);
        return redirect(MountPrefix::path($r, "/creators/{$creator->id}"))->with('ok', 'حُوِّل الناشر إلى مؤثر في CRM.');
    }

    private function row(Publisher $p): array
    {
        return [
            'id' => $p->id, 'number' => $p->publisher_number,
            'name' => $p->display_name ?: $p->handle, 'handle' => $p->handle, 'platform' => $p->platform,
            'platformLabel' => PlatformRegistry::label($p->platform),
            'followers' => (int) $p->followers_count,
            'engagement' => $p->engagement_rate, 'growth' => $p->growth_30d,
            'contentTypes' => $p->content_types ?? [], 'categories' => $p->categories ?? [],
            'city' => $p->city, 'language' => $p->language, 'quality' => $p->quality_score,
            'source' => $p->source, 'sourceLabel' => self::SOURCE_LABEL[$p->source] ?? $p->source,
            'sourceTone' => self::SOURCE_TONE[$p->source] ?? 'draft',
            'lastSynced' => $p->last_synced_at?->format('Y-m-d'),
            'saved' => (bool) $p->saved, 'converted' => $p->converted_creator_id !== null,
        ];
    }
}
