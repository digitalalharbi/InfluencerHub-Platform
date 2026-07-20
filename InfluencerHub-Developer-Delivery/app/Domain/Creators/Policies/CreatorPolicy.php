<?php
namespace App\Domain\Creators\Policies;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Support\CreatorAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
class CreatorPolicy {
    private function role(User $u): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $u->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::VIEW); }
    public function view(User $u, Creator $c): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::VIEW); }
    public function create(User $u): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::WRITE); }
    public function update(User $u, Creator $c): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::WRITE); }
    public function delete(User $u, Creator $c): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::WRITE); }
}
