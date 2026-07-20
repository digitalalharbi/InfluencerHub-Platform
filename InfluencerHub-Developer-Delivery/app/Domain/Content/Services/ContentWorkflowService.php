<?php

namespace App\Domain\Content\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentStatusHistory;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * سير عمل المحتوى والموافقات (بالأحداث، متعدّد المراحل):
 * draft→submitted→agency_review→(الوكالة) changes_requested/client_review→(العميل) changes_requested/approved
 * →scheduled/published؛ ومسار الرفض. يميّز الفاعل (مبدع/وكالة/عميل).
 */
class ContentWorkflowService
{
    private const ALLOWED = [
        'draft' => ['submitted'],
        'changes_requested' => ['submitted'],
        'submitted' => ['agency_review'],
        'agency_review' => ['client_review', 'changes_requested', 'rejected'],
        'client_review' => ['approved', 'changes_requested', 'rejected'],
        'approved' => ['scheduled', 'published'],
        'scheduled' => ['published', 'approved'],
        'published' => [],
        'rejected' => [],
    ];

    public function __construct(private NotificationService $notifications) {}

    /** المبدع (أو الوكالة) ينشئ عنصر محتوى مسودة. */
    public function create(int $tenantId, array $data, int $actorId, string $actorType = 'creator'): ContentItem
    {
        return DB::transaction(function () use ($tenantId, $data, $actorId, $actorType) {
            return TenantContext::withTenant($tenantId, function () use ($actorId, $actorType, $data, $tenantId) {
                $item = ContentItem::create($data + [
                    'tenant_id' => $tenantId, 'status' => 'draft', 'version' => 1, 'created_by' => $actorId,
                    'content_number' => 'CN-'.$tenantId.'-'.(ContentItem::where('tenant_id', $tenantId)->count() + 1),
                ]);
                $this->recordStatus($item, null, 'draft', $actorId, $actorType, 'إنشاء المحتوى');
                AuditLogger::log('content.created', $item, [], $tenantId, $actorId);

                return $item;
            });
        });
    }

    /** المبدع يحدّث المحتوى (مسموح في draft/changes_requested فقط). */
    public function updateDraft(ContentItem $item, array $data, int $actorId): ContentItem
    {
        if (! in_array($item->status, ContentItem::CREATOR_EDITABLE, true)) {
            throw new RuntimeException('لا يمكن تعديل المحتوى في حالته الحالية.');
        }

        return TenantContext::withTenant($item->tenant_id, function () use ($actorId, $data, $item) {
            $item->update($data);
            AuditLogger::log('content.updated', $item, array_keys($data), $item->tenant_id, $actorId);

            return $item;
        });
    }

    /** المبدع يقدّم المحتوى للمراجعة (يزيد الإصدار عند إعادة التقديم). */
    public function submit(ContentItem $item, int $actorId): ContentItem
    {
        $r = $this->transition($item, 'submitted', $actorId, 'creator', null, function ($x) {
            if ($x->status === 'changes_requested') {
                $x->version = $x->version + 1;
            }
        });
        $this->notifyAgency($item, 'محتوى بانتظار مراجعتكم', $item->title);

        return $r;
    }

    /**
     * إثبات النشر: رابط المنشور الحيّ.
     *
     * لا يُقبل قبل النشر — إثبات لِما لم يقع بعد. ويُسجَّل من أثبته ومتى، لأنّه
     * المستند الذي تُبنى عليه الفاتورة والتقرير.
     */
    public function recordPublishProof(ContentItem $item, string $url, ?string $note, int $actorId): ContentItem
    {
        if ($item->status !== 'published') {
            throw new RuntimeException('الإثبات يُسجَّل بعد النشر — انشر المحتوى أوّلًا.');
        }

        return $this->withinTenant($item, function () use ($item, $url, $note, $actorId) {
            $item->update(['published_url' => $url, 'proof_note' => $note, 'proof_by' => $actorId, 'proof_at' => now()]);
            AuditLogger::log('content.proof_recorded', $item, ['url' => $url], $item->tenant_id, $actorId);

            return $item;
        });
    }

