<?php
namespace App\Domain\Partners\Policies;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\ExternalAgency;
use App\Domain\Tenancy\Support\TenantContext;
/** إدارة الوكالات الخارجية: عرض للجميع المخوّلين، والكتابة/الاعتماد للإدارة/العمليات. */
class ExternalAgencyPolicy {
    private function role(User $user): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $user->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function view(User $u, ExternalAgency $a): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function create(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
    public function update(User $u, ExternalAgency $a): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
    public function manage(User $u, ExternalAgency $a): bool { return CrmAbilities::can($this->role($u), CrmAbilities::MANAGE_PORTAL); }
}
