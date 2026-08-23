<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\AdminPool\Support\CreatorDatabaseAbilities as Ability;
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\Request;
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

        $filters = $r->only('platform', 'creator_type', 'city', 'region', 'gender', 'shows_face', 'tier', 'min_followers', 'q');
        $q = $this->baseQuery($filters);

        $page = $q->orderByRaw('followers DESC NULLS LAST')->paginate(24)->withQueryString();
        $rows = $page->through(fn (PoolCreator $c) => $c->toSharedArray($canContact));

        return Inertia::render('CreatorDatabase/Index', [
            'base' => $this->mountBase($r),
            'creators' => $rows,
            'filters' => $filters,
            'canContact' => $canContact,
            'canUseInCampaign' => Ability::can($role, Ability::USE_IN_CAMPAIGN),
            'facets' => $this->facets(),
            'summary' => ['total' => PoolCreator::count()],
        ]);
    }

    public function show(Request $r, PoolCreator $poolCreator): Response
    {
        [, $role] = $this->guard();
        $canContact = Ability::can($role, Ability::VIEW_CONTACT);

        return Inertia::render('CreatorDatabase/Show', [
            'base' => $this->mountBase($r),
            'creator' => $poolCreator->toSharedArray($canContact),
            'canContact' => $canContact,
            'canUseInCampaign' => Ability::can($role, Ability::USE_IN_CAMPAIGN),
        ]);
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function baseQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $q = PoolCreator::query();

        if ($p = ($filters['platform'] ?? null)) {
            $q->where('platform', $p);
        }
        // «نوع المبدع» تصنيف (celebrity|ugc) لا مصدر
        if ($ct = ($filters['creator_type'] ?? null)) {
            $q->where('source_type', $ct === 'ugc' ? 'ugc' : 'celebrity');
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
            $q->whereRaw($this->arNorm('city') . ' ILIKE ' . $this->arNorm('?'), [$city]);
        }
        if ($region = ($filters['region'] ?? null)) {
            $q->where('region', $region);
        }
        if ($mf = ($filters['min_followers'] ?? null)) {
            $q->where('followers', '>=', (int) $mf);
        }
        if ($term = trim((string) ($filters['q'] ?? ''))) {
            // بحث عربي مطبَّع على الطرفين (ألف/ياء/تاء مربوطة/تطويل) — لا مصدر ضمن أبعاد البحث
            $like = '%' . $term . '%';
            $q->where(function ($w) use ($like) {
                $w->whereRaw($this->arNorm('name') . ' ILIKE ' . $this->arNorm('?'), [$like])
                    ->orWhereRaw($this->arNorm('city') . ' ILIKE ' . $this->arNorm('?'), [$like])
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

    /** @return array{platforms:array,creatorTypes:array,regions:array,tiers:array} */
    private function facets(): array
    {
        return [
            'platforms' => PoolCreator::selectRaw('platform, count(*) c')->groupBy('platform')->pluck('c', 'platform'),
            'creatorTypes' => PoolCreator::selectRaw('source_type, count(*) c')->groupBy('source_type')->pluck('c', 'source_type'),
            'regions' => PoolCreator::whereNotNull('region')->selectRaw('region, count(*) c')->groupBy('region')->orderByDesc('c')->limit(20)->pluck('c', 'region'),
            'tiers' => PoolCreator::whereNotNull('tier')->selectRaw('tier, count(*) c')->groupBy('tier')->pluck('c', 'tier'),
        ];
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
