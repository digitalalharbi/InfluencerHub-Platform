<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\ContentWorkflowService;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تفاصيل المحتوى + موافقاته (React/Inertia). الإجراءات تعيد استخدام ContentWorkflowService.
 * view للعرض، review للإجراءات. معزول بالمستأجر، IDOR-safe.
 */
class ContentDetailController extends Controller
{
    private const TYPE_LABEL = ['post' => 'منشور', 'story' => 'ستوري', 'reel' => 'ريل', 'video' => 'فيديو', 'ugc' => 'UGC'];

    /** [action, label, tone, input(none|reason|schedule)]. */
    private const ACTIONS = [
        'submitted' => [['start-review', 'بدء المراجعة', 'primary', 'none']],
        'agency_review' => [['send-to-client', 'إرسال للعميل', 'primary', 'none'], ['request-changes', 'طلب تعديل', 'ghost', 'reason'], ['reject', 'رفض', 'danger', 'reason']],
        'approved' => [['publish', 'نشر', 'primary', 'none'], ['schedule', 'جدولة النشر', 'ghost', 'schedule']],
        'scheduled' => [['publish', 'نشر الآن', 'primary', 'none'], ['reschedule', 'تغيير الموعد', 'ghost', 'schedule']],
        'draft' => [], 'changes_requested' => [], 'client_review' => [], 'published' => [], 'rejected' => [],
    ];
    private const DECISION_LABEL = ['approved' => 'موافقة', 'changes_requested' => 'طلب تعديل', 'rejected' => 'رفض'];

    /** أسماء المراحل/الحالات في السجل — لا مفاتيح خام في الواجهة. */
    private const ACTOR_LABEL = ['agency' => 'الوكالة', 'client' => 'العميل', 'creator' => 'المبدع'];

    public function show(Request $r, ContentItem $content): Response
    {
        $this->authorize('view', $content);
        $c = $content->load('creator', 'client', 'campaign', 'approvals', 'statusHistory');
        $canReview = $r->user()->can('review', $c);
        $st = fn ($s) => __('statuses.' . $s);

        return Inertia::render('Content/Show', [
            'content' => [
                'id' => $c->id, 'number' => $c->content_number, 'title' => $c->title,
                'type' => self::TYPE_LABEL[$c->type] ?? $c->type, 'platform' => $c->platform,
                'caption' => $c->caption, 'mediaUrl' => $c->media_url, 'version' => (int) $c->version,
                'creator' => $c->creator?->display_name, 'creatorId' => $c->creator_id,
                'client' => $c->client?->display_name, 'clientId' => $c->client_id,
                'campaign' => $c->campaign?->name, 'campaignId' => $c->campaign_id,
                'status' => $c->status, 'statusLabel' => $st($c->status), 'statusTone' => __('statuses.tone.' . $c->status),
                'scheduledAt' => $c->scheduled_at?->format('Y-m-d H:i'), 'publishedAt' => $c->published_at?->format('Y-m-d H:i'),
                // إثبات النشر ونتائجه — الفاتورة والتقرير يُبنيان عليهما
                'publishedUrl' => $c->published_url, 'proofNote' => $c->proof_note,
                'proofAt' => $c->proof_at?->format('Y-m-d H:i'),
                'results' => $c->results_at ? [
                    'reach' => $c->reach, 'impressions' => $c->impressions,
                    'engagements' => $c->engagements, 'clicks' => $c->clicks,
                    // المصدر يُعرَض دائمًا: رقم بلا مصدر ادّعاء
                    'source' => $c->results_source === 'platform' ? 'من المنصّة' : 'إدخال يدوي',
                    'at' => $c->results_at->format('Y-m-d H:i'),
                ] : null,
            ],
            'canReview' => $canReview,
            'actions' => $canReview ? (self::ACTIONS[$c->status] ?? []) : [],
            // الانتظار حالة مشروعة لكنها تُعلَن: قائمة إجراءات فارغة
            // بلا تفسير تبدو عطلًا أو صلاحية ناقصة.
            'waitingOn' => \App\Support\Workflow\WaitingOn::for('content', $c->status),
            'approvals' => $c->approvals->sortByDesc('id')->values()->map(fn ($a) => [
                'stage' => $a->stage === 'client' ? 'العميل' : 'الوكالة',
                'decision' => self::DECISION_LABEL[$a->decision] ?? $a->decision,
                'note' => $a->note, 'version' => (int) $a->content_version, 'at' => $a->created_at?->format('Y-m-d H:i'),
            ]),
            // سجل موحّد حقيقي: كل انتقال حالة + كل قرار مراجعة، بفاعله واسمه ودوره
            // ووقته وإصداره — من بيانات محفوظة فعلًا (content_status_history + content_approvals).
            'timeline' => $this->buildTimeline($c),
        ]);
    }

