<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\AdminPool\Actions\MaterializeSharedCreator;
use App\Domain\AdminPool\Models\CreatorDatabaseOverlay;
use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\AdminPool\Support\CreatorDatabaseAbilities as Ability;
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlist;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * قاعدة المؤثرين (Creator Database) — منتج اكتشاف مبدعين مميّز داخل بوّابة الوكالة.
 *
 * حوكمة مزدوجة تُفرَض في الخادم (لا إخفاء واجهة فقط):
 *  1) الاستحقاق: للمؤسسة `creator_database.access` (خطة/إضافة/تجاوز) وإلا 403.
 *  2) RBAC: دور المستخدم ضمن VIEW؛ وكشف التواصل ضمن VIEW_CONTACT.
 *
 * لا يكشف أيّ مصدر/متجر/موظّف/تكلفة/بيانات بنكية — التسلسل عبر toSharedArray فقط.
 */
class CreatorDatabaseController extends Controller
{
    public function index(Request $r): Response
    {
        [$org, $role] = $this->guard();
        $canContact = Ability::can($role, Ability::VIEW_CONTACT);

        $filters = $r->only('platform', 'creator_type', 'category', 'city', 'region', 'gender', 'shows_face', 'tier', 'min_followers', 'has_price', 'q', 'sort');
        $q = $this->baseQuery($filters);

        // ترتيب اكتشاف حتميّ (لا «ذكاء» زائف): الأكثر متابعة (افتراضي) · الأعلى سعرًا · الأحدث بيانات.
        $sort = in_array($filters['sort'] ?? '', ['price', 'recent'], true) ? $filters['sort'] : 'followers';
        $order = match ($sort) {
            'price' => 'GREATEST(COALESCE(price_post_minor,0), COALESCE(price_coverage_minor,0)) DESC, followers DESC NULLS LAST',
            'recent' => 'last_imported_at DESC NULLS LAST, followers DESC NULLS LAST',
            default => 'followers DESC NULLS LAST',
        };
        $filters['sort'] = $sort;
        $page = $q->orderByRaw($order)->paginate(24)->withQueryString();
        $overlays = $this->overlaysFor($org, collect($page->items())->pluck('id')->all());

        // سياق الحملة (اختياري): الاكتشاف يتدفّق مباشرةً إلى الترشيح بلا قفزات صفحات.
        [$context, $roleByPool] = $this->campaignContext($r, $org, $role);

        $rows = $page->through(fn (PoolCreator $c) => $c->toSharedArray($canContact) + [
            'overlay' => $overlays[$c->id] ?? null,
            'shortlistRole' => $context ? ($roleByPool[$c->id] ?? null) : null,
        ]);

        $facets = $this->facets();

        return Inertia::render('CreatorDatabase/Index', [
            'base' => $this->mountBase($r),
            'creators' => $rows,
            'filters' => $filters,
            'canContact' => $canContact,
            'canUseInCampaign' => Ability::can($role, Ability::USE_IN_CAMPAIGN),
            'facets' => $facets,
            // تسميات المنصّات بلغة الطلب (من PlatformRegistry) لخيارات الفلتر — لا خريطة مكرّرة.
            'platformLabels' => collect($facets['platforms'] ?? [])
                ->keys()->mapWithKeys(fn ($k) => [$k => PoolCreator::platformLabel($k)])->all(),
            'summary' => ['total' => PoolCreator::count()],
            'campaignContext' => $context,
        ]);
    }

