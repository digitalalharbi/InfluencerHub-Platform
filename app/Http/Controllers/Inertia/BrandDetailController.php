<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Services\BrandWorkflowService;
use App\Domain\CRM\Support\ClientNotifier;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل العلامة (React/Inertia) — ملف العلامة + سير عمل الاعتماد + سجل قرارات/حالة.
 * الإجراءات تعيد استخدام BrandWorkflowService (لا نسختا منطق). view للعرض، update للإجراءات.
 */
class BrandDetailController extends Controller
{
    /** الإجراءات المتاحة لكل حالة → [action, label, tone, input(none|reason|note)]. */
    private const ACTIONS = [
        'submitted' => [['start', 'بدء المراجعة', 'primary', 'none']],
        // الاعتماد يفتح ملاحظة اختيارية (مبرّر المراجِع)؛ طلب التعديل يتطلّب سببًا.
        'under_review' => [['approve', 'اعتماد العلامة', 'primary', 'note'], ['request-changes', 'طلب تعديل', 'ghost', 'reason']],
        'approved' => [['suspend', 'تعليق العلامة', 'danger', 'reason']],
        'suspended' => [['approve', 'إعادة الاعتماد', 'primary', 'note']],
        // المسوّدة يرسلها العميل من بوابته عادةً، وتُرسلها الوكالة نيابةً عنه
        // حين لا يكون للعميل مستخدم بوابة بعد — وإلا بقيت المسوّدة عالقة أبدًا.
        'draft' => [['submit', 'إرسال للاعتماد', 'primary', 'none']],
        'changes_requested' => [['submit', 'إعادة الإرسال للاعتماد', 'primary', 'none']],
        'archived' => [],
    ];

    /** بنود جاهزية الاعتماد — حقول فعلية على العلامة، حرِجة تمنع الاعتماد المطمئن. */
    private function checklist(Brand $b, int $socialCount): array
    {
        $items = [
            ['key' => 'name', 'label' => 'اسم العلامة', 'present' => filled($b->name), 'critical' => true],
            ['key' => 'client', 'label' => 'مِلْكية العميل', 'present' => $b->client_id !== null || $b->isSelfOwned(), 'critical' => true],
            ['key' => 'sector', 'label' => 'القطاع', 'present' => filled($b->sector), 'critical' => true],
            ['key' => 'description', 'label' => 'وصف العلامة', 'present' => filled($b->description), 'critical' => true],
            ['key' => 'logo', 'label' => 'الشعار', 'present' => filled($b->logo_path), 'critical' => false],
            ['key' => 'website', 'label' => 'الموقع/النطاق', 'present' => filled($b->website) || filled($b->website_domain), 'critical' => false],
            ['key' => 'cr', 'label' => 'السجل التجاري', 'present' => filled($b->commercial_registration), 'critical' => false],
            ['key' => 'contact', 'label' => 'بيانات التواصل', 'present' => filled($b->contact_information), 'critical' => false],
            ['key' => 'guidelines', 'label' => 'إرشادات العلامة', 'present' => filled($b->brand_guidelines_path) || filled($b->visual_guidelines), 'critical' => false],
            ['key' => 'voice', 'label' => 'نبرة الصوت والجمهور', 'present' => filled($b->tone_of_voice) || filled($b->target_audience), 'critical' => false],
            ['key' => 'accounts', 'label' => 'حساب اجتماعي واحد على الأقل', 'present' => $socialCount > 0, 'critical' => false],
        ];
        $present = collect($items)->where('present', true)->count();
        $criticalMissing = collect($items)->where('critical', true)->where('present', false)->count();

        return [
            'items' => $items,
            'completeness' => (int) round($present / max(1, count($items)) * 100),
            'ready' => $criticalMissing === 0,
            'criticalMissing' => $criticalMissing,
        ];
    }

