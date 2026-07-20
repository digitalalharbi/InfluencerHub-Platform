<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Creators\Models\{Creator, CreatorInvitation};
use App\Domain\Creators\Services\CreatorInvitationService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Http\MountPrefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** دعوة صانع محتوى موجود إلى بوابته (جانب الوكالة). */
class CreatorInvitationController extends Controller
{
    public function __construct(private CreatorInvitationService $svc) {}

    public function store(Request $r, Creator $creator): RedirectResponse
    {
        $this->authorize('update', $creator);
        $data = $r->validate([
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:32',
        ]);

        try {
            [, $raw] = $this->svc->invite($creator, $data['email'], $data['phone'] ?? null, $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        // الرمز يُعرض مرّة واحدة: لا يُخزَّن خامًا فلا سبيل لعرضه لاحقًا
        return back()->with('ok', 'أُنشئت الدعوة.')->with('invitation_link', $this->link($r, $raw));
    }

    public function resend(Request $r, CreatorInvitation $invitation): RedirectResponse
    {
        $this->authorizeInvitation($r, $invitation);

        try {
            [, $raw] = $this->svc->resend($invitation, $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        return back()->with('ok', 'أُعيد إرسال الدعوة برمز جديد.')->with('invitation_link', $this->link($r, $raw));
    }

    public function revoke(Request $r, CreatorInvitation $invitation): RedirectResponse
    {
        $this->authorizeInvitation($r, $invitation);

        try {
            $this->svc->revoke($invitation, $r->user(), $r->input('reason'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        return back()->with('ok', 'أُلغيت الدعوة.');
    }

    /**
     * الدعوة تخصّ صانع محتوى في مستأجر المستخدم — لا تُفتح بتبديل الرقم.
     *
     * **لا يُمَسّ سياق الطلب هنا.** الوسيط ضبط المستأجر *والمؤسسة*، وضبطُ
     * المستأجر يدويًّا بوسيط واحد يكتب الأوّل ويمسح الثانية — فيعود
     * `roleIn($orgId)` فارغًا ويُردّ كل أحد 403. والسجلّ وصل عبر ربط المسار
     * تحت النطاق الصحيح أصلًا، فيكفي التحقّق أنه في مستأجر المستخدم.
     */
    private function authorizeInvitation(Request $r, CreatorInvitation $invitation): void
    {
        abort_unless((int) $invitation->tenant_id === (int) TenantContext::tenantId(), 404);

        $creator = Creator::find($invitation->creator_id);
        abort_unless($creator, 404);
        $this->authorize('update', $creator);
    }

    private function link(Request $r, string $raw): string
    {
        return url("/creator/invitation/{$raw}");
    }
}
