<?php

namespace App\Support\Dashboard;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\{Brand, ClientDocument, ClientProfileChangeRequest};
use App\Domain\Creators\Models\CreatorApplication;
use App\Domain\Finance\Models\Payout;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\OrganizationMembership;
use Illuminate\Support\Carbon;

/**
 * مُركِّب لوحة التشغيل — يبني "مساحة عملي" و"المطلوب مني الآن" ولقطة الفريق
 * من بيانات PostgreSQL الحقيقية، مُنطّقة بالمستأجر ومحكومة بالصلاحيات (deny-by-default).
 * كل عنصر عمل يقود إلى إجراء مباشر برابط. لا أرقام زخرفية. لا N+1 (تجميعات على مستوى الاستعلام).
 */
class OperationalDashboard
{
    // مجموعات القدرة (تُطابق منطق السياسات) — الأدوار المذكورة فقط تُمنح.
    private const CONTENT_REVIEW = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'content_reviewer'];
    private const BRAND_REVIEW = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager'];
    private const CLIENT_REVIEW = ['super_admin', 'agency_admin', 'operations_manager'];
    private const FINANCE = ['super_admin', 'agency_admin', 'operations_manager', 'finance'];
    private const CREATOR_MGMT = ['super_admin', 'agency_admin', 'operations_manager', 'creator_manager'];
    private const CAMPAIGN_VIEW = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager', 'agency_employee', 'creator_manager', 'content_reviewer', 'finance', 'viewer'];
    private const TEAM_VIEW = ['super_admin', 'agency_admin', 'operations_manager'];

    private const ROLE_LABEL = [
        'agency_admin' => 'مدير الوكالة', 'operations_manager' => 'مدير العمليات',
        'campaign_manager' => 'مدير حملات', 'creator_manager' => 'مسؤول مبدعين',
        'content_reviewer' => 'مراجع محتوى', 'finance' => 'مالية',
        'agency_employee' => 'موظف', 'super_admin' => 'مدير عام',
    ];

    // ترتيب الأولوية: كلما صغر الرقم زادت الأولوية.
    private const PRIO = ['overdue' => 0, 'critical' => 1, 'today' => 2, 'approval' => 3, 'soon' => 4, 'normal' => 5];
    private const PRIO_LABEL = ['overdue' => 'متأخر', 'critical' => 'حرج', 'today' => 'مستحق اليوم', 'approval' => 'بانتظار موافقتك', 'soon' => 'مستحق قريبًا', 'normal' => 'متابعة'];

    public function __construct(private User $user, private int $orgId)
    {
    }

    public function role(): ?string
    {
        return $this->user->roleIn($this->orgId);
    }

    private function can(array $set): bool
    {
        $r = $this->role();
        return $r !== null && in_array($r, $set, true);
    }

    /** حمولة لوحة التشغيل الكاملة (حسب الدور/الصلاحية). */
    public function compose(): array
    {
        $work = $this->myWork();
        $canTeam = $this->can(self::TEAM_VIEW);

        return [
            'role' => $this->role(),
            'canSeeTeam' => $canTeam,
            'brief' => $this->brief($work),
            'myWork' => $work,
            'team' => $canTeam ? $this->team() : null,
        ];
    }

    /** قائمة "المطلوب مني الآن" مرتّبة حسب الأولوية. */
    private function myWork(): array
    {
        $items = [];
        $now = Carbon::now();

        // 1) طلبات الخدمة المسندة إليّ (عناصر فردية بمهلة/SLA)
        $mine = ServiceRequest::query()
            ->whereIn('status', ServiceRequest::OPEN_STATUSES)
            ->where('assigned_to', $this->user->id)
            ->orderByRaw('due_at asc nulls last')->limit(6)->get();
        foreach ($mine as $sr) {
            $prio = 'normal';
            $due = $sr->due_at;
            if ($sr->sla_breached_at || ($due && $due->isPast())) $prio = 'overdue';
            elseif ($due && $due->isToday()) $prio = 'today';
            elseif ($due && $due->lte($now->copy()->addDays(2))) $prio = 'soon';
            $items[] = $this->item(
                key: 'sr-' . $sr->id,
                title: $sr->title ?: ('طلب ' . $sr->request_number),
                entity: 'طلب خدمة · ' . $sr->request_number,
                reason: $sr->sla_breached_at ? 'تجاوز مهلة SLA' : 'طلب مسند إليك',
                prio: $prio,
                due: $due,
                actionLabel: 'فتح الطلب',
                href: "/app/service-requests/{$sr->id}",
                sla: (bool) $sr->sla_breached_at,
            );
        }

        // 2) طوابير الموافقة المجمّعة (كل منها محكوم بصلاحية)
        // العدّادات نفسها التي تُغذّي شارات القائمة — تُقرأ من ذاكرتها بدل
        // إعادة تنفيذ الاستعلامات ذاتها مرّة ثانية في الطلب الواحد.
        $badges = \App\Support\Navigation\NavigationBadges::all();
        if ($this->can(self::CONTENT_REVIEW)) {
            $n = (int) ($badges['content'] ?? 0);
            if ($n > 0) $items[] = $this->group('content', 'محتوى بانتظار مراجعتك', "$n عنصر مُرسَل للوكالة", 'approval', $n, 'راجع المحتوى', '/app/content');
        }
        if ($this->can(self::BRAND_REVIEW)) {
            $n = (int) ($badges['brand_reviews'] ?? 0);
            if ($n > 0) $items[] = $this->group('brands', 'علامات بانتظار الاعتماد', "$n علامة مُرسَلة", 'approval', $n, 'اعتماد العلامات', '/app/brand-reviews');
        }
        if ($this->can(self::CLIENT_REVIEW)) {
            $n = (int) ($badges['client_reviews'] ?? 0);
            if ($n > 0) $items[] = $this->group('client_reviews', 'مراجعات العملاء', "$n تغيير/مستند بانتظار المراجعة", 'approval', $n, 'مراجعة', '/app/client-reviews');
        }
        if ($this->can(self::FINANCE)) {
            $n = Payout::where('status', 'pending')->count();
            if ($n > 0) $items[] = $this->group('payouts', 'مستحقات بانتظار الاعتماد', "$n دفعة تحتاج اعتمادك", 'approval', $n, 'اعتماد الصرف', '/app/payouts');
        }
        if ($this->can(self::CREATOR_MGMT)) {
            $n = (int) ($badges['creator_applications'] ?? 0);
            if ($n > 0) $items[] = $this->group('applications', 'طلبات انضمام المبدعين', "$n طلب جديد", 'approval', $n, 'مراجعة الطلبات', '/app/creator-applications');
        }

        // 3) حملات متأخرة (خطر تشغيلي)
        if ($this->can(self::CAMPAIGN_VIEW)) {
            $late = Campaign::query()->whereIn('status', ['active', 'paused'])
                ->whereNotNull('end_date')->whereDate('end_date', '<', $now)->count();
            if ($late > 0) $items[] = $this->group('late_campaigns', 'حملات متأخرة عن الموعد', "$late حملة تجاوزت تاريخ الانتهاء", 'critical', $late, 'عرض الحملات', '/app/campaigns?seg=late');
        }

        usort($items, fn ($a, $b) => [$a['prioRank'], $a['dueTs'] ?? PHP_INT_MAX] <=> [$b['prioRank'], $b['dueTs'] ?? PHP_INT_MAX]);
        return $items;
    }

    private function item(string $key, string $title, string $entity, string $reason, string $prio, ?Carbon $due, string $actionLabel, string $href, bool $sla = false, ?int $count = null): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'entity' => $entity,
            'reason' => $reason,
            'prio' => $prio,
            'prioLabel' => self::PRIO_LABEL[$prio],
            'prioRank' => self::PRIO[$prio],
            'due' => $due?->format('Y-m-d'),
            'dueTs' => $due?->timestamp,
            'sla' => $sla,
            'count' => $count,
            'actionLabel' => $actionLabel,
            'href' => $href,
        ];
    }

    private function group(string $key, string $title, string $reason, string $prio, int $count, string $actionLabel, string $href): array
    {
        return $this->item($key, $title, 'طابور تشغيلي', $reason, $prio, null, $actionLabel, $href, false, $count);
    }

    /** الملخّص اليومي — أرقام حقيقية مصدرها عناصر العمل نفسها. */
    private function brief(array $work): array
    {
        $tasks = 0; $approvals = 0; $overdue = 0;
        foreach ($work as $w) {
            $c = $w['count'] ?? 1;
            if ($w['prio'] === 'overdue' || $w['prio'] === 'critical') $overdue += $c;
            elseif ($w['prio'] === 'approval') $approvals += $c;
            else $tasks += $c;
        }
        return [
            'tasks' => $tasks,
            'approvals' => $approvals,
            'overdue' => $overdue,
            'total' => count($work),
        ];
    }

    /** لقطة الفريق (للمديرين فقط): توزيع ضغط العمل من طلبات الخدمة المفتوحة. */
    private function team(): array
    {
        // أعباء مفتوحة مسندة لكل عضو (تجميع على مستوى الاستعلام — بلا N+1)
        $openByUser = ServiceRequest::query()->whereIn('status', ServiceRequest::OPEN_STATUSES)
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to as uid, count(*) as total, count(*) filter (where sla_breached_at is not null) as breached')
            ->groupBy('assigned_to')->get()->keyBy('uid');

        $memberIds = OrganizationMembership::withoutGlobalScopes()
            ->where('organization_id', $this->orgId)->where('status', 'active')
            ->whereNotIn('role', ['viewer'])
            ->pluck('role', 'user_id');

        $users = User::whereIn('id', $memberIds->keys())->get(['id', 'name'])->keyBy('id');
        $members = [];
        foreach ($memberIds as $uid => $role) {
            $row = $openByUser[$uid] ?? null;
            $members[] = [
                'id' => $uid,
                'name' => $users[$uid]->name ?? '—',
                'role' => self::ROLE_LABEL[$role] ?? $role,
                'open' => (int) ($row->total ?? 0),
                'breached' => (int) ($row->breached ?? 0),
            ];
        }
        usort($members, fn ($a, $b) => $b['open'] <=> $a['open']);

        $unassigned = ServiceRequest::query()->whereIn('status', ServiceRequest::OPEN_STATUSES)->whereNull('assigned_to')->count();
        $breachedTotal = ServiceRequest::query()->whereIn('status', ServiceRequest::OPEN_STATUSES)->whereNotNull('sla_breached_at')->count();

        return [
            'members' => array_slice($members, 0, 8),
            'unassigned' => $unassigned,
            'breached' => $breachedTotal,
        ];
    }
}