    /**
     * سياق الترشيح لحملة عند وجود `?campaign=`: يُرجِع [الحملة أو null، خريطة الدور حسب pool_creator_id].
     * آمن-مستأجريًّا (Campaign محكوم بالنطاق) ولا يكشف أي بيانات داخلية — أعداد وأسماء فقط.
     *
     * @return array{0: array<string,mixed>|null, 1: array<int,string>}
     */
    private function campaignContext(Request $r, Organization $org, ?string $role): array
    {
        $cid = (int) $r->integer('campaign');
        if ($cid <= 0 || ! Ability::can($role, Ability::USE_IN_CAMPAIGN)) {
            return [null, []];
        }
        $campaign = Campaign::find($cid); // محكوم بالمستأجر عبر TenantScope
        if ($campaign === null) {
            return [null, []];
        }

        $version = null;
        $roleByPool = [];
        $primary = 0;
        $backup = 0;
        $shortlist = CampaignShortlist::where('campaign_id', $campaign->id)->first();
        if ($shortlist !== null && ($version = $shortlist->currentVersion()) !== null) {
            $items = $version->items()->with('creator:id,pool_creator_id')->get(['id', 'creator_id', 'is_backup']);
            foreach ($items as $it) {
                $it->is_backup ? $backup++ : $primary++;
                $pid = $it->creator?->pool_creator_id;
                if ($pid) {
                    $roleByPool[$pid] = $it->is_backup ? 'backup' : 'primary';
                }
            }
        }

        return [[
            'id' => $campaign->id,
            'name' => $campaign->name,
            'primaryCount' => $primary,
            'backupCount' => $backup,
            // لا إصدار بعد = مسوّدة ستُنشأ عند أوّل إضافة؛ غير المسوّدة لا تُعدَّل.
            'editable' => $version === null || $version->status === 'draft',
            'shortlistUrl' => $this->mountBase($r).'/campaigns/'.$campaign->id.'/shortlist',
        ], $roleByPool];
    }

    public function show(Request $r, PoolCreator $poolCreator): Response
    {
        [$org, $role] = $this->guard();
        $canContact = Ability::can($role, Ability::VIEW_CONTACT);
        $overlay = $this->overlaysFor($org, [$poolCreator->id])[$poolCreator->id] ?? null;

        return Inertia::render('CreatorDatabase/Show', [
            'base' => $this->mountBase($r),
            'creator' => $poolCreator->toSharedArray($canContact) + ['overlay' => $overlay],
            'canContact' => $canContact,
            'canUseInCampaign' => Ability::can($role, Ability::USE_IN_CAMPAIGN),
            'campaigns' => $this->campaignsForNomination($org, $role),
        ]);
    }

    /** حفظ تراكب المؤسسة الخاصّ (مفضّلة/وسوم/ملاحظات/سعر متفاوَض) — معزول بالمؤسسة. */
    public function overlay(Request $r, PoolCreator $poolCreator): RedirectResponse
    {
        [$org] = $this->guard();
        $data = $r->validate([
            'favorite' => 'sometimes|boolean',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:40',
            'notes' => 'sometimes|nullable|string|max:5000',
            'negotiated_rate' => 'sometimes|nullable|numeric|min:0|max:100000000',
            'relationship_status' => 'sometimes|nullable|string|max:30',
        ]);

        $payload = ['tenant_id' => $org->tenant_id];
        foreach (['favorite', 'tags', 'notes', 'relationship_status'] as $k) {
            if (array_key_exists($k, $data)) {
                $payload[$k] = $data[$k];
            }
        }
        if (array_key_exists('negotiated_rate', $data)) {
            $payload['negotiated_rate_minor'] = $data['negotiated_rate'] !== null ? (int) round($data['negotiated_rate'] * 100) : null;
        }

        CreatorDatabaseOverlay::updateOrCreate(
            ['organization_id' => $org->id, 'pool_creator_id' => $poolCreator->id],
            $payload,
        );

        return back()->with('ok', 'حُفِظت بيانات علاقتك بالمبدع.');
    }

