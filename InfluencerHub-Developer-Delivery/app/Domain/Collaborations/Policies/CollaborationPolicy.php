<?php
namespace App\Domain\Collaborations\Policies;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
class CollaborationPolicy {
    private function role(User $user): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $user->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function view(User $u, Collaboration $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function manage(User $u, Collaboration $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
    public function create(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
}