    /** يبني السجل الموحّد من تاريخ الحالات وقرارات المراجعة، بأسماء الفاعلين. */
    private function buildTimeline(ContentItem $c): array
    {
        $userIds = $c->statusHistory->pluck('actor_id')
            ->merge($c->approvals->pluck('reviewer_id'))
            ->filter()->unique()->values();
        $names = User::whereIn('id', $userIds)->pluck('name', 'id');
        $st = fn ($s) => __('statuses.' . $s);

        $entries = [];
        foreach ($c->statusHistory as $h) {
            $isReschedule = $h->from_status === 'scheduled' && $h->to_status === 'scheduled';
            $entries[] = [
                'kind' => 'status',
                'label' => $isReschedule ? 'إعادة جدولة' : ($h->from_status ? $st($h->from_status) . ' ← ' . $st($h->to_status) : $st($h->to_status)),
                'toStatus' => $h->to_status,
                'actor' => $names[$h->actor_id] ?? null,
                'role' => self::ACTOR_LABEL[$h->actor_type] ?? $h->actor_type,
                'note' => $h->reason,
                'version' => null,
                'at' => $h->occurred_at?->format('Y-m-d H:i'),
                'ts' => (int) ($h->occurred_at?->timestamp ?? 0),
            ];
        }
        foreach ($c->approvals as $a) {
            $entries[] = [
                'kind' => 'decision',
                'label' => (self::ACTOR_LABEL[$a->reviewer_type] ?? $a->stage) . ': ' . (self::DECISION_LABEL[$a->decision] ?? $a->decision),
                'toStatus' => null,
                'decision' => $a->decision,
                'actor' => $names[$a->reviewer_id] ?? null,
                'role' => self::ACTOR_LABEL[$a->reviewer_type] ?? $a->reviewer_type,
                'note' => $a->note,
                'version' => (int) $a->content_version,
                'at' => $a->created_at?->format('Y-m-d H:i'),
                'ts' => (int) ($a->created_at?->timestamp ?? 0),
            ];
        }
        // الأحدث أوّلًا؛ عند تساوي الوقت تُقدَّم القرارات (أدقّ) على انتقال الحالة
        usort($entries, fn ($x, $y) => $y['ts'] <=> $x['ts'] ?: ($y['kind'] === 'decision' ? 1 : -1));

        return array_map(fn ($e) => collect($e)->except('ts')->all(), $entries);
    }

    public function action(Request $r, ContentItem $content, string $action, ContentWorkflowService $wf): RedirectResponse
    {
        $this->authorize('review', $content);
        try {
            match ($action) {
                'start-review' => $wf->startAgencyReview($content, $r->user()->id),
                'send-to-client' => $wf->sendToClient($content, $r->user()->id, $r->input('note')),
                'request-changes' => $wf->requestChanges($content, $r->user()->id, 'agency', $r->validate(['reason' => 'required|string|max:500'])['reason']),
                'reject' => $wf->reject($content, $r->user()->id, 'agency', $r->validate(['reason' => 'required|string|max:500'])['reason']),
                'publish' => $wf->publish($content, $r->user()->id),
                'schedule' => $wf->schedule($content, $r->user()->id, new \DateTimeImmutable($r->validate(['scheduled_at' => 'required|date'])['scheduled_at'])),
                'reschedule' => $wf->reschedule($content, $r->user()->id, new \DateTimeImmutable($r->validate(['scheduled_at' => 'required|date'])['scheduled_at'])),
                'record-proof' => $wf->recordPublishProof(
                    $content,
                    $r->validate(['published_url' => 'required|url|max:500'])['published_url'],
                    $r->input('proof_note'),
                    $r->user()->id,
                ),
                'record-results' => $wf->recordResults($content, $r->validate([
                    'reach' => 'nullable|integer|min:0',
                    'impressions' => 'nullable|integer|min:0',
                    'engagements' => 'nullable|integer|min:0',
                    'clicks' => 'nullable|integer|min:0',
                ]), $r->user()->id),
                default => abort(404),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['wf' => $e->getMessage()]);
        }
        return back()->with('ok', 'حُدّثت حالة المحتوى.');
    }
}