    /** ترشيح مبدع مشترك إلى حملة: يجسّد علاقة مبدع للمستأجر (حتمي) ثم يضيفه للقائمة المختصرة. */
    public function nominate(Request $r, PoolCreator $poolCreator, MaterializeSharedCreator $materialize, ShortlistService $shortlist): RedirectResponse
    {
        [$org, $role] = $this->guard();
        abort_unless(Ability::can($role, Ability::USE_IN_CAMPAIGN), 403);
        $data = $r->validate([
            'campaign_id' => 'required|integer',
            'role' => 'sometimes|in:primary,backup', // أساسي/احتياط — الافتراضي أساسي
        ], [], ['campaign_id' => 'الحملة']);

        $campaign = Campaign::find($data['campaign_id']);
        abort_unless($campaign !== null, 404, 'الحملة غير موجودة.');

        $creator = $materialize->handle($poolCreator, $org, $r->user());
        $sl = $shortlist->getOrCreate($campaign, $r->user()->id);
        $version = $sl->currentVersion();
        // لا يُضاف مرشّح إلى إصدار مُرسَل/محسوم — يُنشأ إصدار جديد أوّلًا من مساحة الترشيح.
        abort_unless($version->status === 'draft', 422, 'القائمة أُرسلت — أنشئ إصدارًا جديدًا لتعديلها.');

        $backup = ($data['role'] ?? 'primary') === 'backup';
        $shortlist->addCreator($version, $creator, $backup);

        return back()->with('ok', $backup ? 'أُضيف كاحتياط للحملة.' : 'أُضيف كأساسيّ للحملة.');
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $q = PoolCreator::query();

        if ($p = ($filters['platform'] ?? null)) {
            $q->where('platform', $p);
        }
        // «نوع المبدع» تصنيف (celebrity|ugc) لا مصدر
        if ($ct = ($filters['creator_type'] ?? null)) {
            $q->where('source_type', $ct === 'ugc' ? 'ugc' : 'celebrity');
        }
        // تصنيف المحتوى — من عمود categories الحقيقي (jsonb)، لا قيَم مُختلَقة
        if ($cat = ($filters['category'] ?? null)) {
            $q->whereJsonContains('categories', $cat);
        }
        // توفّر السعر — سعر منشور أو تغطية فعليّ (> 0)
        if (($filters['has_price'] ?? null) !== null && $filters['has_price'] !== '') {
            if (filter_var($filters['has_price'], FILTER_VALIDATE_BOOL)) {
                $q->where(fn ($w) => $w->where('price_post_minor', '>', 0)->orWhere('price_coverage_minor', '>', 0));
            }
        }
        if ($t = ($filters['tier'] ?? null)) {
            $q->where('tier', $t);
        }
        if ($g = ($filters['gender'] ?? null)) {
            $q->where('gender', $g);
        }
        if (($filters['shows_face'] ?? null) !== null && $filters['shows_face'] !== '') {
            $q->where('shows_face', filter_var($filters['shows_face'], FILTER_VALIDATE_BOOL));
        }
        if ($city = ($filters['city'] ?? null)) {
            $q->whereRaw($this->arNorm('city').' ILIKE '.$this->arNorm('?'), [$city]);
        }
        if ($region = ($filters['region'] ?? null)) {
            $q->where('region', $region);
        }
        if ($mf = ($filters['min_followers'] ?? null)) {
            $q->where('followers', '>=', (int) $mf);
        }
        if ($term = trim((string) ($filters['q'] ?? ''))) {
            // بحث عربي مطبَّع على الطرفين (ألف/ياء/تاء مربوطة/تطويل) — لا مصدر ضمن أبعاد البحث
            $like = '%'.$term.'%';
            $q->where(function ($w) use ($like) {
                $w->whereRaw($this->arNorm('name').' ILIKE '.$this->arNorm('?'), [$like])
                    ->orWhereRaw($this->arNorm('city').' ILIKE '.$this->arNorm('?'), [$like])
                    ->orWhere('account_url', 'ILIKE', $like);
            });
        }

        return $q;
    }

