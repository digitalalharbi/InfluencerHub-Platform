<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الفريق (React/Inertia) — أعضاء مساحة العمل وأدوارهم وحِملهم التشغيلي الفعلي،
 * وإدارتهم فعليًا: إضافة عضو، تغيير الدور، تعليق/إعادة تفعيل/إزالة.
 * بوابة على مستوى الإدارة (بيانات الفريق حسّاسة). معزول بالمؤسسة الحالية.
 * كل إجراء يُطبَّق من الخادم ويُدقَّق، ويحمي آخر مدير نشِط.
 */
class TeamController extends Controller
{
    /** أدوار تُدير الفريق (تُطابق SettingsController). */
    private const ADMIN_ROLES = ['super_admin', 'agency_admin', 'operations_manager'];

    /** أدوار «المالك» التي لا يجوز إزالة آخر نشِط منها (قفل خارج المؤسسة). */
    private const OWNER_ROLES = ['super_admin', 'agency_admin'];

    /** أسماء الأدوار بالعربية — لا مفاتيح داخلية في الواجهة. */
    public const ROLE_LABEL = [
        'super_admin' => 'مدير عام', 'agency_admin' => 'مدير الوكالة', 'operations_manager' => 'مدير عمليات',
        'campaign_manager' => 'مدير حملات', 'creator_manager' => 'مسؤول مبدعين', 'content_reviewer' => 'مراجع محتوى',
        'finance' => 'مالية', 'agency_employee' => 'موظف', 'viewer' => 'مُطّلع',
    ];

    /** الأدوار القابلة للإسناد من الواجهة — «مدير عام» مستوى نظام لا يُمنح من مساحة العمل. */
    private const ASSIGNABLE_ROLES = [
        'agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager',
        'content_reviewer', 'finance', 'agency_employee', 'viewer',
    ];

