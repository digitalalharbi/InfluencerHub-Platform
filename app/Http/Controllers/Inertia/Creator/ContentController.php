<?php

namespace App\Http\Controllers\Inertia\Creator;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\ContentWorkflowService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * محتوى المبدع (React/Inertia) — إنشاء مسودة/تعديل/تقديم للمراجعة عبر ContentWorkflowService.
 * معزول على المبدع النشِط. الرابط يدوي (لا رفع ملفات) — صادق.
 */
class ContentController extends Controller
{
    private const TYPES = ['post' => 'منشور', 'story' => 'ستوري', 'reel' => 'ريلز', 'video' => 'فيديو', 'ugc' => 'UGC'];

    public function index(Request $r): Response
    {
        $c = $r->attributes->get('creator');
        $items = ContentItem::with('campaign')->where('creator_id', $c->id)->latest()->paginate(15)
            ->through(fn (ContentItem $it) => $this->row($it));
        $todo = ContentItem::where('creator_id', $c->id)->whereIn('status', ['draft', 'changes_requested'])->count();
        $collabs = Collaboration::where('creator_id', $c->id)->where('status', 'in_progress')->get(['id', 'title', 'collaboration_number'])
            ->map(fn ($cl) => ['id' => $cl->id, 'label' => $cl->title ?: $cl->collaboration_number])->values();
        // تعاون مقبول لم يبدأ بعد: لا يظهر في القائمة، وغيابه بلا تفسير يجعل
        // المبدع يرى نموذجًا بلا حقل ربط ولا يعرف أن عليه «بدء العمل» أوّلًا.
        $notStarted = Collaboration::where('creator_id', $c->id)->where('status', 'accepted')->count();

        return Inertia::render('CreatorPortal/Content/Index', [
            'creatorName' => $c->display_name,
            'items' => $items,
            'todo' => $todo,
            'collabs' => $collabs,
            'notStartedCollabs' => $notStarted,
            'types' => collect(self::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all(),
        ]);
    }

    public function show(Request $r, int $content): Response
    {
        $item = $this->contentOf($r, $content);
        $item->load('campaign', 'statusHistory');
        $actorIds = $item->statusHistory->pluck('actor_id')->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->pluck('name', 'id');
        $history = $item->statusHistory->sortByDesc('occurred_at')->take(12)->map(fn ($h) => [
            'to' => __("statuses.{$h->to_status}"), 'tone' => __("statuses.tone.{$h->to_status}"),
            'actor' => $actors[$h->actor_id] ?? 'النظام', 'note' => $h->reason,
            'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();

        return Inertia::render('CreatorPortal/Content/Show', [
            'creatorName' => $r->attributes->get('creator')->display_name,
            'item' => $this->row($item) + [
                'caption' => $item->caption, 'mediaUrl' => $item->media_url,
                'campaign' => $item->campaign?->name, 'version' => (int) $item->version,
            ],
            'history' => $history,
            'editable' => in_array($item->status, ['draft', 'changes_requested'], true),
        ]);
    }

    public function store(Request $r, ContentWorkflowService $wf)
    {
        $c = $r->attributes->get('creator');
        $data = $r->validate([
            'title' => 'required|string|max:160',
            'type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'platform' => 'nullable|string|max:20',
            'caption' => 'nullable|string|max:4000',
            'media_url' => 'nullable|url|max:500',
            'collaboration_id' => 'nullable|integer',
        ]);
        $link = [];
        if (! empty($data['collaboration_id'])) {
            $col = Collaboration::where('id', $data['collaboration_id'])->where('creator_id', $c->id)->first();
            abort_unless($col, 422);
            $link = ['collaboration_id' => $col->id, 'campaign_id' => $col->campaign_id, 'deliverable_id' => $col->deliverable_id, 'client_id' => $col->client_id];
        }
        unset($data['collaboration_id']);
        $wf->create($c->tenant_id, $data + $link + ['creator_id' => $c->id], $r->user()->id, 'creator');
        return redirect(MountPrefix::path($r, '/content'))->with('ok', 'أُنشئت مسودة المحتوى.');
    }

    public function update(Request $r, int $content, ContentWorkflowService $wf)
    {
        $item = $this->contentOf($r, $content);
        $data = $r->validate(['title' => 'required|string|max:160', 'caption' => 'nullable|string|max:4000',
            'media_url' => 'nullable|url|max:500', 'platform' => 'nullable|string|max:20']);
        try { $wf->updateDraft($item, $data, $r->user()->id); }
        catch (\RuntimeException $e) { return back()->withErrors(['content' => $e->getMessage()]); }
        return back()->with('ok', 'حُفظ المحتوى.');
    }

    public function submit(Request $r, int $content, ContentWorkflowService $wf)
    {
        $item = $this->contentOf($r, $content);
        try { $wf->submit($item, $r->user()->id); }
        catch (\RuntimeException $e) { return back()->withErrors(['content' => $e->getMessage()]); }
        return back()->with('ok', 'قُدّم المحتوى للمراجعة.');
    }

    private function contentOf(Request $r, int $id): ContentItem
    {
        $c = $r->attributes->get('creator');
        $item = ContentItem::where('id', $id)->where('creator_id', $c->id)->first();
        abort_unless($item, 404);
        return $item;
    }

    private function row(ContentItem $it): array
    {
        return [
            'id' => $it->id, 'number' => $it->content_number, 'title' => $it->title,
            'type' => $it->type, 'typeLabel' => self::TYPES[$it->type] ?? $it->type, 'platform' => $it->platform,
            'campaignName' => $it->campaign?->name,
            'status' => $it->status, 'statusLabel' => __("statuses.{$it->status}"), 'statusTone' => __("statuses.tone.{$it->status}"),
        ];
    }
}
