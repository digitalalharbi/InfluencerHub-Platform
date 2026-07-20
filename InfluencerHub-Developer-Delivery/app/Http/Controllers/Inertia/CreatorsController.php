<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Creators\Actions\CreateCreator;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Models\CreatorCapability;
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use App\Support\Analytics\CreatorAnalytics;
use App\Support\Platforms\PlatformRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * المبدعون (React/Inertia) — قائمة بفلاتر وشرائح وتحليلات، وإضافة مبدع.
 * الصلاحية عبر Policy (viewAny/create) والعزل عبر TenantScope.
 */
class CreatorsController extends Controller
{
    private const STATUS_LABEL = ['prospect' => 'مبدئي', 'active' => 'نشط', 'paused' => 'موقوف', 'blocked' => 'محظور'];
    private const STATUS_TONE = ['prospect' => 'submitted', 'active' => 'active', 'paused' => 'paused', 'blocked' => 'rejected'];

    public function index(Request $r): Response
    {
        $this->authorize('viewAny', Creator::class);

        // الفلتر يقبل مفتاح قدرة (ugc, voiceover…) والنوع القديم في الرابط معًا:
        // روابط محفوظة ونصوص تنقّل ما تزال تحمل `?type=ugc_creator`، وكسرها بلا
        // مقابل. CreatorAnalytics::capabilityFor يترجم القديم إلى قدرة.
        $type = $r->query('type');
        $capability = CreatorAnalytics::capabilityFor($type);
        $q = Creator::query()->with('capabilities')->withCount(['platforms'])->latest();
        if ($capability) {
            CreatorCapabilityService::filter($q, $capability);
        }
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('display_name', 'ilike', "%{$s}%")
                ->orWhere('handle', 'ilike', "%{$s}%")->orWhere('creator_number', 'ilike', "%{$s}%")
                ->orWhere('city', 'ilike', "%{$s}%"));
        }
        if ($v = $r->query('status')) $q->where('status', $v);
        if ($v = $r->query('platform')) $q->where('primary_platform', $v);
        if ($v = $r->query('city')) $q->where('city', $v);
        CreatorAnalytics::applySegment($q, $r->query('seg'));

        $creators = $q->paginate(15)->withQueryString();
        $metrics = CreatorAnalytics::forPage($creators->getCollection());

        $creators->through(fn (Creator $c) => [
            'id' => $c->id,
            'name' => $c->display_name,
            'handle' => $c->handle,
            'number' => $c->creator_number,
            'platform' => $c->primary_platform,
            'followers' => (int) $c->followers_count,
            'rateMinor' => $c->rate_per_post_minor,
            'status' => $c->status,
            'statusLabel' => self::STATUS_LABEL[$c->status] ?? $c->status,
            'statusTone' => self::STATUS_TONE[$c->status] ?? 'draft',
            'tier' => $metrics[$c->id]['tier'] ?? '—',
            'engagement' => $metrics[$c->id]['engagement'] ?? null,
            'verified' => (bool) ($metrics[$c->id]['verified'] ?? false),
            'incomplete' => (bool) ($metrics[$c->id]['incomplete'] ?? false),
            'activeCollabs' => (int) ($metrics[$c->id]['active_collabs'] ?? 0),
            // القدرات تُعرض كاملة: «مؤثّر وصانع محتوى» كان أقصى ما يقوله العمود القديم
            'capabilities' => array_map(
                fn (string $k) => ['key' => $k, 'label' => CreatorCapability::label($k)],
                $c->capabilityKeys(),
            ),
        ]);

        return Inertia::render('Creators/Index', [
            'creators' => $creators,
            'summary' => CreatorAnalytics::summary($type),
            'type' => $type,
            'capabilityOptions' => CreatorCapabilityService::options(),
            'filters' => $r->only('q', 'status', 'platform', 'city', 'seg', 'type'),
            'platformOptions' => PlatformRegistry::options('creator_profile'),
            'cities' => Creator::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->values(),
        ]);
    }

    /**
     * إضافة مبدع — نفس قواعد التحقّق والإجراء المستعملَين في نسخة Blade
     * (CreateCreator وPlatformRegistry)، فلا يوجد منطق إنشاء مكرّر.
     */
    public function store(Request $r, CreateCreator $action): RedirectResponse
    {
        $this->authorize('create', Creator::class);
        $data = $r->validate([
            'display_name' => 'required|string|max:160',
            ...CreatorCapabilityService::rules(),
            'handle' => 'nullable|string|max:80',
            'email' => 'nullable|email|max:160',
            'phone' => 'nullable|string|max:30',
            'primary_platform' => PlatformRegistry::rule('creator_profile', false),
            'followers_count' => 'nullable|integer|min:0',
            'city' => 'nullable|string|max:120',
            'status' => 'nullable|in:prospect,active,paused,blocked',
        ], CreatorCapabilityService::messages());

        try {
            $action->handle($this->org(), $data, $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['display_name' => $e->getMessage()]);
        }

        return redirect(MountPrefix::path($r, '/creators'))->with('ok', 'تمت إضافة المبدع.');
    }

    private function org(): ?Organization
    {
        $id = TenantContext::organizationId();

        return $id ? Organization::find($id) : null;
    }

    /**
     * تحديث بيانات المبدع وحالته.
     *
     * لم يكن للمبدع مسار تحديث: يُنشأ «مبدئيًّا» فيبقى كذلك، والترشيح يبحث في
     * النشطين وحدهم — فيُضاف المبدع ثم يختفي بلا سبب معروف.
     */
    public function update(Request $r, \App\Domain\Creators\Models\Creator $creator): RedirectResponse
    {
        $this->authorize('update', $creator);

        $data = $r->validate([
            'status' => 'sometimes|required|string|in:prospect,active,paused,blocked',
            'display_name' => 'sometimes|required|string|max:190',
            'handle' => 'sometimes|nullable|string|max:120',
            'primary_platform' => 'sometimes|nullable|string|max:30',
        ], [], ['status' => 'الحالة', 'display_name' => 'الاسم']);

        $before = $creator->only(array_keys($data));
        $creator->update($data);

        \App\Domain\Audit\Services\AuditLogger::log('creator.updated', $creator,
            ['from' => $before, 'to' => $data], (int) $creator->tenant_id, (int) $r->user()->id);

        return back()->with('ok', 'حُدّثت بيانات المبدع.');
    }
}
