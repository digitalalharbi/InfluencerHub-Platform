<?php
namespace App\Domain\Requests\Policies;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Support\TenantContext;
/** إدارة طلبات الخدمة من الوكالة. */
class ServiceRequestPolicy {
    private function role(User $user): ?string {
        $orgId = TenantContext::organizationId();
        return $orgId ? $user->roleIn($orgId) : null;
    }
    public function viewAny(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function view(User $u, ServiceRequest $s): bool { return CrmAbilities::can($this->role($u), CrmAbilities::VIEW); }
    public function handle(User $u, ServiceRequest $s): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }

    /**
     * تسجيل طلب نيابةً عن العميل — كتابة كغيرها.
     * كانت القدرة غير معرَّفة، والنظام يرفض افتراضيًّا، فاختفى الإجراء من
     * الواجهة ولم يكن ممكنًا أصلًا: طابور طلبات بلا مدخل.
     */
    public function create(User $u): bool { return CrmAbilities::can($this->role($u), CrmAbilities::WRITE); }
}
