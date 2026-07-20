<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Campaigns\Models\{Campaign, CampaignShortlistItem};
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Creators\Models\Creator;
use App\Http\Controllers\Controller;
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

    public function index(Request $r, Campaign $campaign): Response
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
        if ($v = $r->query('platform')) $q->where('primary_platform', $v);
        $candidates = $q->latest()->limit(30)->get()->map(function ($cr) use ($campaign) {
            $m = $this->svc->matchScore($campaign, $cr);
            return [
                'id' => $cr->id, 'name' => $cr->display_name, 'handle' => $cr->handle,
                'platform' => $cr->primary_platform, 'followers' => (int) $cr->followers_count,
                'feeMinor' => (int) ($cr->rate_per_post_minor ?? 0),
                'verified' => $cr->mowthooq_status === 'verified',
                'score' => $m['score'], 'reasons' => $m['reasons'],
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
        ]);
    }

    public function add(Request $r, Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $data = $r->validate(['creator_id' => 'required|integer', 'backup' => 'nullable|boolean']);
        $version = $this->svc->getOrCreate($campaign, $r->user()->id)->currentVersion();
        if ($version->status !== 'draft') return back()->withErrors(['shortlist' => 'لا يمكن تعديل قائمة مُرسَلة — أنشئ إصدارًا جديدًا أولًا.']);
        $creator = Creator::findOrFail($data['creator_id']);
        $this->svc->addCreator($version, $creator, (bool) ($data['backup'] ?? false));
        return back()->with('ok', 'أُضيف المؤثر إلى القائمة.');
    }

    public function remove(Request $r, Campaign $campaign, CampaignShortlistItem $item)
    {
        $this->authorize('update', $campaign);
        abort_unless($item->version->shortlist->campaign_id === $campaign->id, 404);
        if ($item->version->status !== 'draft') return back()->withErrors(['shortlist' => 'لا يمكن تعديل قائمة مُرسَلة — أنشئ إصدارًا جديدًا أولًا.']);
        $this->svc->removeItem($item);
        return back()->with('ok', 'أُزيل من القائمة.');
    }

    public function submit(Request $r, Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $sl = $this->svc->getOrCreate($campaign, $r->user()->id);
        $version = $sl->currentVersion();
        if ($version->status !== 'draft') return back()->withErrors(['shortlist' => 'هذا الإصدار مُرسَل بالفعل.']);
        if ($version->items()->count() === 0) return back()->withErrors(['shortlist' => 'أضِف مؤثرًا واحدًا على الأقل قبل الإرسال.']);

        // بوابة العميل تُخفي الحملات المسودّة، فإرسال ترشيح على حملة مسودة
        // يصل إلى من لا يراه: القائمة تُصبح «مُرسلة» والعميل لا يجد ما يقرّره.
        if ($campaign->status === 'draft') {
            return back()->withErrors(['shortlist' =>
                'الحملة مسودة ولا تظهر للعميل بعد. انقلها إلى التخطيط أوّلًا ثم أرسل الترشيح.']);
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
            'partially_approved' => 'اعتماد جزئي', 'rejected' => 'مرفوض'][$s] ?? $s;
    }

    private function versionTone(string $s): string
    {
        return ['draft' => 'draft', 'submitted' => 'submitted', 'approved' => 'approved',
            'partially_approved' => 'under_review', 'rejected' => 'rejected'][$s] ?? 'draft';
    }

    private function decisionLabel(?string $s): string
    {
        return ['approved' => 'اعتمده العميل', 'rejected' => 'رفضه العميل', 'pending' => 'بانتظار القرار'][$s ?? 'pending'] ?? 'بانتظار القرار';
    }

    private function decisionTone(?string $s): string
    {
        return ['approved' => 'approved', 'rejected' => 'rejected', 'pending' => 'draft'][$s ?? 'pending'] ?? 'draft';
    }
}