    /**
     * نتائج المنشور — تُدخَل يدويًّا وتُوسَم بمصدرها.
     *
     * لا مزوّد منصّة مربوط، فوسم المصدر `manual` صدقٌ لا تفصيل: التقرير يجب أن
     * يقول من أين جاء الرقم. ما لم يُدخَل يبقى فارغًا لا صفرًا — الصفر ادّعاء.
     */
    public function recordResults(ContentItem $item, array $metrics, int $actorId, string $source = 'manual'): ContentItem
    {
        if (! $item->hasPublishProof()) {
            throw new RuntimeException('سجّل إثبات النشر أوّلًا — النتائج تُنسَب إلى منشور معروف.');
        }

        $clean = array_intersect_key($metrics, array_flip(['reach', 'impressions', 'engagements', 'clicks']));
        $clean = array_filter($clean, fn ($v) => $v !== null && $v !== '');

        if (! $clean) {
            throw new RuntimeException('أدخل نتيجة واحدة على الأقل.');
        }

        return $this->withinTenant($item, function () use ($item, $clean, $actorId, $source) {
            $item->update($clean + ['results_source' => $source, 'results_at' => now()]);
            AuditLogger::log('content.results_recorded', $item, $clean + ['source' => $source], $item->tenant_id, $actorId);

            return $item;
        });
    }

    /** يبدأ مراجعة الوكالة. */
    public function startAgencyReview(ContentItem $item, int $actorId): ContentItem
    {
        return $this->transition($item, 'agency_review', $actorId, 'agency');
    }

    /** الوكالة تمرّر للعميل. */
    public function sendToClient(ContentItem $item, int $actorId, ?string $note = null): ContentItem
    {
        $r = $this->transition($item, 'client_review', $actorId, 'agency', $note);
        $this->recordApproval($item, 'agency', 'approved', $actorId, 'agency', $note);
        $this->notifyClient($item, 'محتوى بانتظار اعتمادكم', $item->title);

        return $r;
    }

    /** الوكالة أو العميل يطلب تعديلًا. */
    public function requestChanges(ContentItem $item, int $actorId, string $reviewerType, string $reason): ContentItem
    {
        $stage = $reviewerType === 'client' ? 'client' : 'agency';
        $r = $this->transition($item, 'changes_requested', $actorId, $reviewerType, $reason);
        $this->recordApproval($item, $stage, 'changes_requested', $actorId, $reviewerType, $reason);
        $this->notifyCreator($item, 'مطلوب تعديل على محتواك', $reason);

        return $r;
    }

    /** العميل يوافق. */
    public function clientApprove(ContentItem $item, int $actorId, ?string $note = null): ContentItem
    {
        $r = $this->transition($item, 'approved', $actorId, 'client', $note);
        $this->recordApproval($item, 'client', 'approved', $actorId, 'client', $note);
        $this->notifyCreator($item, 'اعتُمد محتواك', $item->title);

        return $r;
    }

    public function reject(ContentItem $item, int $actorId, string $reviewerType, string $reason): ContentItem
    {
        $r = $this->transition($item, 'rejected', $actorId, $reviewerType, $reason);
        $this->recordApproval($item, $reviewerType === 'client' ? 'client' : 'agency', 'rejected', $actorId, $reviewerType, $reason);

        return $r;
    }

    public function schedule(ContentItem $item, int $actorId, \DateTimeInterface $at): ContentItem
    {
        return $this->transition($item, 'scheduled', $actorId, 'agency', null, fn ($x) => $x->scheduled_at = $at);
    }

    public function publish(ContentItem $item, int $actorId): ContentItem
    {
        return $this->transition($item, 'published', $actorId, 'agency', null, fn ($x) => $x->published_at = now());
    }

