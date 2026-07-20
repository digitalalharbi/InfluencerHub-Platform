<?php

namespace App\Domain\Partners\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyStatusHistory};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * آلة حالة قبول الوكالة الخارجية مدفوعة بالأحداث (لا Dropdown يدوي).
 * draft→submitted→under_review→approved/changes_requested؛ approved→suspended/archived؛ suspended→approved/archived.
 */
class ExternalAgencyWorkflowService
{
    private const ALLOWED = [
        'draft' => ['submitted'],
        'changes_requested' => ['submitted'],
        'submitted' => ['under_review'],
        'under_review' => ['approved', 'changes_requested'],
        'approved' => ['suspended', 'archived'],
        'suspended' => ['approved', 'archived'],
    ];

    public function createDraft(int $tenantId, array $data, int $actorId): ExternalAgency
    {
        return DB::transaction(function () use ($tenantId, $data, $actorId) {
            return TenantContext::withTenant($tenantId, function () use ($actorId, $data, $tenantId) {
                $agency = ExternalAgency::create($data + [
                    'tenant_id' => $tenantId, 'status' => 'draft', 'created_by' => $actorId,
                    'agency_number' => 'PA-' . $tenantId . '-' . (ExternalAgency::where('tenant_id', $tenantId)->count() + 1),
                ]);
                $this->recordStatus($agency, null, 'draft', $actorId, 'إنشاء مسودة');
                AuditLogger::log('external_agency.created', $agency, [], $tenantId, $actorId);
                return $agency;
            });
        });
    }

    public function updateDraft(ExternalAgency $agency, array $data, int $actorId): ExternalAgency
    {
        if (! in_array($agency->status, ['draft', 'changes_requested'], true)) {
            throw new RuntimeException('لا يمكن تعديل الوكالة بعد إرسالها.');
        }
        return TenantContext::withTenant($agency->tenant_id, function () use ($actorId, $agency, $data) {
            $agency->update($data);
            AuditLogger::log('external_agency.updated', $agency, array_keys($data), $agency->tenant_id, $actorId);
            return $agency;
        });
    }

    public function submit(ExternalAgency $agency, int $actorId): ExternalAgency
    {
        return $this->transition($agency, 'submitted', $actorId, null, function ($a) {
            $a->submitted_at = now();
        });
    }

    public function startReview(ExternalAgency $agency, int $actorId): ExternalAgency
    {
        return $this->transition($agency, 'under_review', $actorId);
    }

    public function approve(ExternalAgency $agency, int $actorId, ?string $note = null): ExternalAgency
    {
        return $this->transition($agency, 'approved', $actorId, $note, function ($a) use ($actorId) {
            $a->reviewed_at = now();
            $a->reviewed_by = $actorId;
            $a->changes_reason = null;
        });
    }

    public function requestChanges(ExternalAgency $agency, int $actorId, string $reason): ExternalAgency
    {
        return $this->transition($agency, 'changes_requested', $actorId, $reason, function ($a) use ($reason) {
            $a->changes_reason = $reason;
        });
    }

    public function suspend(ExternalAgency $agency, int $actorId, ?string $reason = null): ExternalAgency
    {
        return $this->transition($agency, 'suspended', $actorId, $reason);
    }

    public function archive(ExternalAgency $agency, int $actorId, ?string $reason = null): ExternalAgency
    {
        return $this->transition($agency, 'archived', $actorId, $reason);
    }

    private function transition(ExternalAgency $agency, string $to, int $actorId, ?string $reason = null, ?callable $mutate = null): ExternalAgency
    {
        return DB::transaction(function () use ($agency, $to, $actorId, $reason, $mutate) {
            return TenantContext::withTenant($agency->tenant_id, function () use ($actorId, $agency, $mutate, $reason, $to) {
                $from = $agency->status;
                $this->assertTransition($from, $to);
                if ($mutate) $mutate($agency);
                $agency->status = $to;
                $agency->save();
                $this->recordStatus($agency, $from, $to, $actorId, $reason);
                AuditLogger::log("external_agency.$to", $agency, [], $agency->tenant_id, $actorId);
                return $agency;
            });
        });
    }

    private function assertTransition(string $from, string $to): void
    {
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new RuntimeException("انتقال غير مسموح: {$from} → {$to}");
        }
    }

    private function recordStatus(ExternalAgency $agency, ?string $from, string $to, int $actorId, ?string $reason): void
    {
        ExternalAgencyStatusHistory::create([
            'tenant_id' => $agency->tenant_id, 'external_agency_id' => $agency->id,
            'from_status' => $from, 'to_status' => $to, 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now(),
        ]);
    }
}
