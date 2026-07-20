<?php

namespace App\Domain\Finance\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Models\PayoutStatusHistory;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * دورة حياة المستحقات (بالأحداث) بحالات صادقة:
 * pending→approved/cancelled؛ approved→scheduled/cancelled؛ scheduled→waiting_for_provider/cancelled؛
 * waiting_for_provider→paid/failed؛ failed→scheduled/cancelled.
 * لا تنفيذ دفع فعلي هنا — «paid» تُسجَّل يدويًا بمرجع تحويل بعد تسوية حقيقية عبر مزوّد مربوط.
 */
class PayoutWorkflowService
{
    private const ALLOWED = [
        'pending' => ['approved', 'cancelled'],
        'approved' => ['scheduled', 'cancelled'],
        'scheduled' => ['waiting_for_provider', 'cancelled'],
        'waiting_for_provider' => ['paid', 'failed'],
        'failed' => ['scheduled', 'cancelled'],
        'paid' => [],
        'cancelled' => [],
    ];

    /** بعدهما لا يشغل المستحقّ التعاون، فإعادة إنشائه مشروعة. */
    private const REPAYABLE = ['cancelled', 'failed'];

    public function __construct(private NotificationService $notifications) {}

    /** ينشئ التزام مستحق لمبدع (لقطة IBAN last4 من الملف المشفّر). */
    public function create(int $tenantId, array $data, int $actorId): Payout
    {
        return DB::transaction(function () use ($tenantId, $data, $actorId) {
            return TenantContext::withTenant($tenantId, function () use ($actorId, $data, $tenantId) {
                $creator = Creator::find($data['creator_id'] ?? 0);
                if (! $creator) {
                    throw new RuntimeException('المبدع غير موجود.');
                }
                if (($data['amount_minor'] ?? 0) <= 0) {
                    throw new RuntimeException('المبلغ يجب أن يكون موجبًا.');
                }
                $p = Payout::create($data + [
                    'tenant_id' => $tenantId, 'status' => 'pending', 'created_by' => $actorId,
                    'currency' => $data['currency'] ?? 'SAR', 'iban_last4' => $creator->iban_last4,
                    'payout_number' => 'PY-'.$tenantId.'-'.(Payout::where('tenant_id', $tenantId)->count() + 1),
                ]);
                $this->recordStatus($p, null, 'pending', $actorId, 'إنشاء المستحق');
                AuditLogger::log('payout.created', $p, ['amount_minor' => $p->amount_minor], $tenantId, $actorId);

                return $p;
            });
        });
    }

    /**
     * مستحقّ المبدع مشتقًّا من تعاونه.
     *
     * الجدول يحمل `collaboration_id` و`campaign_id` و`contract_id` ولا مسار
     * يملؤها: `store` لا يقبل إلا المبدع والمبلغ. فيُعاد إدخال ما تقرّر فعلًا،
     * ويخرج المستحقّ يتيمًا عن العمل الذي يستحقّ عنه — فلا يُتتبَّع
     * مستحقّ ← تعاون ← مخرَج ← محتوى.
     *
     * والتكرار ممنوع: مستحقّ حيّ واحد لكل تعاون، وإلا دُفع أجر العمل مرّتين.
     */
    public function createFromCollaboration(Collaboration $col, int $actorId): Payout
    {
        [$existing, $contractId] = TenantContext::withTenant($col->tenant_id, fn () => [
            Payout::where('collaboration_id', $col->id)
                ->whereNotIn('status', self::REPAYABLE)
                ->first(),
            Contract::where('collaboration_id', $col->id)
                ->whereNotIn('status', ['cancelled', 'terminated'])->value('id'),
        ]);

        if ($existing) {
            throw new RuntimeException("لهذا التعاون مستحقّ قائم ({$existing->payout_number}).");
        }
        if ((int) ($col->fee_minor ?? 0) < 1) {
            throw new RuntimeException('التعاون بلا أجر — حدّد الأجر قبل إنشاء المستحقّ.');
        }

        return $this->create($col->tenant_id, [
            'creator_id' => $col->creator_id,
            'collaboration_id' => $col->id,
            'campaign_id' => $col->campaign_id,
            'contract_id' => $contractId,
            'amount_minor' => (int) $col->fee_minor,
            'currency' => $col->currency ?: 'SAR',
            'description' => $col->title,
            'due_date' => $col->due_date,
        ], $actorId);
    }

