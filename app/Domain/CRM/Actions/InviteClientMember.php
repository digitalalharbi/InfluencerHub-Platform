<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Enums\ClientMemberRole;
use App\Domain\CRM\Models\{Client, ClientMemberInvitation};
use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
class InviteClientMember {
    /** يعيد [invitation, rawToken]. الرمز الخام يُعرض مرة واحدة فقط (نُخزّن Hash). */
    public function handle(Client $client, string $email, string $role, User $inviter): array {
        if (! in_array($role, ClientMemberRole::values(), true)) {
            throw new RuntimeException('دور بوابة عميل غير صالح (client_admin لا يعيّن أدوار الوكالة/النظام).');
        }
        $raw = Str::random(48);
        $inv = ClientMemberInvitation::create([
            'tenant_id' => $client->tenant_id, 'client_id' => $client->id, 'email' => $email, 'role' => $role,
            'token_hash' => hash('sha256', $raw), 'invited_by' => $inviter->id, 'expires_at' => now()->addDays(7),
        ]);
        AuditLogger::log('client_member.invited', $inv, ['email' => $email, 'role' => $role], $client->tenant_id, $inviter->id);
        // TODO(Queue): SendClientMemberInvitationNotification::dispatch($inv) — إشعار عبر الطابور
        return [$inv, $raw];
    }
}
