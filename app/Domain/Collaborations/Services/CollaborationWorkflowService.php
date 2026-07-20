<?php

namespace App\Domain\Collaborations\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Campaigns\Models\CampaignDeliverable;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Collaborations\Models\CollaborationStatusHistory;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * دورة حياة التعاون بالأحداث. الوكالة تعرض؛ المبدع يقبل/يرفض/يسلّم؛ الوكالة تعتمد.
 * offered→accepted/declined/cancelled؛ accepted→in_progress/cancelled؛ in_progress→submitted/cancelled؛
 * submitted→approved/in_progress(مراجعة)؛ approved→completed.
 */
class CollaborationWorkflowService
{
    private const ALLOWED = [
        'offered' => ['accepted', 'declined', 'cancelled'],
        'accepted' => ['in_progress', 'cancelled'],
        'in_progress' => ['submitted', 'cancelled'],
        'submitted' => ['approved', 'in_progress'],
        'approved' => ['completed'],
        'declined' => [],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * بعدها العرض مُنتهٍ ولا يشغل المخرَج — فإعادة العرض على نفس المبدع مشروعة.
     * ما عداها عرض حيّ: تكراره يعني تعاونين على عمل واحد.
     */
    private const REOFFERABLE = ['declined', 'cancelled'];

    public function __construct(private NotificationService $notifications) {}

    /** الوكالة تعرض تعاونًا على مبدع (اختياريًا من مخرَج حملة). */
    public function offer(int $tenantId, array $data, int $actorId): Collaboration
    {
        return DB::transaction(function () use ($tenantId, $data, $actorId) {
            return TenantContext::withTenant($tenantId, function () use ($actorId, $data, $tenantId) {
                if (empty($data['creator_id']) || ! Creator::where('id', $data['creator_id'])->exists()) {
                    throw new RuntimeException('المبدع غير موجود في هذا المستأجر.');
                }
                $this->guardDuplicateOffer($data);
                $c = Collaboration::create($data + [
                    'tenant_id' => $tenantId, 'status' => 'offered', 'created_by' => $actorId, 'offered_at' => now(),
                    'collaboration_number' => 'CO-'.$tenantId.'-'.(Collaboration::where('tenant_id', $tenantId)->count() + 1),
                ]);
                $this->recordStatus($c, null, 'offered', $actorId, 'agency', 'عرض تعاون');
                AuditLogger::log('collaboration.offered', $c, ['creator_id' => $c->creator_id], $tenantId, $actorId);
                $this->notifyCreator($c, 'عرض تعاون جديد', $c->title);

                return $c;
            });
        });
    }

    /**
     * تعاون واحد لكل مبدع على المخرَج الواحد.
     *
     * الواجهة كانت تعرض «عُرض عليه من قبل» ولا تمنع الإرسال، فالنقر المزدوج أو
     * استدعاء المسار مباشرةً يُنشئ تعاونين على عمل واحد — بأجرين ومستحقّين.
     * المنع بالمخرَج لا بالحملة: المبدع متعدّد القدرات قد يأخذ منشورًا وUGC في
     * الحملة نفسها، وهذان عملان لا تكرار.
     */
    private function guardDuplicateOffer(array $data): void
    {
        if (empty($data['deliverable_id'])) {
            return;
        }

        $existing = Collaboration::where('deliverable_id', $data['deliverable_id'])
            ->where('creator_id', $data['creator_id'])
            ->whereNotIn('status', self::REOFFERABLE)
            ->first();

        if ($existing) {
            throw new RuntimeException(
                "لهذا المبدع تعاون قائم على المخرَج نفسه ({$existing->collaboration_number}). "
                .'افتحه بدل إنشاء تعاون ثانٍ، أو ألغِ القائم أوّلًا.'
            );
        }
    }

    /** يعرض تعاونًا مشتقًّا من مخرَج حملة (يرث الأجر/العميل/العلامة). */
    public function offerFromDeliverable(CampaignDeliverable $d, int $creatorId, int $actorId): Collaboration
    {
        $campaign = TenantContext::withTenant($d->tenant_id, function () use ($d) {
            $campaign = $d->campaign;

            return $campaign;
        });

        return $this->offer($d->tenant_id, [
            'creator_id' => $creatorId, 'campaign_id' => $d->campaign_id, 'deliverable_id' => $d->id,
            'client_id' => $campaign?->client_id, 'title' => ($campaign?->name ?? 'تعاون').' — '.$d->type,
            'brief' => $campaign?->brief, 'fee_minor' => $d->fee_minor ?? 0, 'currency' => $d->currency,
            'due_date' => $d->due_date,
        ], $actorId);
    }

    // ===== إجراءات المبدع =====
    public function accept(Collaboration $c, int $creatorUserId): Collaboration
    {
        return $this->transition($c, 'accepted', $creatorUserId, 'creator', null, fn ($x) => $x->responded_at = now());
    }

    public function decline(Collaboration $c, int $creatorUserId, ?string $reason = null): Collaboration
    {
        return $this->transition($c, 'declined', $creatorUserId, 'creator', $reason, function ($x) use ($reason) {
            $x->responded_at = now();
            $x->decline_reason = $reason;
        });
    }

    public function startWork(Collaboration $c, int $creatorUserId): Collaboration
    {
        return $this->transition($c, 'in_progress', $creatorUserId, 'creator');
    }

    public function submit(Collaboration $c, int $creatorUserId, ?string $note = null): Collaboration
    {
        return $this->transition($c, 'submitted', $creatorUserId, 'creator', null, function ($x) use ($note) {
            $x->submitted_at = now();
            $x->submission_note = $note;
        });
    }

    // ===== إجراءات الوكالة =====
    public function approve(Collaboration $c, int $actorId, ?string $note = null): Collaboration
    {
        return $this->transition($c, 'approved', $actorId, 'agency', $note);
    }

    public function requestRevision(Collaboration $c, int $actorId, string $reason): Collaboration
    {
        return $this->transition($c, 'in_progress', $actorId, 'agency', $reason);
    }

    public function complete(Collaboration $c, int $actorId): Collaboration
    {
        return $this->transition($c, 'completed', $actorId, 'agency', null, fn ($x) => $x->completed_at = now());
    }

    public function cancel(Collaboration $c, int $actorId, ?string $reason = null): Collaboration
    {
        return $this->transition($c, 'cancelled', $actorId, 'agency', $reason);
    }

    private function transition(Collaboration $c, string $to, int $actorId, string $actorType, ?string $reason = null, ?callable $mutate = null): Collaboration
    {
        return DB::transaction(function () use ($c, $to, $actorId, $actorType, $reason, $mutate) {
            return TenantContext::withTenant($c->tenant_id, function () use ($actorId, $actorType, $c, $mutate, $reason, $to) {
                // قفل الصف وإعادة قراءة الحالة من القاعدة (لا اعتماد على حالة قديمة/متزامنة)
                $c = Collaboration::query()->whereKey($c->getKey())->lockForUpdate()->first() ?? $c;
                $from = $c->status;
                if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
                    throw new RuntimeException("انتقال غير مسموح: {$from} → {$to}");
                }
                if ($mutate) {
                    $mutate($c);
                }
                $c->status = $to;
                $c->save();
                $this->recordStatus($c, $from, $to, $actorId, $actorType, $reason);
                AuditLogger::log("collaboration.$to", $c, ['actor_type' => $actorType], $c->tenant_id, $actorId);
                // إشعار الطرف المقابل
                if ($actorType === 'creator' && $c->created_by) {
                    $this->notifications->notify($c->tenant_id, $c->created_by, "collaboration.$to", 'general', "تحديث تعاون: {$c->title}", 'الحالة: '.$to, "/app/collaborations/{$c->id}", ['collaboration_id' => $c->id], $c);
                } elseif ($actorType === 'agency') {
                    $this->notifyCreator($c, 'تحديث تعاون', $c->title);
                }

                return $c;
            });
        });
    }

    private function notifyCreator(Collaboration $c, string $title, string $body): void
    {
        $creator = Creator::find($c->creator_id);
        if ($creator?->user_id) {
            $this->notifications->notify($c->tenant_id, $creator->user_id, 'collaboration.update', 'general', $title, $body, '/creator/collaborations', ['collaboration_id' => $c->id], $c);
        }
    }

    private function recordStatus(Collaboration $c, ?string $from, string $to, int $actorId, string $actorType, ?string $reason): void
    {
        CollaborationStatusHistory::create([
            'tenant_id' => $c->tenant_id, 'collaboration_id' => $c->id, 'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actorId, 'actor_type' => $actorType, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }
}