    /** تعبير تطبيع عربي في SQL: يوحّد ألف/ياء/تاء مربوطة ويزيل التطويل. */
    private function arNorm(string $expr): string
    {
        // translate يستبدل حرفًا بحرف؛ نزيل التطويل (ـ) بـ replace ثم نوحّد الحروف
        return "translate(replace(lower($expr),'ـ',''),'أإآىة','اااية')";
    }

    /** @return array{platforms:array,creatorTypes:array,categories:array,regions:array,tiers:array} */
    private function facets(): array
    {
        return [
            'platforms' => PoolCreator::selectRaw('platform, count(*) c')->groupBy('platform')->pluck('c', 'platform'),
            'creatorTypes' => PoolCreator::selectRaw('source_type, count(*) c')->groupBy('source_type')->pluck('c', 'source_type'),
            // تصنيفات حقيقية بأعداد حقيقية — توسيع عناصر مصفوفة jsonb ثم group by (لا أعداد مُختلَقة)
            'categories' => $this->categoryFacet(),
            'regions' => PoolCreator::whereNotNull('region')->selectRaw('region, count(*) c')->groupBy('region')->orderByDesc('c')->limit(20)->pluck('c', 'region'),
            'tiers' => PoolCreator::whereNotNull('tier')->selectRaw('tier, count(*) c')->groupBy('tier')->pluck('c', 'tier'),
        ];
    }

    /** عدد المبدعين الحقيقي لكل تصنيف محتوى، من عمود categories (jsonb) مباشرةً. */
    private function categoryFacet(): Collection
    {
        $table = (new PoolCreator)->getTable();
        $sub = "(SELECT jsonb_array_elements_text(categories) AS cat FROM {$table} "
            ."WHERE categories IS NOT NULL AND jsonb_typeof(categories) = 'array') t";

        return DB::table(DB::raw($sub))
            ->selectRaw('cat, count(*) c')->groupBy('cat')->orderByDesc('c')->limit(24)
            ->pluck('c', 'cat');
    }

    /**
     * تراكبات المؤسسة الخاصّة لمجموعة مبدعين — معزولة بالمؤسسة (لا ترى مؤسسة أخرى).
     *
     * @param  array<int,int>  $poolIds
     * @return array<int,array<string,mixed>>
     */
    private function overlaysFor(Organization $org, array $poolIds): array
    {
        if (empty($poolIds)) {
            return [];
        }

        return CreatorDatabaseOverlay::query()
            ->where('organization_id', $org->id)
            ->whereIn('pool_creator_id', $poolIds)
            ->get()
            ->keyBy('pool_creator_id')
            ->map(fn (CreatorDatabaseOverlay $o) => $o->toArrayForTenant())
            ->all();
    }

    /** حملات المؤسسة المتاحة للترشيح (فقط لمن يملك صلاحية الاستخدام في حملة). */
    private function campaignsForNomination(Organization $org, ?string $role): array
    {
        if (! Ability::can($role, Ability::USE_IN_CAMPAIGN)) {
            return [];
        }

        return Campaign::query()
            ->whereIn('status', ['draft', 'planning', 'active'])
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'name'])
            ->map(fn (Campaign $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    /** حارس مزدوج: استحقاق المؤسسة + صلاحية العرض. يُرجع [org, role]. */
    private function guard(): array
    {
        $org = $this->org();
        abort_unless($org !== null, 403);
        abort_unless(app(EntitlementService::class)->allows($org, 'creator_database.access'), 403, 'قاعدة المؤثرين غير مُفعّلة لهذه المؤسسة.');

        $role = TenantContext::withBypass(fn () => request()->user()?->roleIn($org->id));
        abort_unless(Ability::can($role, Ability::VIEW), 403);

        return [$org, $role];
    }

    private function org(): ?Organization
    {
        $oid = TenantContext::organizationId();

        return $oid ? TenantContext::withBypass(fn () => Organization::find($oid)) : null;
    }

    private function mountBase(Request $r): string
    {
        return MountPrefix::for($r);
    }
}
