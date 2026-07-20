<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{ClientMember, ClientMemberStatusHistory};
use App\Domain\Identity\Models\User;
class ChangeClientMemberStatus {
    /** suspend|reactivate|revoke → status. */
    public function handle(ClientMember $member, string $action, ?User $actor = null): ClientMember {
        $map = ['suspend' => 'suspended', 'reactivate' => 'active', 'revoke' => 'revoked'];
        $to = $map[$action] ?? throw new \RuntimeException('إجراء غير معروف');
        $from = $member->status;
        $member->update(array_filter([
            'status' => $to,
            'suspended_at' => $to === 'suspended' ? now() : null,
            'revoked_at' => $to === 'revoked' ? now() : null,
        ], fn ($v) => $v !== null) + ['status' => $to]);
        ClientMemberStatusHistory::create(['tenant_id' => $member->tenant_id, 'client_member_id' => $member->id, 'from_status' => $from, 'to_status' => $to, 'changed_by' => $actor?->id, 'created_at' => now()]);
        AuditLogger::log("client_member.$action", $member, ['from' => $from, 'to' => $to], $member->tenant_id, $actor?->id);
        return $member->fresh();
    }
}
