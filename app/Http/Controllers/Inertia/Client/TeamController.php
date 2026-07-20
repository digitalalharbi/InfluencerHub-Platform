<?php

namespace App\Http\Controllers\Inertia\Client;

use App\Domain\CRM\Actions\{ChangeClientMemberRole, ChangeClientMemberStatus, InviteClientMember};
use App\Domain\CRM\Enums\ClientMemberRole;
use App\Domain\CRM\Models\{ClientMember, ClientMemberInvitation};
use App\Domain\CRM\Support\ClientPortalAbilities;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * فريق العميل (React/Inertia) — أعضاء + دعوات + إدارة الأدوار/الحالة (client_admin).
 * يحمي آخر مدير نشِط. معزول على العميل النشِط.
 */
class TeamController extends Controller
{
    private const ROLE_LABEL = [
        'client_admin' => 'مدير الحساب', 'client_campaign_manager' => 'مدير حملات', 'client_content_reviewer' => 'مراجع محتوى',
        'client_finance' => 'مالية', 'client_report_viewer' => 'مشاهدة التقارير', 'client_member' => 'عضو',
    ];

    private function canManage(Request $r): bool
    {
        return ClientPortalAbilities::can($r->attributes->get('clientMembership')->role, ClientPortalAbilities::MANAGE_TEAM);
    }

    public function index(Request $r): Response
    {
        $c = $r->attributes->get('activeClient');
        $meId = $r->attributes->get('clientMembership')->id;
        $members = ClientMember::with('user')->where('client_id', $c->id)->orderByRaw("status='active' desc")->orderBy('id')->get()
            ->map(fn (ClientMember $m) => [
                'id' => $m->id, 'name' => $m->user?->name ?? '—', 'email' => $m->user?->email,
                'role' => $m->role, 'roleLabel' => self::ROLE_LABEL[$m->role] ?? $m->role,
                'status' => $m->status, 'statusLabel' => __("statuses.{$m->status}"), 'statusTone' => __("statuses.tone.{$m->status}"),
                'isMe' => $m->id === $meId,
            ]);
        $invites = ClientMemberInvitation::where('client_id', $c->id)->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get()
            ->map(fn ($i) => ['id' => $i->id, 'email' => $i->email, 'role' => $i->role, 'roleLabel' => self::ROLE_LABEL[$i->role] ?? $i->role, 'expires' => $i->expires_at?->format('Y-m-d')]);

        return Inertia::render('ClientPortal/Team/Index', [
            'clientName' => $c->display_name,
            'members' => $members,
            'invites' => $invites,
            'canManage' => $this->canManage($r),
            'roles' => collect(ClientMemberRole::values())->map(fn ($v) => ['value' => $v, 'label' => self::ROLE_LABEL[$v] ?? $v])->values(),
        ]);
    }

    public function invite(Request $r, InviteClientMember $action)
    {
        abort_unless($this->canManage($r), 403);
        $data = $r->validate(['email' => 'required|email|max:160', 'role' => 'required|string']);
        try {
            [, $raw] = $action->handle($r->attributes->get('activeClient'), $data['email'], $data['role'], $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['team' => $e->getMessage()]);
        }

        // الرمز يُعرض مرة واحدة فقط ولا يُخزَّن خامًا — بلا هذا لا وسيلة لتسليمه للعضو
        return back()->with('ok', 'أُرسلت الدعوة.')->with('invite_token', $raw);
    }

    public function changeRole(Request $r, int $member, ChangeClientMemberRole $action)
    {
        abort_unless($this->canManage($r), 403);
        $data = $r->validate(['role' => 'required|string']);
        $m = $this->memberOf($r, $member);
        if ($this->wouldRemoveLastAdmin($m, $data['role'])) return back()->withErrors(['team' => 'لا يمكن إزالة آخر مدير للعميل.']);
        try { TenantContext::withTenant($m->tenant_id, fn () => $action->handle($m, $data['role'], $r->user())); }
        catch (\RuntimeException $e) { return back()->withErrors(['team' => $e->getMessage()]); }
        return back()->with('ok', 'حُدّث دور العضو.');
    }

    public function changeStatus(Request $r, int $member, ChangeClientMemberStatus $action)
    {
        abort_unless($this->canManage($r), 403);
        $data = $r->validate(['action' => 'required|in:suspend,reactivate,revoke']);
        $m = $this->memberOf($r, $member);
        if (in_array($data['action'], ['suspend', 'revoke'], true) && $m->role === 'client_admin' && $this->wouldRemoveLastAdmin($m, null)) {
            return back()->withErrors(['team' => 'لا يمكن تعليق/إزالة آخر مدير نشِط للعميل.']);
        }
        try { TenantContext::withTenant($m->tenant_id, fn () => $action->handle($m, $data['action'], $r->user())); }
        catch (\RuntimeException $e) { return back()->withErrors(['team' => $e->getMessage()]); }
        return back()->with('ok', 'حُدّثت حالة العضو.');
    }

    private function wouldRemoveLastAdmin(ClientMember $m, ?string $newRole): bool
    {
        if ($m->role !== 'client_admin' || $m->status !== 'active') return false;
        if ($newRole === 'client_admin') return false;
        $activeAdmins = ClientMember::where('client_id', $m->client_id)->where('role', 'client_admin')->where('status', 'active')->count();
        return $activeAdmins <= 1;
    }

    private function memberOf(Request $r, int $id): ClientMember
    {
        $c = $r->attributes->get('activeClient');
        $m = ClientMember::where('id', $id)->where('client_id', $c->id)->first();
        abort_unless($m, 404);
        return $m;
    }
}
