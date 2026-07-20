<?php

namespace App\Domain\Requests\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Requests\Enums\ServiceRequestPriority;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Requests\Models\ServiceRequestComment;
use App\Domain\Requests\Models\ServiceRequestStatusHistory;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * آلة حالة طلبات الخدمة (بالأحداث، لا Dropdown يدوي) + SLA.
 * submitted→triage/cancelled؛ triage→in_progress/needs_info/cancelled؛
 * in_progress→needs_info/resolved/cancelled؛ needs_info→in_progress/cancelled؛
 * resolved→closed/in_progress(إعادة فتح)؛ closed→(نهائي).
 */
class ServiceRequestWorkflowService
{
    private const ALLOWED = [
        'submitted' => ['triage', 'cancelled'],
        'triage' => ['in_progress', 'needs_info', 'cancelled'],
        'in_progress' => ['needs_info', 'resolved', 'cancelled'],
        'needs_info' => ['in_progress', 'cancelled'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => [],
        'cancelled' => [],
    ];

    /** ينشئ طلب خدمة جديدًا (submitted) مع رقم وSLA حسب الأولوية. */
    public function create(int $tenantId, array $data, int $actorId): ServiceRequest
    {
        return DB::transaction(function () use ($tenantId, $data, $actorId) {
            return TenantContext::withTenant($tenantId, function () use ($actorId, $data, $tenantId) {
                $priority = $data['priority'] ?? 'normal';
                $sr = ServiceRequest::create($data + [
                    'tenant_id' => $tenantId, 'status' => 'submitted', 'requested_by' => $actorId,
                    'priority' => $priority,
                    'request_number' => 'SR-'.$tenantId.'-'.(ServiceRequest::where('tenant_id', $tenantId)->count() + 1),
                    'due_at' => now()->addHours(ServiceRequestPriority::slaHours($priority)),
                ]);
                $this->recordStatus($sr, null, 'submitted', $actorId, 'إنشاء الطلب');
                AuditLogger::log('service_request.created', $sr, ['type' => $sr->type], $tenantId, $actorId);

                return $sr;
            });
        });
    }

    public function assign(ServiceRequest $sr, int $assigneeId, int $actorId): ServiceRequest
    {
        return TenantContext::withTenant($sr->tenant_id, function () use ($actorId, $assigneeId, $sr) {
            $sr->update(['assigned_to' => $assigneeId]);
            AuditLogger::log('service_request.assigned', $sr, ['assignee' => $assigneeId], $sr->tenant_id, $actorId);

            return $sr;
        });
    }

    public function triage(ServiceRequest $sr, int $actorId): ServiceRequest
    {
        return $this->transition($sr, 'triage', $actorId);
    }

    public function startWork(ServiceRequest $sr, int $actorId): ServiceRequest
    {
        return $this->transition($sr, 'in_progress', $actorId);
    }

    public function requestInfo(ServiceRequest $sr, int $actorId, string $reason): ServiceRequest
    {
        return $this->transition($sr, 'needs_info', $actorId, $reason);
    }

    public function resolve(ServiceRequest $sr, int $actorId, ?string $note = null): ServiceRequest
    {
        return $this->transition($sr, 'resolved', $actorId, $note, fn ($r) => $r->resolved_at = now());
    }

    public function close(ServiceRequest $sr, int $actorId, ?string $note = null): ServiceRequest
    {
        return $this->transition($sr, 'closed', $actorId, $note, fn ($r) => $r->closed_at = now());
    }

    public function reopen(ServiceRequest $sr, int $actorId, ?string $reason = null): ServiceRequest
    {
        return $this->transition($sr, 'in_progress', $actorId, $reason, function ($r) {
            $r->resolved_at = null;
            $r->closed_at = null;
        });
    }

    public function cancel(ServiceRequest $sr, int $actorId, ?string $reason = null): ServiceRequest
    {
        return $this->transition($sr, 'cancelled', $actorId, $reason);
    }

    /** يضيف تعليقًا (خارجي للطرفين، أو داخلي للوكالة). */
    public function comment(ServiceRequest $sr, int $actorId, string $authorType, string $body, bool $internal = false): ServiceRequestComment
    {
        return TenantContext::withTenant($sr->tenant_id, function () use ($actorId, $authorType, $body, $internal, $sr) {
            $c = ServiceRequestComment::create([
                'tenant_id' => $sr->tenant_id, 'service_request_id' => $sr->id, 'author_id' => $actorId,
                'author_type' => $authorType, 'body' => $body, 'is_internal' => $internal, 'created_at' => now(),
            ]);
            AuditLogger::log('service_request.commented', $sr, ['internal' => $internal], $sr->tenant_id, $actorId);

            return $c;
        });
    }

    private function transition(ServiceRequest $sr, string $to, int $actorId, ?string $reason = null, ?callable $mutate = null): ServiceRequest
    {
        return DB::transaction(function () use ($sr, $to, $actorId, $reason, $mutate) {
            return TenantContext::withTenant($sr->tenant_id, function () use ($actorId, $mutate, $reason, $sr, $to) {
                // قفل الصف وإعادة قراءة الحالة من القاعدة: يمنع اعتماد حالة قديمة في الذاكرة
                // ويُسلسِل التحوّلات المتزامنة فلا تتكرّر سجلّات الحالة/التدقيق.
                $locked = ServiceRequest::query()->whereKey($sr->getKey())->lockForUpdate()->first();
                if (! $locked) {
                    throw new RuntimeException('الطلب غير موجود.');
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
                AuditLogger::log("service_request.$to", $locked, [], $locked->tenant_id, $actorId);

                return $locked;
            });
        });
    }

    private function recordStatus(ServiceRequest $sr, ?string $from, string $to, int $actorId, ?string $reason): void
    {
        ServiceRequestStatusHistory::create([
            'tenant_id' => $sr->tenant_id, 'service_request_id' => $sr->id,
            'from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }
}
