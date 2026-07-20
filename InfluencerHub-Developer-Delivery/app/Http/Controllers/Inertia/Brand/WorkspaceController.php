<?php

namespace App\Http\Controllers\Inertia\Brand;

use App\Domain\Brands\Services\AgencyDelegationService;
use App\Domain\Brands\Services\BrandOnboardingService;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Communications\Models\Notification;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Contracts\Models\Contract;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payout;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Analytics\FinancialMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مساحة العلامة.
 *
 * السياق مضبوط من `EnsureBrandMember`، فلا يُضبط هنا ولا يُعاد — كل استعلام
 * أدناه مُنطّق بمستأجر العلامة تلقائيًّا.
 *
 * والأقسام تُبنى على البيانات القائمة: كل عدّاد يعكس سجلّات حقيقية، ولا يُعرض
 * قسمٌ إلا وله مسار يعمل. القسم الذي لا بيانات له يقول ذلك صراحةً ويُظهر
 * الفعل الذي يبدؤه، بدل رقمٍ صفريّ بلا معنى.
 */
class WorkspaceController extends Controller
{
    public function __construct(private BrandOnboardingService $onboarding) {}

    public function overview(Request $r): Response
    {
        $brand = $this->brand($r);
        $tenantId = TenantContext::tenantId();
        $orgId = TenantContext::organizationId();

        $counts = [
            'requests' => ServiceRequest::count(),
            'campaigns' => Campaign::count(),
            'shortlists' => Collaboration::whereIn('status', ['offered', 'accepted'])->count(),
            'content' => ContentItem::count(),
            'contracts' => Contract::count(),
            'invoices' => Invoice::count(),
            'payouts' => Payout::count(),
            'team' => OrganizationMembership::where('organization_id', $orgId)->where('status', 'active')->count(),
            'agencies' => BrandWorkspaceRelationship::where('brand_id', $brand->id)
                ->where('relationship_type', BrandWorkspaceRelationship::MANAGING_AGENCY)
                ->where('status', 'active')->whereNull('ended_at')->count(),
        ];

        // الأرقام المالية من مصدر الحساب الموحَّد — لا معادلة ثانية هنا
        $finance = FinancialMetrics::agency($tenantId);

        return Inertia::render('BrandPortal/Overview', [
            'brand' => $this->brandPayload($brand),
            'counts' => $counts,
            'finance' => [
                'revenueMinor' => $finance['revenue_minor'],
                'costMinor' => $finance['cost_minor'],
                'profitMinor' => $finance['profit_minor'],
                'margin' => $finance['margin'],
                'currency' => 'SAR',
            ],
            'onboarding' => $this->onboarding->checklist($brand, $tenantId, $orgId),
            'nextAction' => $this->nextAction($counts),
        ]);
    }

    public function requests(Request $r): Response
    {
        return Inertia::render('BrandPortal/Requests', [
            'brand' => $this->brandPayload($this->brand($r)),
            'items' => ServiceRequest::latest()->limit(50)->get()->map(fn ($x) => [
                'id' => $x->id, 'number' => $x->request_number, 'title' => $x->title,
                'status' => $x->status, 'statusLabel' => __("statuses.{$x->status}"),
                'statusTone' => __("statuses.tone.{$x->status}"),
                'dueAt' => $x->due_at?->format('Y-m-d'),
            ]),
        ]);
    }

    public function campaigns(Request $r): Response
    {
        return Inertia::render('BrandPortal/Campaigns', [
            'brand' => $this->brandPayload($this->brand($r)),
            'items' => Campaign::latest()->limit(50)->get()->map(fn ($x) => [
                'id' => $x->id, 'number' => $x->campaign_number, 'name' => $x->name,
                'status' => $x->status, 'statusLabel' => __("statuses.{$x->status}"),
                'statusTone' => __("statuses.tone.{$x->status}"),
                'budgetMinor' => (int) ($x->budget_minor ?? 0),
            ]),
        ]);
    }

    /**
     * أقسامٌ تتشارك شكلًا واحدًا: سجلّ بمعرّف وعنوان وحالة.
     *
     * تُبنى بمُعرِّف واحد لا بستّة متحكّمات متطابقة — والفرق بينها في الأعمدة
     * التي يستحقّها كل كيان، لا في هيكل الصفحة.
     */
    public function content(Request $r): Response
    {
        return $this->listing($r, 'المحتوى', ContentItem::latest()->limit(50)->get()->map(fn ($x) => [
            'id' => $x->id, 'title' => $x->title ?? "محتوى #{$x->id}",
            'meta' => $x->platform ?? '—',
            'status' => $x->status, 'statusLabel' => __("statuses.{$x->status}"),
            'statusTone' => __("statuses.tone.{$x->status}"),
        ]), 'لا محتوى بعد', 'المحتوى يصل هنا حين يرفعه المبدعون على حملاتك.');
    }