    public function index(Request $r): Response
    {
        $orgId = TenantContext::organizationId();
        /** @var User $user */
        $user = $r->user();
        abort_unless($this->canManage($user, $orgId), 403);

        $members = OrganizationMembership::where('organization_id', $orgId)->get();
        $users = User::whereIn('id', $members->pluck('user_id'))->get()->keyBy('id');

        // الحِمل التشغيلي: الطلبات المفتوحة المُسنَدة لكل عضو (رقم حقيقي)
        $openByUser = ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)
            ->whereNotNull('assigned_to')->get()->groupBy('assigned_to')->map->count();
        $breachedByUser = ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)
            ->whereNotNull('assigned_to')->whereNotNull('sla_breached_at')->get()->groupBy('assigned_to')->map->count();

        $rows = $members->map(function (OrganizationMembership $m) use ($users, $openByUser, $breachedByUser) {
            $u = $users[$m->user_id] ?? null;
            return [
                'id' => $m->id,
                'name' => $u?->name ?? '—',
                'email' => $u?->email,
                'role' => $m->role,
                'roleLabel' => self::ROLE_LABEL[$m->role] ?? $m->role,
                'status' => $m->status,
                'statusLabel' => __("statuses.{$m->status}"),
                'statusTone' => __("statuses.tone.{$m->status}"),
                'open' => (int) ($openByUser[$m->user_id] ?? 0),
                'breached' => (int) ($breachedByUser[$m->user_id] ?? 0),
                'canReview' => CrmAbilities::can($m->role, CrmAbilities::WRITE),
                'isSelf' => $m->user_id === auth()->id(),
            ];
        })->sortBy([['status', 'asc'], ['name', 'asc']])->values();

        $unassigned = ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)->whereNull('assigned_to')->count();

        return Inertia::render('Team/Index', [
            'members' => $rows,
            'summary' => [
                'total' => $rows->count(),
                'active' => $rows->where('status', 'active')->count(),
                'openWork' => (int) $openByUser->sum(),
                'breached' => (int) $breachedByUser->sum(),
                'unassigned' => $unassigned,
            ],
            // توزيع الأدوار — من بيانات فعلية
            'byRole' => $rows->groupBy('role')->map(fn ($g, $role) => [
                'role' => $role, 'label' => self::ROLE_LABEL[$role] ?? $role, 'count' => $g->count(),
            ])->values(),
            // إدارة فعلية: الصلاحية + الأدوار القابلة للإسناد (لبناء عناصر التحكم)
            'canManage' => true,
            'assignableRoles' => collect(self::ASSIGNABLE_ROLES)
                ->map(fn ($v) => ['value' => $v, 'label' => self::ROLE_LABEL[$v] ?? $v])->values(),
        ]);
    }

    /** إضافة عضو موجود إلى مساحة العمل عبر بريده — لا رمز وهمي بلا مسار قبول. */
    public function invite(Request $r)
    {
        $orgId = TenantContext::organizationId();
        abort_unless($this->canManage($r->user(), $orgId), 403);
        $data = $r->validate([
            'email' => 'required|email|max:160',
            'role' => 'required|string',
        ]);
        if (! in_array($data['role'], self::ASSIGNABLE_ROLES, true)) {
            return back()->withErrors(['team' => 'دور غير قابل للإسناد.']);
        }

        $target = User::whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->first();
        if (! $target) {
            return back()->withErrors(['team' => 'لا يوجد مستخدم بهذا البريد الإلكتروني. اطلبوا منه إنشاء حساب أولًا ثم أعيدوا المحاولة.']);
        }

        $existing = OrganizationMembership::where('organization_id', $orgId)
            ->where('user_id', $target->id)->first();

        if ($existing && $existing->status === 'active') {
            return back()->withErrors(['team' => 'هذا المستخدم عضو نشِط بالفعل في مساحة العمل.']);
        }

        if ($existing) {
            // إعادة تفعيل عضو مُعلَّق/مدعوٍّ مع الدور المختار
            $from = $existing->role . '/' . $existing->status;
            $existing->update(['role' => $data['role'], 'status' => 'active']);
            $target->forgetRoleCache();
            AuditLogger::log('org_member.reactivated', $existing, ['from' => $from, 'to' => $data['role'] . '/active'], TenantContext::tenantId());
            return back()->with('ok', 'أُعيد تفعيل العضو في مساحة العمل.');
        }

        $m = OrganizationMembership::create([
            'tenant_id' => TenantContext::tenantId(),
            'organization_id' => $orgId,
            'user_id' => $target->id,
            'role' => $data['role'],
            'status' => 'active',
        ]);
        $target->forgetRoleCache();
        AuditLogger::log('org_member.added', $m, ['email' => $target->email, 'role' => $data['role']], TenantContext::tenantId());

        return back()->with('ok', 'أُضيف العضو إلى مساحة العمل.');
    }

    public function changeRole(Request $r, int $member)
    {
        $orgId = TenantContext::organizationId();
        abort_unless($this->canManage($r->user(), $orgId), 403);
        $data = $r->validate(['role' => 'required|string']);
        if (! in_array($data['role'], self::ASSIGNABLE_ROLES, true)) {
            return back()->withErrors(['team' => 'دور غير قابل للإسناد.']);
        }
        $m = $this->memberOf($orgId, $member);

        if ($this->wouldRemoveLastOwner($m, $data['role'])) {
            return back()->withErrors(['team' => 'لا يمكن تغيير دور آخر مدير نشِط لمساحة العمل.']);
        }
        if ($m->role === $data['role']) {
            return back()->with('ok', 'لا تغيير — الدور نفسه.');
        }

        $from = $m->role;
        $m->update(['role' => $data['role']]);
        optional(User::find($m->user_id))->forgetRoleCache();
        AuditLogger::log('org_member.role_changed', $m, ['from' => $from, 'to' => $data['role']], $m->tenant_id);

        return back()->with('ok', 'حُدّث دور العضو.');
    }

    public function changeStatus(Request $r, int $member)
    {
        $orgId = TenantContext::organizationId();
        abort_unless($this->canManage($r->user(), $orgId), 403);
        $data = $r->validate(['action' => 'required|in:suspend,reactivate,remove']);
        $m = $this->memberOf($orgId, $member);

        // تعليق/إزالة آخر مالك نشِط = قفل خارج مساحة العمل — ممنوع
        if (in_array($data['action'], ['suspend', 'remove'], true) && $this->wouldRemoveLastOwner($m, null)) {
            return back()->withErrors(['team' => 'لا يمكن تعليق/إزالة آخر مدير نشِط لمساحة العمل.']);
        }

        $user = User::find($m->user_id);
        if ($data['action'] === 'remove') {
            AuditLogger::log('org_member.removed', $m, ['email' => $user?->email, 'role' => $m->role], $m->tenant_id);
            $m->delete();
            optional($user)->forgetRoleCache();
            return back()->with('ok', 'أُزيل العضو من مساحة العمل.');
        }

        $to = $data['action'] === 'suspend' ? 'suspended' : 'active';
        $from = $m->status;
        $m->update(['status' => $to]);
        optional($user)->forgetRoleCache();
        AuditLogger::log("org_member.{$data['action']}", $m, ['from' => $from, 'to' => $to], $m->tenant_id);

        return back()->with('ok', $to === 'suspended' ? 'عُلِّق العضو.' : 'أُعيد تفعيل العضو.');
    }

    private function canManage(?User $user, ?int $orgId): bool
    {
        return (bool) ($user && $orgId && in_array($user->roleIn($orgId), self::ADMIN_ROLES, true));
    }

    /** يمنع نزع آخر مالك نشِط (تغيير دوره لغير مالك، أو تعليقه/إزالته). */
    private function wouldRemoveLastOwner(OrganizationMembership $m, ?string $newRole): bool
    {
        if (! in_array($m->role, self::OWNER_ROLES, true) || $m->status !== 'active') {
            return false;
        }
        if ($newRole !== null && in_array($newRole, self::OWNER_ROLES, true)) {
            return false; // يبقى مالكًا
        }
        $activeOwners = OrganizationMembership::where('organization_id', $m->organization_id)
            ->whereIn('role', self::OWNER_ROLES)->where('status', 'active')->count();

        return $activeOwners <= 1;
    }

    private function memberOf(int $orgId, int $id): OrganizationMembership
    {
        $m = OrganizationMembership::where('id', $id)->where('organization_id', $orgId)->first();
        abort_unless($m, 404);

        return $m;
    }
}
