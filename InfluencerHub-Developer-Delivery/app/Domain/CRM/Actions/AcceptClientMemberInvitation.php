<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\{ClientMember, ClientMemberInvitation, ClientMemberStatusHistory};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class AcceptClientMemberInvitation {
    public function handle(string $rawToken, User $user): ClientMember {
        return DB::transaction(function () use ($rawToken, $user) {
            $member = TenantContext::withBypass(function () use ($rawToken, $user) {
                $inv = ClientMemberInvitation::where('token_hash', hash('sha256', $rawToken))->lockForUpdate()->first();
                if (! $inv || ! $inv->isPending()) {
                    throw new RuntimeException('الدعوة غير صالحة (منتهية أو مستخدمة أو ملغاة).');
                }
                $member = ClientMember::firstOrCreate(
                    ['client_id' => $inv->client_id, 'user_id' => $user->id],
                    ['tenant_id' => $inv->tenant_id, 'role' => $inv->role, 'status' => 'active', 'invited_by' => $inv->invited_by, 'accepted_at' => now()]
                );
                if ($member->status !== 'active') { $member->update(['status' => 'active', 'accepted_at' => now()]); }
                $inv->update(['accepted_at' => now()]);
                ClientMemberStatusHistory::create(['tenant_id' => $inv->tenant_id, 'client_member_id' => $member->id, 'from_status' => null, 'to_status' => 'active', 'changed_by' => $user->id, 'created_at' => now()]);
                AuditLogger::log('client_member.accepted', $member, [], $inv->tenant_id, $user->id);
                return $member;
            });
            return $member;
        });
    }
}
