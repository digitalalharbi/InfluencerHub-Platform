<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Exports\DocumentArtifactService;
use App\Domain\Exports\ExportService;
use App\Domain\Exports\Models\ExportJob;
use App\Domain\Exports\TabularData;
use App\Domain\Nomination\Services\NominationMatchService;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ترشيح المؤثرين للحملة (React/Inertia) — إصدارات + ميزانية + مطابقة + قرار العميل.
 * يعيد استخدام ShortlistService بالكامل (لا منطق مكرّر). معزول بالمستأجر عبر Policy(view/update Campaign).
 */
class ShortlistController extends Controller
{
    public function __construct(private ShortlistService $svc) {}

    public function index(Request $r, Campaign $campaign, DocumentArtifactService $artifacts): Response
    {
        $this->authorize('view', $campaign);
        $campaign->load('deliverables', 'client', 'brand');
        $sl = $this->svc->getOrCreate($campaign, $r->user()->id);
        $version = $sl->currentVersion();
        $items = $version->items()->with('creator')->get();
        $addedIds = $items->pluck('creator_id')->all();

        $q = Creator::query()->where('status', 'active')->whereNotIn('id', $addedIds ?: [0]);
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('display_name', 'ilike', "%{$s}%")->orWhere('handle', 'ilike', "%{$s}%"));
        }
        if ($v = $r->query('platform')) {
            $q->where('primary_platform', $v);
        }
        // المُطابِق القانونيّ الموحّد — درجة + أسباب + تحذيرات صادقة (بيانات ناقصة/منصّة مختلفة).
        $matcher = app(NominationMatchService::class);
        $candidates = $q->latest()->limit(30)->get()->map(function ($cr) use ($campaign, $matcher) {
            $m = $matcher->score($campaign, $cr);

            return [
                'id' => $cr->id, 'name' => $cr->display_name, 'handle' => $cr->handle,
                'platform' => $cr->primary_platform, 'followers' => (int) $cr->followers_count,
                'feeMinor' => (int) ($cr->rate_per_post_minor ?? 0),
                'verified' => $cr->mowthooq_status === 'verified',
                'score' => $m['score'], 'reasons' => $m['reasons'], 'flags' => $m['flags'],
            ];
        })->sortByDesc('score')->values();

        $primary = $items->where('is_backup', false);
        $committed = (int) $primary->sum('proposed_fee_minor');
        $budget = (int) $campaign->budget_minor;
        $canEdit = $r->user()->can('update', $campaign) && $version->status === 'draft';

        return Inertia::render('Shortlist/Index', [
            'campaign' => [
                'id' => $campaign->id, 'name' => $campaign->name, 'number' => $campaign->campaign_number,
                'client' => $campaign->client?->display_name, 'brand' => $campaign->brand?->name,
                'budgetMinor' => $budget, 'committedMinor' => $committed,
            ],
            'version' => [
                'number' => (int) $version->version, 'status' => $version->status,
                'statusLabel' => $this->versionLabel($version->status),
                'statusTone' => $this->versionTone($version->status),
                'submittedAt' => $version->submitted_at?->format('Y-m-d'),
                'decidedAt' => $version->decided_at?->format('Y-m-d'),
            ],
            'items' => $items->map(fn (CampaignShortlistItem $it) => [
                'id' => $it->id, 'creator' => $it->creator?->display_name ?? '—',
                'handle' => $it->creator?->handle, 'platform' => $it->creator?->primary_platform,
                'isBackup' => (bool) $it->is_backup, 'feeMinor' => (int) $it->proposed_fee_minor,
                'score' => (int) $it->match_score, 'reasons' => $it->reasons ?? [],
                'decision' => $it->client_decision ?? 'pending',
                'decisionLabel' => $this->decisionLabel($it->client_decision),
                'decisionTone' => $this->decisionTone($it->client_decision),
            ])->values(),
            'candidates' => $candidates,
            // لماذا لا مرشّحين: «لا نتائج» صحيحة وغير مفيدة حين يكون السبب أن
            // المبدعين موجودون لكنهم غير نشطين — وهي الحالة الشائعة بعد إضافتهم.
            'candidatePool' => [
                'active' => Creator::where('status', 'active')->count(),
                'inactive' => Creator::where('status', '!=', 'active')->count(),
            ],
            // تاريخ الإصدارات — كل إصدار يحفظ قراره وعدد مرشّحيه (لا تكرار للبيانات)
            'versions' => $sl->versions()->withCount('items')->orderByDesc('version')->get()->map(fn ($v) => [
                'number' => (int) $v->version, 'status' => $v->status,
                'statusLabel' => $this->versionLabel($v->status), 'statusTone' => $this->versionTone($v->status),
                'items' => (int) $v->items_count, 'isCurrent' => (int) $v->version === (int) $sl->current_version,
                'submittedAt' => $v->submitted_at?->format('Y-m-d'), 'decidedAt' => $v->decided_at?->format('Y-m-d'),
            ])->values(),
            'filters' => ['q' => $r->query('q'), 'platform' => $r->query('platform')],
            'canEdit' => $canEdit,
            'budgetPct' => ($budget > 0) ? (int) round(min(100, $committed / $budget * 100)) : 0,
            'overBudget' => $budget > 0 && $committed > $budget,
            // مستند المقترح — معاينة/تنزيل نفس الأثر + كشف القِدَم (نفس معمارية الحملة)
            'documents' => ['proposal' => $this->proposalMeta($campaign, $r, $artifacts)],
        ]);
    }

    /** بنود الإصدار الحالي (مع المبدع) — مصدر واحد للعرض والتصدير. */
    private function currentItems(Campaign $campaign, Request $r)
    {
        $version = $this->svc->getOrCreate($campaign, $r->user()->id)->currentVersion();

        return [$version, $version->items()->with('creator')->orderByDesc('is_backup')->get()];
    }

    /**
     * تصدير الترشيحات (داخلي — XLSX/CSV): يتضمّن درجة المطابقة وأسبابها والتكلفة
     * والقرار. للاستخدام الداخلي فقط. Policy(view). مُدقَّق.
     */
    public function export(Request $r, Campaign $campaign, ExportService $svc): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $campaign);
        [$version, $items] = $this->currentItems($campaign, $r);

        $data = new TabularData(
            title: 'ترشيحات حملة '.$campaign->campaign_number,
            columns: [
                'creator' => 'المبدع', 'handle' => 'الحساب', 'platform' => 'المنصّة',
                'followers' => 'المتابعون', 'kind' => 'النوع', 'fee' => 'السعر المقترح',
                'score' => 'درجة المطابقة', 'decision' => 'قرار العميل',
            ],
            rows: $items->map(fn (CampaignShortlistItem $it) => [
                'creator' => $it->creator?->display_name ?? '—',
                'handle' => $it->creator?->handle ?? '—',
                'platform' => $it->creator?->primary_platform ?? '—',
                'followers' => number_format((int) ($it->creator?->followers_count ?? 0)),
                'kind' => $it->is_backup ? 'احتياطي' : 'أساسي',
                'fee' => number_format(($it->proposed_fee_minor ?? 0) / 100, 2).' SAR',
                'score' => (int) $it->match_score,
                'decision' => $this->decisionLabel($it->client_decision),
            ]),
            meta: array_filter(['الحملة' => $campaign->name, 'الإصدار' => (int) $version->version]),
            workspace: Organization::find(TenantContext::organizationId())?->name,
            generatedAt: now()->format('Y-m-d H:i'),
        );

        return $svc->download($data, (string) $r->query('format', 'xlsx'), 'shortlist-'.$campaign->campaign_number,
            'shortlist', $items->count(), TenantContext::tenantId(), $r->user()->id);
    }

    private const PROPOSAL_TYPE = 'shortlist_client_proposal';

    private const PROPOSAL_TEMPLATE = 'v1';

    /** بيانات المقترح الآمنة للعميل — بلا score/reasons/تكلفة مبدع/تواصل. تُحدّد البصمة (بلا وقت). */
    private function proposalData(Campaign $campaign, Request $r): array
    {
        $campaign->loadMissing('client', 'brand');
        [$version, $items] = $this->currentItems($campaign, $r);

        return [
            'workspace' => Organization::find(TenantContext::organizationId())?->name ?? 'الوكالة',
            'campaign' => $campaign->name,
            'number' => $campaign->campaign_number,
            'client' => $campaign->client?->display_name ?? '—',
            'brand' => $campaign->brand?->name,
            'versionNo' => (int) $version->version,
            'items' => $items->map(fn (CampaignShortlistItem $it) => [
                'creator' => $it->creator?->display_name ?? '—',
                'handle' => $it->creator?->handle,
                'platform' => $it->creator?->primary_platform ?? '—',
                'followers' => number_format((int) ($it->creator?->followers_count ?? 0)),
                'backup' => (bool) $it->is_backup,
                'fee' => number_format(($it->proposed_fee_minor ?? 0) / 100, 0).' ر.س',
                'decision' => $this->decisionLabel($it->client_decision),
            ])->values()->all(),
        ];
    }

    private function proposalArtifact(Campaign $campaign, Request $r, DocumentArtifactService $svc, bool $regenerate = false): ExportJob
    {
        $data = $this->proposalData($campaign, $r);
        $render = function () use ($campaign, $data, $svc, $r) {
            AuditLogger::log('export.generated', $campaign, [
                'type' => self::PROPOSAL_TYPE, 'format' => 'pdf', 'campaign' => $campaign->campaign_number,
            ], TenantContext::tenantId(), $r->user()?->id);

            return $svc->pdfFromView('exports.shortlist-proposal', $data + ['generatedAt' => now()->format('Y-m-d H:i')]);
        };
        if (! $regenerate) {
            $latest = $svc->latest(self::PROPOSAL_TYPE, $campaign);
            if ($latest) {
                return $latest;
            }
        }

        return $svc->current(self::PROPOSAL_TYPE, $campaign, 'pdf', self::PROPOSAL_TEMPLATE, $data,
            'مقترح ترشيحات '.$campaign->campaign_number, $render, $r->user()?->id);
    }

    private function streamProposal(ExportJob $a, DocumentArtifactService $svc, string $disposition): \Symfony\Component\HttpFoundation\Response
    {
        $bytes = $svc->bytes($a);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.Brand::documentFilename($a->title).'"',
            'Content-Length' => (string) strlen($bytes), 'X-Artifact-Checksum' => $a->checksum,
        ]);
    }

    /** معاينة inline للمقترح — نفس أثر التنزيل. */
    public function proposalPreview(Request $r, Campaign $campaign, DocumentArtifactService $svc): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $campaign);

        return $this->streamProposal($this->proposalArtifact($campaign, $r, $svc), $svc, 'inline');
    }

    /** تنزيل المقترح — نفس بايتات المعاينة (attachment). */
    public function proposalDownload(Request $r, Campaign $campaign, DocumentArtifactService $svc): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $campaign);

        return $this->streamProposal($this->proposalArtifact($campaign, $r, $svc), $svc, 'attachment');
    }

    /** إنشاء نسخة محدّثة صريحة من المقترح. */
    public function proposalRegenerate(Request $r, Campaign $campaign, DocumentArtifactService $svc): RedirectResponse
    {
        $this->authorize('view', $campaign);
        $this->proposalArtifact($campaign, $r, $svc, regenerate: true);

        return back()->with('ok', 'أُنشئت نسخة محدّثة من المقترح.');
    }

    /** توافق خلفي: الرابط القديم يُنزّل الأثر ذاته. */
    public function exportClientPdf(Request $r, Campaign $campaign, DocumentArtifactService $svc): \Symfony\Component\HttpFoundation\Response
    {
        return $this->proposalDownload($r, $campaign, $svc);
    }

    /** بيانات مستند المقترح لصفحة الترشيح (معاينة/تنزيل نفس الأثر + كشف القِدَم). مسارات نسبية للتركيب. */
    public function proposalMeta(Campaign $campaign, Request $r, DocumentArtifactService $svc): array
    {
        $latest = $svc->latest(self::PROPOSAL_TYPE, $campaign);
        $currentFp = $svc->fingerprint($this->proposalData($campaign, $r), self::PROPOSAL_TEMPLATE, 'pdf');
        $base = "/campaigns/{$campaign->id}/shortlist/proposal";

        return [
            'title' => 'مقترح المؤثرين (آمن للعميل)',
            'hasArtifact' => (bool) $latest,
            'generatedAt' => $latest?->created_at?->format('Y-m-d H:i'),
            'stale' => $svc->isStale($latest, $currentFp),
            'previewUrl' => "{$base}/preview", 'downloadUrl' => "{$base}/download", 'regenerateUrl' => "{$base}/regenerate",
        ];
    }

    public function add(Request $r, Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $data = $r->validate(['creator_id' => 'required|integer', 'backup' => 'nullable|boolean']);
        $version = $this->svc->getOrCreate($campaign, $r->user()->id)->currentVersion();
        if ($version->status !== 'draft') {
            return back()->withErrors(['shortlist' => 'لا يمكن تعديل قائمة مُرسَلة — أنشئ إصدارًا جديدًا أولًا.']);
        }
        $creator = Creator::findOrFail($data['creator_id']);
        $this->svc->addCreator($version, $creator, (bool) ($data['backup'] ?? false));

        return back()->with('ok', 'أُضيف المؤثر إلى القائمة.');
    }

    public function remove(Request $r, Campaign $campaign, CampaignShortlistItem $item)
    {
        $this->authorize('update', $campaign);
        abort_unless($item->version->shortlist->campaign_id === $campaign->id, 404);
        if ($item->version->status !== 'draft') {
            return back()->withErrors(['shortlist' => 'لا يمكن تعديل قائمة مُرسَلة — أنشئ إصدارًا جديدًا أولًا.']);
        }
        $this->svc->removeItem($item);

        return back()->with('ok', 'أُزيل من القائمة.');
    }

    public function submit(Request $r, Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $sl = $this->svc->getOrCreate($campaign, $r->user()->id);
        $version = $sl->currentVersion();
        if ($version->status !== 'draft') {
            return back()->withErrors(['shortlist' => 'هذا الإصدار مُرسَل بالفعل.']);
        }
        if ($version->items()->count() === 0) {
            return back()->withErrors(['shortlist' => 'أضِف مؤثرًا واحدًا على الأقل قبل الإرسال.']);
        }

        // بوابة العميل تُخفي الحملات المسودّة، فإرسال ترشيح على حملة مسودة
        // يصل إلى من لا يراه: القائمة تُصبح «مُرسلة» والعميل لا يجد ما يقرّره.
        if ($campaign->status === 'draft') {
            return back()->withErrors(['shortlist' => 'الحملة مسودة ولا تظهر للعميل بعد. انقلها إلى التخطيط أوّلًا ثم أرسل الترشيح.']);
        }
        try {
            $this->svc->submit($sl);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['shortlist' => $e->getMessage()]);
        }

        return back()->with('ok', 'أُرسلت القائمة لاعتماد العميل.');
    }

    public function revise(Request $r, Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $this->svc->newRevision($this->svc->getOrCreate($campaign, $r->user()->id));

        return back()->with('ok', 'أُنشئ إصدار جديد للترشيح.');
    }

    private function versionLabel(string $s): string
    {
        return ['draft' => 'مسودة', 'submitted' => 'بانتظار العميل', 'approved' => 'مُعتمَد',
            'partially_approved' => 'اعتماد جزئي', 'changes_requested' => 'مطلوب بديل', 'rejected' => 'مرفوض'][$s] ?? $s;
    }

    private function versionTone(string $s): string
    {
        return ['draft' => 'draft', 'submitted' => 'submitted', 'approved' => 'approved',
            'partially_approved' => 'under_review', 'changes_requested' => 'under_review', 'rejected' => 'rejected'][$s] ?? 'draft';
    }

    private function decisionLabel(?string $s): string
    {
        return ['approved' => 'اعتمده العميل', 'rejected' => 'رفضه العميل', 'needs_alternative' => 'طلب بديلًا', 'pending' => 'بانتظار القرار'][$s ?? 'pending'] ?? 'بانتظار القرار';
    }

    private function decisionTone(?string $s): string
    {
        return ['approved' => 'approved', 'rejected' => 'rejected', 'needs_alternative' => 'under_review', 'pending' => 'draft'][$s ?? 'pending'] ?? 'draft';
    }
}
