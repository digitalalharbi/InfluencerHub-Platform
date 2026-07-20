<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\Payout;
use App\Domain\Finance\Support\FinanceAbilities as Fin;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * صلاحيات المستحقات — مفصولة بالفعل لا بمجموعة واحدة.
 *
 * كانت كلها `MANAGE_DOCS`: مدير الحملة يطلب ويعتمد ويسجّل الصرف وحده. إدارة
 * مستند تُصحَّح بتعديل، أمّا إقرار خروج مال فلا يُسترجَع — فلا يجوز أن يكونا
 * صلاحية واحدة.
 *
 * التقسيم: مَن يطلب لا يعتمد، والصرف مقصور على المالية والإدارة العليا.
 */
class PayoutPolicy
{
    private function role(User $user): ?string
    {
        $orgId = TenantContext::organizationId();

        return $orgId ? $user->roleIn($orgId) : null;
    }

    public function viewAny(User $u): bool { return Fin::can($this->role($u), Fin::VIEW); }
    public function view(User $u, Payout $p): bool { return Fin::can($this->role($u), Fin::VIEW); }

    /** طلب مستحق — مدير الحملة يعرف ما أُنجز فيطلب. */
    public function create(User $u): bool { return Fin::can($this->role($u), Fin::PAYOUT_REQUEST); }

    /** تعديل الطلب ما دام معلّقًا. */
    public function update(User $u, Payout $p): bool
    {
        return $p->isEditable() && Fin::can($this->role($u), Fin::PAYOUT_REQUEST);
    }

    /**
     * الاعتماد: صلاحية مالية + فصل الواجبات.
     * مَن أنشأ الطلب لا يعتمده إلا إن كان دوره مستثنًى صراحةً.
     */
    public function approve(User $u, Payout $p): bool
    {
        $role = $this->role($u);
        if (! Fin::can($role, Fin::PAYOUT_APPROVE)) {
            return false;
        }
        if ((int) $p->created_by === (int) $u->id) {
            return Fin::mayApproveOwnRequest($role);
        }

        return true;
    }

    /** الجدولة والإرسال للمزوّد — خطوات تنفيذية بعد الاعتماد. */
    public function schedule(User $u, Payout $p): bool { return Fin::can($this->role($u), Fin::PAYOUT_APPROVE); }

    /** تسجيل الصرف: الفعل الذي لا رجعة فيه. */
    public function markPaid(User $u, Payout $p): bool { return Fin::can($this->role($u), Fin::PAYOUT_MARK_PAID); }

    public function cancel(User $u, Payout $p): bool { return Fin::can($this->role($u), Fin::CANCEL); }

    /**
     * بوّابة عامّة للأفعال — تُبقي المتحكّم بسيطًا وتوجّه كل فعل إلى قاعدته.
     * الفعل المجهول يُرفض: لا يُسمح بما لم يُصرَّح به.
     */
    public function act(User $u, Payout $p, string $action): bool
    {
        return match ($action) {
            'approve' => $this->approve($u, $p),
            'schedule', 'send-to-provider' => $this->schedule($u, $p),
            'mark-paid', 'mark-failed' => $this->markPaid($u, $p),
            'cancel' => $this->cancel($u, $p),
            default => false,
        };
    }
}
