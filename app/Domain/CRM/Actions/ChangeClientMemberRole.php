<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Enums\ClientMemberRole;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Identity\Models\User;
use RuntimeException;
class ChangeClientMemberRole {
    public function handle(ClientMember $member, string $role, ?User $actor = null): ClientMember {
        if (! in_array($role, ClientMemberRole::values(), true)) {
            throw new RuntimeException('دور بوابة عميل غير صالح.');
        }
        $from = $member->role;
        $member->update(['role' => $role]);
        AuditLogger::log('client_member.role_changed', $member, ['from' => $from, 'to' => $role], $member->tenant_id, $actor?->id);
        return $member->fresh();
    }
}