    private function transition(ContentItem $item, string $to, int $actorId, string $actorType, ?string $reason = null, ?callable $mutate = null): ContentItem
    {
        return DB::transaction(function () use ($item, $to, $actorId, $actorType, $reason, $mutate) {
            return TenantContext::withTenant($item->tenant_id, function () use ($actorId, $actorType, $item, $mutate, $reason, $to) {
                $from = $item->status;
                if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
                    throw new RuntimeException("انتقال غير مسموح: {$from} → {$to}");
                }
                if ($mutate) {
                    $mutate($item);
                }
                $item->status = $to;
                $item->save();
                $this->recordStatus($item, $from, $to, $actorId, $actorType, $reason);
                AuditLogger::log("content.$to", $item, ['actor_type' => $actorType], $item->tenant_id, $actorId);

                return $item;
            });
        });
    }

    private function recordApproval(ContentItem $item, string $stage, string $decision, int $reviewerId, string $reviewerType, ?string $note): void
    {
        ContentApproval::create([
            'tenant_id' => $item->tenant_id, 'content_item_id' => $item->id, 'stage' => $stage, 'decision' => $decision,
            'reviewer_id' => $reviewerId, 'reviewer_type' => $reviewerType, 'note' => $note, 'content_version' => $item->version, 'created_at' => now(),
        ]);
    }

    private function recordStatus(ContentItem $item, ?string $from, string $to, int $actorId, string $actorType, ?string $reason): void
    {
        ContentStatusHistory::create([
            'tenant_id' => $item->tenant_id, 'content_item_id' => $item->id, 'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actorId, 'actor_type' => $actorType, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }

    /**
     * `transition()` يُعيد ضبط سياق المستأجر في `finally`، وTenantScope مغلق
     * افتراضيًا — فكان البحث عن المستقبِل يعود فارغًا ويُتخطّى الإشعار بصمت.
     * كل بحث عن مستقبِل يجري داخل سياق المستأجر.
     *
     * @template T
     *
     * @param  callable():T  $lookup
     * @return T
     */
    private function withinTenant(ContentItem $item, callable $lookup)
    {
        return TenantContext::withTenant($item->tenant_id, function () use ($lookup) {
            return $lookup();
        });
    }

    private function notifyCreator(ContentItem $item, string $title, string $body): void
    {
        $userId = $this->withinTenant($item, fn () => $item->creator_id ? Creator::find($item->creator_id)?->user_id : null);

        if ($userId) {
            $this->notifications->notify($item->tenant_id, $userId, 'content.update', 'general', $title, $body, '/creator/content', ['content_id' => $item->id], $item);
        }
    }

    /**
     * المحتوى المُقدَّم ينتظر مراجعة الوكالة — ولم يكن أحد يُبلَّغ به.
     * صاحب الحملة هو من يملك قرار المراجعة، فهو المقصود.
     */
    private function notifyAgency(ContentItem $item, string $title, string $body): void
    {
        $userId = $this->withinTenant($item, fn () => $item->campaign_id
            ? Campaign::find($item->campaign_id)?->created_by
            : null);

        if ($userId) {
            $this->notifications->notify($item->tenant_id, (int) $userId, 'content.submitted', 'general', $title, $body, "/app/content/{$item->id}", ['content_id' => $item->id], $item);
        }
    }

    /** والمحتوى المُمرَّر للعميل ينتظر قراره — فيُبلَّغ أعضاؤه. */
    private function notifyClient(ContentItem $item, string $title, string $body): void
    {
        $userIds = $this->withinTenant($item, fn () => $item->client_id
            ? ClientMember::where('client_id', $item->client_id)
                ->where('status', 'active')->pluck('user_id')->all()
            : []);

        foreach ($userIds as $uid) {
            $this->notifications->notify($item->tenant_id, (int) $uid, 'content.client_review', 'general', $title, $body, '/client/content', ['content_id' => $item->id], $item);
        }
    }
}
