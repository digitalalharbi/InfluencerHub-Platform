<?php
namespace App\Domain\Content\Policies;
use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
class ContentPolicy {
    private function role(User $user): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $user->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function view(User $u, ContentItem $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function review(User $u, ContentItem $c): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
}
