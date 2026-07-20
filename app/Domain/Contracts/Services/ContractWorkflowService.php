<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Contracts\Models\{Contract, ContractStatusHistory};
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * دورة حياة العقد بالأحداث. الوكالة تصدر/ترسل؛ الطرف المقابل يقبل داخل بوابته؛ ثم يُفعَّل ويكتمل.
 * draft→sent/cancelled؛ sent→signed/cancelled؛ signed→active/cancelled؛ active→completed/terminated؛
 * القبول (sign) يُسجَّل باسم ووقت وفاعل — ليس توقيعًا قانونيًا خارجيًا.
 */
class ContractWorkflowService
{
    private const ALLOWED = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['signed', 'cancelled'],
        'signed' => ['active', 'cancelled'],
        'active' => ['completed', 'terminated'],
        'completed' => [],
        'terminated' => [],
        'cancelled' => [],
    ];

    public function __construct(private NotificationService $notifications) {}

    public function create(int $tenantId, array $data, int $actorId): Contract
    {
        return DB::transaction(function () use ($tenantId, $data, $actorId) {
            return TenantContext::withTenant($tenantId, function () use ($actorId, $data, $tenantId) {
                if (($data['party_type'] ?? null) === 'creator' && empty($data['creator_id'])) throw new RuntimeException('حدّد المبدع.');
                if (($data['party_type'] ?? null) === 'client' && empty($data['client_id'])) throw new RuntimeException('حدّد العميل.');
                $c = Contract::create($data + [
                    'tenant_id' => $tenantId, 'status' => 'draft', 'created_by' => $actorId,
                    'contract_number' => 'CT-' . $tenantId . '-' . (Contract::where('tenant_id', $tenantId)->count() + 1),
                ]);
                $this->recordStatus($c, null, 'draft', $actorId, 'agency', 'إنشاء العقد');
                AuditLogger::log('contract.created', $c, [], $tenantId, $actorId);
                return $c;
            });
        });
    }

    /**
     * عقد المبدع مشتقًّا من تعاونه.
     *
     * إنشاء العقد من الصفر كان يفقد الرابط بالتعاون والحملة رغم وجود العمودين،
     * ويطلب من المستخدم إعادة كتابة ما تقرّر فعلًا: الطرف والأجر والموعد. وهو
     * عين ما مُنع في الطلب←الحملة: لا تُدخَل المعلومة مرّتين.
     *
     * والتكرار ممنوع: عقد حيّ واحد لكل تعاون.
     */
    public function createFromCollaboration(Collaboration $col, int $actorId): Contract
    {
        // الفحص داخل سياق المستأجر: TenantScope مغلق افتراضيًا، فبلا سياق
        // يعود الاستعلام فارغًا ويمرّ التكرار كأنّ لا عقد.
        $existing = TenantContext::withTenant($col->tenant_id, fn () => Contract::where('collaboration_id', $col->id)
            ->whereNotIn('status', ['cancelled', 'terminated'])
            ->first());

        if ($existing) {
            throw new RuntimeException("لهذا التعاون عقد قائم ({$existing->contract_number}).");
        }

        if (! in_array($col->status, ['accepted', 'in_progress'], true)) {
            throw new RuntimeException('يُصدَر العقد بعد قبول المبدع للتعاون.');
        }

        return $this->create($col->tenant_id, [
            'party_type' => 'creator',
            'creator_id' => $col->creator_id,
            'client_id' => $col->client_id,
            'collaboration_id' => $col->id,
            'campaign_id' => $col->campaign_id,
            'title' => $col->title,
            'value_minor' => (int) ($col->fee_minor ?? 0),
            'currency' => $col->currency ?: 'SAR',
            'end_date' => $col->due_date,
        ], $actorId);
    }

    public function updateDraft(Contract $c, array $data, int $actorId): Contract
    {
        if (! $c->isEditable()) throw new RuntimeException('لا يمكن تعديل العقد بعد إرساله.');
        return TenantContext::withTenant($c->tenant_id, function () use ($actorId, $c, $data) {
            $c->update($data);
            AuditLogger::log('contract.updated', $c, array_keys($data), $c->tenant_id, $actorId);
            return $c;
        });
    }

    public function send(Contract $c, int $actorId): Contract
    {
        $r = $this->transition($c, 'sent', $actorId, 'agency', null, fn ($x) => $x->sent_at = now());
        $this->notifyParty($c, 'عقد بانتظار موافقتك', $c->title);
        return $r;
    }

    /** الطرف المقابل يقبل العقد داخل بوابته (تسجيل القبول). */
    public function sign(Contract $c, int $userId, string $signerName, string $actorType): Contract
    {
        return $this->transition($c, 'signed', $userId, $actorType, null, function ($x) use ($userId, $signerName) {
            $x->signed_at = now();
            $x->signed_by_name = $signerName;
            $x->signed_by_user = $userId;
        });
    }

    public function activate(Contract $c, int $actorId): Contract { return $this->transition($c, 'active', $actorId, 'agency'); }
    public function complete(Contract $c, int $actorId): Contract { return $this->transition($c, 'completed', $actorId, 'agency'); }
    public function terminate(Contract $c, int $actorId, string $reason): Contract
    {
        return $this->transition($c, 'terminated', $actorId, 'agency', $reason, fn ($x) => $x->termination_reason = $reason);
    }
    public function cancel(Contract $c, int $actorId, ?string $reason = null): Contract { return $this->transition($c, 'cancelled', $actorId, 'agency', $reason); }

    private function transition(Contract $c, string $to, int $actorId, string $actorType, ?string $reason = null, ?callable $mutate = null): Contract
    {
        return DB::transaction(function () use ($c, $to, $actorId, $actorType, $reason, $mutate) {
            return TenantContext::withTenant($c->tenant_id, function () use ($c, $to, $actorId, $actorType, $reason, $mutate) {
                // قفل الصف وإعادة قراءة الحالة من القاعدة (لا اعتماد على حالة قديمة/متزامنة)
                $c = Contract::query()->whereKey($c->getKey())->lockForUpdate()->first() ?? $c;
                $from = $c->status;
                if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
                    throw new RuntimeException("انتقال غير مسموح: {$from} → {$to}");
                }
                if ($mutate) $mutate($c);
                $c->status = $to;
                $c->save();
                $this->recordStatus($c, $from, $to, $actorId, $actorType, $reason);
                AuditLogger::log("contract.$to", $c, ['actor_type' => $actorType], $c->tenant_id, $actorId);
                if ($actorType !== 'agency' && $c->created_by) {
                    $this->notifications->notify($c->tenant_id, $c->created_by, "contract.$to", 'general', "تحديث عقد: {$c->title}", 'الحالة: ' . $to, "/app/contracts/{$c->id}", ['contract_id' => $c->id], $c);
                }
                return $c;
            });
        });
    }

    /**
     * الطرف يُبلَّغ أن عقدًا ينتظر توقيعه.
     *
     * `transition()` يُعيد ضبط سياق المستأجر في `finally` قبل الوصول إلى هنا،
     * وTenantScope مغلق افتراضيًا — فكان البحث عن الطرف يعود فارغًا دائمًا
     * ويُتخطّى الإشعار بصمت. النتيجة: عقد بحالة «مُرسَل» ينتظر توقيعًا لا يعلم
     * به أحد، للطرفين معًا. لذلك يُقرأ الطرف داخل سياق مستأجر العقد.
     */
    private function notifyParty(Contract $c, string $title, string $body): void
    {
        [$creatorUserId, $clientUserIds] = $this->partyRecipients($c);

        if ($creatorUserId) {
            $this->notifications->notify($c->tenant_id, $creatorUserId, 'contract.sent', 'general', $title, $body, '/creator/contracts', ['contract_id' => $c->id], $c);
        }
        foreach ($clientUserIds as $uid) {
            $this->notifications->notify($c->tenant_id, $uid, 'contract.sent', 'general', $title, $body, '/client/contracts', ['contract_id' => $c->id], $c);
        }
    }

    /** @return array{0: ?int, 1: array<int>} */
    private function partyRecipients(Contract $c): array
    {
        return TenantContext::withTenant($c->tenant_id, function () use ($c) {
            if ($c->party_type === 'creator' && $c->creator_id) {
                return [Creator::find($c->creator_id)?->user_id, []];
            }
            if ($c->party_type === 'client' && $c->client_id) {
                return [null, $this->clientMemberIds($c->client_id)];
            }

            return [null, []];
        });
    }

    private function clientMemberIds(int $clientId): array
    {
        return \App\Domain\CRM\Models\ClientMember::where('client_id', $clientId)->where('status', 'active')->pluck('user_id')->all();
    }

    private function recordStatus(Contract $c, ?string $from, string $to, int $actorId, string $actorType, ?string $reason): void
    {
        ContractStatusHistory::create([
            'tenant_id' => $c->tenant_id, 'contract_id' => $c->id, 'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actorId, 'actor_type' => $actorType, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }
}
