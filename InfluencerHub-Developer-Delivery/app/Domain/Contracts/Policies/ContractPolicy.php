<?php
namespace App\Domain\Contracts\Policies;
use App\Domain\Contracts\Models\Contract;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
class ContractPolicy {
    private function role(User $user): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $user->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function view(User $u, Contract $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function create(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
    public function manage(User $u, Contract $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
}
