<?php
namespace App\Domain\Partners\Services;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Enums\PartnerRole;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyInvitation};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Str;
use RuntimeException;
/** يدعو عضو بوابة شريك. يعيد [invitation, rawToken] — الرمز الخام يُعرض مرة واحدة (نُخزّن Hash). */
class InvitePartnerMember {
    public function handle(ExternalAgency $agency, string $email, string $role, User $inviter): array {
        if (! in_array($role, PartnerRole::values(), true)) {
            throw new RuntimeException('دور بوابة شريك غير صالح.');
        }
        return TenantContext::withTenant($agency->tenant_id, function () use ($agency, $email, $inviter, $role) {
            $raw = Str::random(48);
            $inv = ExternalAgencyInvitation::create([
                'tenant_id' => $agency->tenant_id, 'external_agency_id' => $agency->id, 'email' => $email, 'role' => $role,
                'token_hash' => hash('sha256', $raw), 'invited_by' => $inviter->id, 'expires_at' => now()->addDays(7),
            ]);
            AuditLogger::log('external_agency_member.invited', $inv, ['email' => $email, 'role' => $role], $agency->tenant_id, $inviter->id);
            return [$inv, $raw];
        });
    }
}
