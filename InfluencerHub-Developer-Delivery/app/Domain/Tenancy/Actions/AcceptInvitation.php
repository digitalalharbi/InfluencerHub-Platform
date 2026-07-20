<?php
namespace App\Domain\Tenancy\Actions;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Invitation, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use RuntimeException;
/** يقبل دعوة صالحة وينشئ عضوية فعّالة. يرفض المنتهية/المستخدمة/الملغاة. */
class AcceptInvitation {
    public function handle(Invitation $invitation, User $user): OrganizationMembership {
        if ($invitation->status !== 'pending') {
            throw new RuntimeException('الدعوة غير صالحة (مستخدمة أو ملغاة).');
        }
        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired']);
            throw new RuntimeException('انتهت صلاحية الدعوة.');
        }
        $membership = TenantContext::withBypass(function () use ($invitation, $user) {
            $membership = OrganizationMembership::firstOrCreate(
                ['organization_id' => $invitation->organization_id, 'workspace_id' => $invitation->workspace_id, 'user_id' => $user->id],
                ['tenant_id' => $invitation->tenant_id, 'role' => $invitation->role, 'status' => 'active']
            );
            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);
            return $membership;
        });
        return $membership;
    }
}
