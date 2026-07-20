<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Support\FinanceAbilities as Fin;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * صلاحيات الفواتير — على نسق `PayoutPolicy`.
 *
 * الإصدار والتحصيل والإلغاء أفعال مالية تُقصر على الأدوار المالية/الإدارية
 * (`MANAGE_DOCS` تضمّ `finance`)، ولا تُمنح لكل من يملك التحرير.
 */
class InvoicePolicy
{
    private function role(User $user): ?string
    {
        $orgId = TenantContext::organizationId();

        return $orgId ? $user->roleIn($orgId) : null;
    }

    public function viewAny(User $u): bool { return Fin::can($this->role($u), Fin::VIEW); }
    public function view(User $u, Invoice $i): bool { return Fin::can($this->role($u), Fin::VIEW); }

    public function create(User $u): bool { return Fin::can($this->role($u), Fin::INVOICE_MANAGE); }
    public function update(User $u, Invoice $i): bool { return Fin::can($this->role($u), Fin::INVOICE_MANAGE); }

    /** الإصدار والإلغاء — تصرّف في وثيقة مالية. */
    public function manage(User $u, Invoice $i): bool { return Fin::can($this->role($u), Fin::INVOICE_MANAGE); }

    /** قيد التحصيل صلاحية مستقلّة: إقرار بدخول مال لا مجرّد تحرير وثيقة. */
    public function recordPayment(User $u, Invoice $i): bool { return Fin::can($this->role($u), Fin::PAYMENT_RECORD); }

    public function cancel(User $u, Invoice $i): bool { return Fin::can($this->role($u), Fin::CANCEL); }
}