    public function approve(Payout $p, int $actorId): Payout
    {
        return $this->transition($p, 'approved', $actorId);
    }

    public function schedule(Payout $p, int $actorId, ?\DateTimeInterface $due = null): Payout
    {
        return $this->transition($p, 'scheduled', $actorId, null, function ($x) use ($due) {
            if ($due) {
                $x->due_date = $due;
            }
        });
    }

    /** يبدأ التسوية عبر المزوّد — حالة صادقة (لا مزوّد مربوط ⇒ ينتظر). */
    public function sendToProvider(Payout $p, int $actorId): Payout
    {
        return $this->transition($p, 'waiting_for_provider', $actorId);
    }

    /** يُسجَّل الدفع يدويًا بعد تحويل حقيقي (مرجع إلزامي) — النظام لا ينفّذ التحويل. */
    public function markPaid(Payout $p, int $actorId, string $reference): Payout
    {
        return $this->transition($p, 'paid', $actorId, 'مرجع: '.$reference, function ($x) use ($reference) {
            $x->paid_at = now();
            $x->payment_reference = $reference;
        });
    }

    public function markFailed(Payout $p, int $actorId, string $reason): Payout
    {
        return $this->transition($p, 'failed', $actorId, $reason, fn ($x) => $x->failure_reason = $reason);
    }

    public function cancel(Payout $p, int $actorId, ?string $reason = null): Payout
    {
        return $this->transition($p, 'cancelled', $actorId, $reason);
    }

    private function transition(Payout $p, string $to, int $actorId, ?string $reason = null, ?callable $mutate = null): Payout
    {
        return DB::transaction(function () use ($p, $to, $actorId, $reason, $mutate) {
            return TenantContext::withTenant($p->tenant_id, function () use ($actorId, $mutate, $p, $reason, $to) {
                // قفل الصف وإعادة قراءة الحالة من القاعدة: يمنع الاعتماد على حالة قديمة في الذاكرة
                // ويُسلسِل التحوّلات المتزامنة (نقر مزدوج على «تسجيل الدفع») فلا تتكرّر الآثار الجانبية.
                $locked = Payout::query()->whereKey($p->getKey())->lockForUpdate()->first();
                if (! $locked) {
                    throw new RuntimeException('المستحق غير موجود.');
                }
                $from = $locked->status;
                if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
                    throw new RuntimeException("انتقال غير مسموح: {$from} → {$to}");
                }
                if ($mutate) {
                    $mutate($locked);
                }
                $locked->status = $to;
                $locked->save();
                $this->recordStatus($locked, $from, $to, $actorId, $reason);
                // الانتقال داخل السجلّ: «مدفوع» وحدها لا تقول من أين جاءت
                AuditLogger::log("payout.$to", $locked, ['from' => $from, 'to' => $to], $locked->tenant_id, $actorId);
                // الاعتماد يعني أن الأجر صار التزامًا لا طلبًا معلّقًا — وهو خبر
                // يخصّ المبدع لا الوكالة وحدها، فكان الاعتماد يمرّ صامتًا عنه.
                if (in_array($to, ['approved', 'paid', 'scheduled', 'failed'], true)) {
                    $this->notifyCreator($locked, $to);
                }

                return $locked;
            });
        });
    }

    private function notifyCreator(Payout $p, string $to): void
    {
        $creator = Creator::find($p->creator_id);
        if (! $creator?->user_id) {
            return;
        }
        $labels = ['approved' => 'اعتُمد مستحقك', 'scheduled' => 'جُدول مستحقك للدفع',
            'paid' => 'تم دفع مستحقك', 'failed' => 'تعذّر دفع مستحقك'];
        $this->notifications->notify($p->tenant_id, $creator->user_id, "payout.$to", 'general',
            $labels[$to] ?? 'تحديث مستحق', $p->description ?? $p->payout_number, '/creator/payouts', ['payout_id' => $p->id], $p);
    }

    private function recordStatus(Payout $p, ?string $from, string $to, int $actorId, ?string $reason): void
    {
        PayoutStatusHistory::create([
            'tenant_id' => $p->tenant_id, 'payout_id' => $p->id, 'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }
}
