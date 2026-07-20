<?php
namespace App\Domain\Creators\Policies;
use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Creators\Support\CreatorAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
class CreatorApplicationPolicy {
    private function role(User $u): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $u->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::VIEW); }
    public function view(User $u, CreatorApplication $a): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::VIEW); }
    public function review(User $u, CreatorApplication $a): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::WRITE); }
    public function approve(User $u, CreatorApplication $a): bool { return CreatorAbilities::can($this->role($u), CreatorAbilities::WRITE); }
}
