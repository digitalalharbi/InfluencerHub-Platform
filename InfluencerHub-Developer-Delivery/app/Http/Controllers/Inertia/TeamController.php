<?php

namespace App\Http\Controllers\Inertia;

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
 * الفريق (React/Inertia) — أعضاء مساحة العمل وأدوارهم وحِملهم التشغيلي الفعلي.
 * بوابة على مستوى الإدارة (بيانات الفريق حسّاسة). معزول بالمؤسسة الحالية.
 */
class TeamController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'agency_admin', 'operations_manager'];

    /** أسماء الأدوار بالعربية — لا مفاتيح داخلية في الواجهة. */
    public const ROLE_LABEL = [
        'super_admin' => 'مدير عام', 'agency_admin' => 'مدير الوكالة', 'operations_manager' => 'مدير عمليات',
        'campaign_manager' => 'مدير حملات', 'creator_manager' => 'مسؤول مبدعين', 'content_reviewer' => 'مراجع محتوى',
        'finance' => 'مالية', 'agency_employee' => 'موظف', 'viewer' => 'مُطّلع',
    ];

    public function index(Request $r): Response
    {
        $orgId = TenantContext::organizationId();
        /** @var User $user */
        $user = $r->user();
        abort_unless($orgId && in_array($user->roleIn($orgId), self::ADMIN_ROLES, true), 403);

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
        ]);
    }
}