    public function contracts(Request $r): Response
    {
        return $this->listing($r, 'العقود', Contract::latest()->limit(50)->get()->map(fn ($x) => [
            'id' => $x->id, 'title' => $x->title ?? $x->contract_number,
            'meta' => $x->contract_number,
            'status' => $x->status, 'statusLabel' => __("statuses.{$x->status}"),
            'statusTone' => __("statuses.tone.{$x->status}"),
        ]), 'لا عقود بعد', 'العقد يُصدَر من التعاون بعد قبول المبدع.');
    }

    public function invoices(Request $r): Response
    {
        return $this->listing($r, 'الفواتير', Invoice::latest()->limit(50)->get()->map(fn ($x) => [
            'id' => $x->id, 'title' => $x->invoice_number,
            'meta' => number_format(((int) $x->total_minor) / 100, 2).' ر.س',
            'status' => $x->status, 'statusLabel' => __("statuses.{$x->status}"),
            'statusTone' => __("statuses.tone.{$x->status}"),
        ]), 'لا فواتير بعد', 'الفاتورة تُصدَر بعد إثبات نشر المحتوى.');
    }

    public function payouts(Request $r): Response
    {
        return $this->listing($r, 'المدفوعات', Payout::latest()->limit(50)->get()->map(fn ($x) => [
            'id' => $x->id, 'title' => $x->payout_number,
            'meta' => number_format(((int) $x->amount_minor) / 100, 2).' ر.س',
            'status' => $x->status, 'statusLabel' => __("statuses.{$x->status}"),
            'statusTone' => __("statuses.tone.{$x->status}"),
        ]), 'لا مدفوعات بعد', 'المستحقّ يُشتقّ من التعاون ولا يُدخَل يدويًّا.');
    }

    public function shortlists(Request $r): Response
    {
        return $this->listing($r, 'الترشيحات', Collaboration::latest()->limit(50)->get()->map(fn ($x) => [
            'id' => $x->id, 'title' => $x->title ?? "تعاون #{$x->id}",
            'meta' => number_format(((int) ($x->fee_minor ?? 0)) / 100, 2).' ر.س',
            'status' => $x->status, 'statusLabel' => __("statuses.{$x->status}"),
            'statusTone' => __("statuses.tone.{$x->status}"),
        ]), 'لا ترشيحات بعد', 'الترشيح يبدأ من مخرَج حملة.');
    }

