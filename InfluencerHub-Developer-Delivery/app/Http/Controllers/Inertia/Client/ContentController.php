<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\ContentWorkflowService;
use App\Domain\CRM\Support\ClientPortalAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * موافقات المحتوى — بوابة العميل (React/Inertia). يعيد استخدام ContentWorkflowService.
 * معزول على العميل النشِط؛ الاعتماد/طلب التعديل لأدوار العميل المخوّلة فقط.
 */
class ContentController extends Controller
{
    private const VISIBLE = ['client_review', 'approved', 'scheduled', 'published', 'changes_requested'];

    /** أدوار العميل المخوّلة لاعتماد المحتوى / طلب التعديل. */
    private function canReview(Request $r): bool
    {
        $role = $r->attributes->get('clientMembership')->role;
        return ClientPortalAbilities::can($role, ClientPortalAbilities::MANAGE_BRANDS)
            || in_array($role, ['client_content_reviewer', 'client_admin'], true);
    }

    public function index(Request $r): Response
    {
        $c = $r->attributes->get('activeClient');
        $items = ContentItem::with('creator', 'campaign')->where('client_id', $c->id)
            ->whereIn('status', self::VISIBLE)->latest()->paginate(15)
            ->through(fn (ContentItem $it) => $this->row($it));
        $awaiting = ContentItem::where('client_id', $c->id)->where('status', 'client_review')->count();

        return Inertia::render('ClientPortal/Content/Index', [
            'clientName' => $c->display_name,
            'items' => $items,
            'awaiting' => $awaiting,
            'canReview' => $this->canReview($r),
        ]);
    }

    public function show(Request $r, int $content): Response
    {
        $item = $this->contentOf($r, $content);
        $item->load('creator', 'campaign', 'statusHistory');
        $actorIds = $item->statusHistory->pluck('actor_id')->filter()->unique();
        $actors = User::whereIn('id', $actorIds)->pluck('name', 'id');
        $history = $item->statusHistory->sortByDesc('occurred_at')->take(12)->map(fn ($h) => [
            'from' => $h->from_status ? __("statuses.{$h->from_status}") : null,
            'to' => __("statuses.{$h->to_status}"),
            'tone' => __("statuses.tone.{$h->to_status}"),
            'actor' => $actors[$h->actor_id] ?? ($h->actor_type === 'creator' ? 'المبدع' : 'النظام'),
            'note' => $h->reason,
            'at' => $h->occurred_at?->format('Y-m-d H:i'),
        ])->values();

        return Inertia::render('ClientPortal/Content/Show', [
            'clientName' => $r->attributes->get('activeClient')->display_name,
            'item' => $this->row($item) + [
                'caption' => $item->caption,
                'mediaUrl' => $item->media_url,
                'version' => (int) $item->version,
                'scheduledAt' => $item->scheduled_at?->format('Y-m-d H:i'),
                'publishedAt' => $item->published_at?->format('Y-m-d H:i'),
            ],
            'history' => $history,
            'canReview' => $this->canReview($r),
            'isPending' => $item->status === 'client_review',
        ]);
    }

    public function approve(Request $r, int $content, ContentWorkflowService $wf)
    {
        $item = $this->contentOf($r, $content);
        abort_unless($this->canReview($r), 403);
        try { $wf->clientApprove($item, $r->user()->id, $r->input('note')); }
        catch (\RuntimeException $e) { return back()->withErrors(['content' => $e->getMessage()]); }
        return back()->with('ok', 'اعتُمد المحتوى.');
    }

    public function requestChanges(Request $r, int $content, ContentWorkflowService $wf)
    {
        $item = $this->contentOf($r, $content);
        abort_unless($this->canReview($r), 403);
        $data = $r->validate(['reason' => 'required|string|max:500']);
        try { $wf->requestChanges($item, $r->user()->id, 'client', $data['reason']); }
        catch (\RuntimeException $e) { return back()->withErrors(['content' => $e->getMessage()]); }
        return back()->with('ok', 'طُلب تعديل المحتوى.');
    }

    private function contentOf(Request $r, int $id): ContentItem
    {
        $c = $r->attributes->get('activeClient');
        $item = ContentItem::where('id', $id)->where('client_id', $c->id)->whereIn('status', self::VISIBLE)->first();
        abort_unless($item, 404);
        return $item;
    }

    private function row(ContentItem $it): array
    {
        return [
            'id' => $it->id, 'number' => $it->content_number, 'title' => $it->title,
            'type' => $it->type, 'platform' => $it->platform,
            'creator' => $it->creator?->display_name, 'campaign' => $it->campaign?->name,
            'status' => $it->status, 'statusLabel' => __("statuses.{$it->status}"),
            'statusTone' => __("statuses.tone.{$it->status}"),
            'mediaUrl' => $it->media_url, 'version' => (int) $it->version,
            'publishedAt' => $it->published_at?->format('Y-m-d'),
            'awaiting' => $it->status === 'client_review',
        ];
    }
}