    public function show(Request $r, Brand $brand): Response
    {
        $this->authorize('view', $brand);
        $b = $brand->load('client', 'statusHistory', 'decisions', 'socialAccounts', 'versions');

        // شخصية العلامة التشغيلية: حملاتها ومحتواها المرتبط (بيانات فعلية)
        $brandCampaigns = \App\Domain\Campaigns\Models\Campaign::where('brand_id', $b->id)
            ->withCount('deliverables')->latest()->get();
        $brandContent = \App\Domain\Content\Models\ContentItem::whereIn('campaign_id', $brandCampaigns->pluck('id'))
            ->with('creator')->latest()->limit(30)->get();
        $activeCampaigns = $brandCampaigns->whereNotIn('status', ['draft', 'completed', 'cancelled'])->count();
        $awaitingContent = $brandContent->whereIn('status', ['agency_review', 'client_review'])->count();
        $canReview = $r->user()->can('update', $b);
        // التعليق إجراء هدّام ببوابة أعلى — لا يُعرض لمن لا يملكه
        $canSuspend = $r->user()->can('delete', $b);
        $actorNames = User::whereIn('id', $b->statusHistory->pluck('actor_id')->merge($b->decisions->pluck('reviewer_id'))->filter()->unique())->pluck('name', 'id');
        $st = fn ($s) => __('statuses.' . $s);
        $tone = fn ($s) => __('statuses.tone.' . $s);

        return Inertia::render('Brands/Show', [
            'brand' => [
                'id' => $b->id, 'name' => $b->name, 'client' => $b->client?->display_name, 'clientId' => $b->client_id,
                'sector' => $b->sector, 'website' => $b->website, 'description' => $b->description,
                'toneOfVoice' => $b->tone_of_voice, 'targetAudience' => $b->target_audience,
                'preferredLanguage' => $b->preferred_language, 'visualGuidelines' => $b->visual_guidelines,
                'prohibitedTopics' => $b->prohibited_topics ?? [], 'requiredMessages' => $b->required_messages ?? [],
                'status' => $b->status, 'statusLabel' => $st($b->status), 'statusTone' => $tone($b->status),
                'version' => (int) $b->current_version, 'submittedAt' => $b->submitted_at?->format('Y-m-d H:i'),
                'reviewedAt' => $b->reviewed_at?->format('Y-m-d H:i'), 'changesReason' => $b->changes_reason,
            ],
            'canReview' => $canReview,
            'actions' => collect($canReview ? (self::ACTIONS[$b->status] ?? []) : [])
                ->reject(fn (array $a) => $a[0] === 'suspend' && ! $canSuspend)->values(),
            // جاهزية الاعتماد — بنود فعلية تُعلِم قرار المراجِع بدل اعتماد على العمياء
            'checklist' => $this->checklist($b, $b->socialAccounts->count()),
            'metrics' => [
                'campaigns' => $brandCampaigns->count(),
                'activeCampaigns' => $activeCampaigns,
                'content' => $brandContent->count(),
                'awaitingContent' => $awaitingContent,
                'budgetMinor' => (int) $brandCampaigns->sum('budget_minor'),
            ],
            'campaigns' => $brandCampaigns->map(function ($c) use ($st, $tone, $brandContent) {
                $cc = $brandContent->where('campaign_id', $c->id);
                $pub = $cc->where('status', 'published')->count();
                return [
                    'id' => $c->id, 'name' => $c->name, 'deliverables' => (int) $c->deliverables_count,
                    'budgetMinor' => (int) $c->budget_minor,
                    'content' => $cc->count(), 'published' => $pub,
                    'progress' => $cc->count() ? (int) round($pub / max(1, $cc->count()) * 100) : 0,
                    'startDate' => $c->start_date?->format('Y-m-d'), 'endDate' => $c->end_date?->format('Y-m-d'),
                    'status' => $c->status, 'statusLabel' => $st($c->status), 'statusTone' => $tone($c->status),
                ];
            })->values(),
            'content' => $brandContent->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title, 'creator' => $c->creator?->display_name, 'platform' => $c->platform,
                'mediaUrl' => $c->media_url, 'version' => (int) $c->version, 'type' => $c->type,
                'publishedAt' => $c->published_at?->format('Y-m-d'),
                'needsAction' => in_array($c->status, ['agency_review', 'client_review', 'changes_requested'], true),
                'status' => $c->status, 'statusLabel' => $st($c->status), 'statusTone' => $tone($c->status),
            ])->values(),
            'socialAccounts' => $b->socialAccounts->map(fn ($s) => ['platform' => $s->platform, 'handle' => $s->handle, 'url' => $s->url])->values(),
            'decisions' => $b->decisions->sortByDesc('id')->values()->map(fn ($d) => [
                'decision' => $d->decision, 'note' => $d->note, 'version' => (int) $d->version,
                'by' => $actorNames[$d->reviewer_id] ?? '—', 'at' => $d->created_at?->format('Y-m-d H:i'),
            ]),
            'history' => $b->statusHistory->sortByDesc('id')->values()->map(fn ($h) => [
                'from' => $h->from_status ? $st($h->from_status) : '—', 'to' => $st($h->to_status),
                'by' => $actorNames[$h->actor_id] ?? '—', 'reason' => $h->reason, 'at' => $h->occurred_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    /**
     * إجراءات اعتماد العلامة.
     *
     * التعليق يتطلّب صلاحية الحذف لا التحديث (كما في مسار المراجعة السابق) —
     * إجراء هدّام لا يُمنح لكل من يملك التحرير.
     * الاعتماد وطلب التعديل يُخطران أعضاء بوابة العميل: القرار بلا إبلاغ
     * يترك العميل ينتظر بلا سبب معروف.
     */
    public function action(Request $r, Brand $brand, string $action, BrandWorkflowService $wf, ClientNotifier $notifier): RedirectResponse
    {
        $this->authorize($action === 'suspend' ? 'delete' : 'update', $brand);
        $client = $brand->client; // يُلتقط قبل الخدمة لأنها تعيد ضبط سياق المستأجر
        $reason = $action === 'request-changes'
            ? $r->validate(['reason' => 'required|string|max:500'])['reason']
            : $r->input('reason');

        try {
            match ($action) {
                'submit' => $wf->submit($brand, $r->user()->id),
                'start' => $wf->startReview($brand, $r->user()->id),
                'approve' => $wf->approve($brand, $r->user()->id, $reason),
                'request-changes' => $wf->requestChanges($brand, $r->user()->id, $reason),
                'suspend' => $wf->suspend($brand, $r->user()->id, $reason),
                default => abort(404),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }

        if ($client && $action === 'approve') {
            $notifier->toClientMembers($client, 'brand.approved', 'brands', "اعتُمدت علامتك: {$brand->name}",
                'يمكنك الآن استخدام العلامة في الحملات.', "/client/brands/{$brand->id}", ['brand_id' => $brand->id], $brand);
        } elseif ($client && $action === 'request-changes') {
            $notifier->toClientMembers($client, 'brand.changes_requested', 'brands', "مطلوب تعديل على علامتك: {$brand->name}",
                $reason, "/client/brands/{$brand->id}", ['brand_id' => $brand->id], $brand);
        }

        return back()->with('ok', 'حُدّثت حالة العلامة.');
    }
}
