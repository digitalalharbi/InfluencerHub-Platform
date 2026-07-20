<?php
namespace App\Domain\Campaigns\Policies;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
class CampaignPolicy {
    private function role(User $user): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $user->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function view(User $u, Campaign $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function create(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
    public function update(User $u, Campaign $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
    public function delete(User $u, Campaign $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::DELETE); }
}
