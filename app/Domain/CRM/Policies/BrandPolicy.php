<?php
namespace App\Domain\CRM\Policies;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;

class BrandPolicy {
    private function role(User $user): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $user->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function view(User $u, Brand $b): bool  { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function create(User $u): bool  { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
    public function update(User $u, Brand $b): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
    public function delete(User $u, Brand $b): bool { return CrmAbilities::can($this->role($u), CrmAbilities::DELETE); }
}
