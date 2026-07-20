<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\ContentWorkflowService;
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
        'scheduled' => [['publish', 'نشر الآن', 'primary', 'none']],
        'draft' => [], 'changes_requested' => [], 'client_review' => [], 'published' => [], 'rejected' => [],
    ];
    private const DECISION_LABEL = ['approved' => 'موافقة', 'changes_requested' => 'طلب تعديل', 'rejected' => 'رفض'];

    public function show(Request $r, ContentItem $content): Response
    {
        $this->authorize('view', $content);
        $c = $content->load('creator', 'client', 'campaign', 'approvals');
        $canReview = $r->user()->can('review', $c);
        $st = fn ($s) => __('statuses.' . $s);

        return Inertia::render('Content/Show', [
            'content' => [
                'id' => $c->id, 'number' => $c->content_number, 'title' => $c->title,
                'type' => self::TYPE_LABEL[$c->type] ?? $c->type, 'platform' => $c->platform,
                'caption' => $c->caption, 'mediaUrl' => $c->media_url, 'version' => (int) $c->version,
                'creator' => $c->creator?->display_name, 'client' => $c->client?->display_name,
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
        ]);
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