    /**
     * التقارير — بالمعادلة الموحَّدة وحدها.
     *
     * لا حساب ثانٍ هنا: الربح والهامش يأتيان من `FinancialMetrics`، وهو مصدر
     * الحساب الوحيد. معادلةٌ ثانية في الواجهة تُنتج رقمًا يخالف بقيّة النظام.
     */
    public function reports(Request $r): Response
    {
        $tenantId = TenantContext::tenantId();
        $finance = FinancialMetrics::agency($tenantId);

        return Inertia::render('BrandPortal/Reports', [
            'brand' => $this->brandPayload($this->brand($r)),
            'finance' => [
                'revenueMinor' => $finance['revenue_minor'],
                'costMinor' => $finance['cost_minor'],
                'profitMinor' => $finance['profit_minor'],
                'margin' => $finance['margin'],
            ],
            'campaigns' => Campaign::where('status', 'completed')->latest()->limit(20)->get()
                ->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name,
                    'budgetMinor' => (int) ($c->budget_minor ?? 0),
                ]),
        ]);
    }

    public function notifications(Request $r): Response
    {
        return Inertia::render('BrandPortal/Notifications', [
            'brand' => $this->brandPayload($this->brand($r)),
            'items' => Notification::where('user_id', $r->user()->id)
                ->latest()->limit(50)->get()->map(fn ($n) => [
                    'id' => $n->id, 'title' => $n->title, 'body' => $n->body,
                    'actionUrl' => $n->action_url, 'read' => $n->read_at !== null,
                    'at' => $n->created_at?->format('Y-m-d H:i'),
                ]),
        ]);
    }

    public function team(Request $r): Response
    {
        $orgId = TenantContext::organizationId();

        $members = OrganizationMembership::where('organization_id', $orgId)
            ->with('user')->get()->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->user?->name ?? '—',
                'email' => $m->user?->email ?? '—',
                'role' => $m->role,
                'roleLabel' => __("roles.{$m->role}"),
                'status' => $m->status,
            ]);

        return Inertia::render('BrandPortal/Team', [
            'brand' => $this->brandPayload($this->brand($r)),
            'members' => $members,
            'canManage' => $r->attributes->get('brandMembership')?->role === 'brand_admin',
        ]);
    }

    /**
     * الوكالات المفوَّضة — والنطاق المفوَّض ظاهر لكلٍّ منها.
     *
     * عرض النطاق ليس تفصيلًا: التفويض بلا نطاق ظاهر يصير وصولًا شاملًا في
     * ذهن القارئ، فيُمنَح ولا يُراجَع.
     */
    public function agencies(Request $r): Response
    {
        $brand = $this->brand($r);

        // العلاقات تعبر المستأجرين، فقراءة أسماء الوكالات تحتاج تجاوزًا محدَّدًا
        $items = TenantContext::withBypass(function () use ($brand) {
            return BrandWorkspaceRelationship::where('brand_id', $brand->id)
                ->where('relationship_type', BrandWorkspaceRelationship::MANAGING_AGENCY)
                ->get()->map(function ($rel) {
                    $tenant = Tenant::find($rel->tenant_id);

                    return [
                        'id' => $rel->id,
                        'agency' => $tenant?->name ?? '—',
                        'status' => $rel->status,
                        'services' => $rel->services_scope ?? [],
                        'startedAt' => $rel->started_at?->format('Y-m-d'),
                        'endedAt' => $rel->ended_at?->format('Y-m-d'),
                        'isLive' => $rel->isLive(),
                    ];
                })->values();
        });

        /**
         * الوكالات المتاحة للدعوة.
         *
         * تُعرض بالاسم والمعرّف فقط — لا شيء عن عملائها ولا حجمها ولا علاماتها.
         * وهي قائمة الوكالات المسجَّلة النشِطة، وهذه معلومة تجارية معروضة أصلًا
         * على الموقع، لا كشف عن بيانات مستأجر.
         */
        $available = TenantContext::withBypass(function () use ($brand) {
            $taken = BrandWorkspaceRelationship::where('brand_id', $brand->id)
                ->whereIn('status', ['active', 'pending'])->whereNull('ended_at')
                ->pluck('tenant_id');

            return Tenant::where('type', Tenant::TYPE_AGENCY)
                ->where('status', 'active')
                ->whereNotIn('id', $taken)
                ->orderBy('name')->limit(100)
                ->get(['id', 'name'])
                ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values();
        });

        return Inertia::render('BrandPortal/Agencies', [
            'brand' => $this->brandPayload($brand),
            'items' => $items,
            'available' => $available,
            'allServices' => BrandWorkspaceRelationship::SERVICES,
            'canManage' => $r->attributes->get('brandMembership')?->role === 'brand_admin',
        ]);
    }

    /**
     * دعوة وكالة بنطاق محدَّد.
     *
     * النطاق مطلوب في التحقّق نفسه — لا يصل الخادمَ طلبٌ بلا نطاق فيُملأ
     * بافتراض. والدعوة تبدأ `pending` حتّى توافق الوكالة.
     */
    public function inviteAgency(Request $r, AgencyDelegationService $svc): RedirectResponse
    {
        $data = $r->validate([
            'agency_tenant_id' => 'required|integer',
            'services' => 'required|array|min:1',
            'services.*' => 'required|string|in:'.implode(',', BrandWorkspaceRelationship::SERVICES),
        ], [], ['services' => 'نطاق الخدمات', 'agency_tenant_id' => 'الوكالة']);

        try {
            $svc->inviteAgency($this->brand($r), (int) $data['agency_tenant_id'], $data['services'], $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['agency_tenant_id' => $e->getMessage()]);
        }

        return back()->with('ok', 'أُرسلت الدعوة — تنتظر موافقة الوكالة.');
    }

    public function updateScope(Request $r, int $relationship, AgencyDelegationService $svc): RedirectResponse
    {
        $data = $r->validate([
            'services' => 'required|array|min:1',
            'services.*' => 'required|string|in:'.implode(',', BrandWorkspaceRelationship::SERVICES),
        ], [], ['services' => 'نطاق الخدمات']);

        try {
            $svc->updateScope($this->relationshipOf($r, $relationship), $data['services'], $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['services' => $e->getMessage()]);
        }

        return back()->with('ok', 'حُدّث النطاق المفوَّض.');
    }

    public function revokeAgency(Request $r, int $relationship, AgencyDelegationService $svc): RedirectResponse
    {
        try {
            $svc->revoke($this->relationshipOf($r, $relationship), $r->user(), $r->input('reason'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['revoke' => $e->getMessage()]);
        }

        return back()->with('ok', 'أُنهي التفويض. البيانات المنتَجة تبقى في مساحتك.');
    }

    /**
     * العلاقة يجب أن تخصّ علامة هذا الطلب — منع IDOR.
     *
     * الصفّ غير مُنطّق بالمستأجر (يربط مستأجرَين)، فلا يحميه TenantScope —
     * والتحقّق هنا هو الحارس الوحيد.
     */
    private function relationshipOf(Request $r, int $id): BrandWorkspaceRelationship
    {
        $rel = BrandWorkspaceRelationship::find($id);

        abort_unless($rel && $rel->brand_id === $this->brand($r)->id, 404);

        return $rel;
    }

    public function settings(Request $r): Response
    {
        return Inertia::render('BrandPortal/Settings', [
            'brand' => $this->brandPayload($this->brand($r)) + [
                'description' => $this->brand($r)->description,
                'toneOfVoice' => $this->brand($r)->tone_of_voice,
                'targetAudience' => $this->brand($r)->target_audience,
            ],
            'socialAccounts' => $this->brand($r)->socialAccounts()->get()
                ->map(fn ($s) => ['id' => $s->id, 'platform' => $s->platform, 'handle' => $s->handle]),
            'canManage' => $r->attributes->get('brandMembership')?->role === 'brand_admin',
        ]);
    }

    // ===== داخلي =====

    private function brand(Request $r): Brand
    {
        return $r->attributes->get('brand');
    }

    /**
     * صفحة قائمة.
     *
     * `emptyTitle` و`emptyHint` إلزاميّان: القسم الفارغ يقول **لماذا** هو فارغ
     * وما الذي يملؤه، بدل جدولٍ بلا صفوف يترك القارئ يظنّ أن شيئًا تعطّل.
     */
    private function listing(Request $r, string $title, $items, string $emptyTitle, string $emptyHint): Response
    {
        return Inertia::render('BrandPortal/Listing', [
            'brand' => $this->brandPayload($this->brand($r)),
            'title' => $title,
            'items' => $items,
            'emptyTitle' => $emptyTitle,
            'emptyHint' => $emptyHint,
        ]);
    }

    /** @return array<string,mixed> */
    private function brandPayload(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'sector' => $brand->sector,
            'website' => $brand->website,
            'logoPath' => $brand->logo_path,
            'status' => $brand->status,
        ];
    }

    /**
     * الفعل التالي الواحد.
     *
     * لوحةٌ تعرض تسعة أقسام فارغة لا تقول للمستخدم ما يفعله. الترتيب يتبع
     * سلسلة الاشتقاق: طلب ← حملة ← ترشيح ← محتوى.
     */
    private function nextAction(array $counts): array
    {
        return match (true) {
            $counts['requests'] === 0 => ['label' => 'قدّم أوّل طلب', 'href' => '/brand/requests',
                'why' => 'الطلب مدخل الحملة — منه تُشتقّ ولا تُعاد كتابته.'],
            $counts['campaigns'] === 0 => ['label' => 'حوّل طلبك إلى حملة', 'href' => '/brand/campaigns',
                'why' => 'طلبٌ بلا حملة لا يصل المبدعين.'],
            $counts['shortlists'] === 0 => ['label' => 'رشّح مبدعين', 'href' => '/brand/campaigns',
                'why' => 'الحملة بلا مبدعين لا تنتج محتوى.'],
            $counts['content'] === 0 => ['label' => 'تابع المحتوى', 'href' => '/brand/content',
                'why' => 'المحتوى هو ما تدفع مقابله.'],
            default => ['label' => 'راجع تقاريرك', 'href' => '/brand/reports',
                'why' => 'الحملة تُقاس بنتائجها لا بإطلاقها.'],
        };
    }
}
