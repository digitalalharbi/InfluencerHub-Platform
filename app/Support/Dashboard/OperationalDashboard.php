<?php

namespace App\Support\Dashboard;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Campaigns\Models\CampaignShortlistVersion;
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
    // من يُدير الترشيح فعليًّا (إرسال/بديل/تحويل) — لا المُطّلع ولا المالية
    private const NOMINATION = ['super_admin', 'agency_admin', 'operations_manager', 'campaign_manager'];
    private const TEAM_VIEW = ['super_admin', 'agency_admin', 'operations_manager'];

    // أدوار الفريق — التسمية تُحلّ باللغة الحالية عبر trans('dashboard.role_*').
    private const ROLES = ['agency_admin', 'operations_manager', 'campaign_manager', 'creator_manager', 'content_reviewer', 'finance', 'agency_employee', 'super_admin'];

    // ترتيب الأولوية: كلما صغر الرقم زادت الأولوية.
    private const PRIO = ['overdue' => 0, 'critical' => 1, 'today' => 2, 'approval' => 3, 'soon' => 4, 'normal' => 5];

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

    /**
     * «عملي» الشخصي (بلا لقطة الفريق) — نقطة البداية اليومية. المصدر نفسه الذي
     * تستعمله اللوحة، فلا تتفرّع نسختان من «المطلوب مني الآن».
     * @return array{role: ?string, brief: array, myWork: array}
     */
    public function personalWork(): array
    {
        $work = $this->myWork();
        return ['role' => $this->role(), 'brief' => $this->brief($work), 'myWork' => $work];
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
                title: $sr->title ?: trans('dashboard.sr_title_fallback', ['number' => $sr->request_number]),
                entity: trans('dashboard.entity_service_request', ['number' => $sr->request_number]),
                reason: $sr->sla_breached_at ? trans('dashboard.sr_reason_sla') : trans('dashboard.sr_reason_assigned'),
                prio: $prio,
                due: $due,
                actionLabel: trans('dashboard.sr_action'),
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
            if ($n > 0) $items[] = $this->group('content', trans('dashboard.g_content_title'), trans('dashboard.g_content_reason', ['n' => $n]), 'approval', $n, trans('dashboard.g_content_action'), '/app/content');
        }
        if ($this->can(self::BRAND_REVIEW)) {
            $n = (int) ($badges['brand_reviews'] ?? 0);
            if ($n > 0) $items[] = $this->group('brands', trans('dashboard.g_brands_title'), trans('dashboard.g_brands_reason', ['n' => $n]), 'approval', $n, trans('dashboard.g_brands_action'), '/app/brand-reviews');
        }
        if ($this->can(self::CLIENT_REVIEW)) {
            $n = (int) ($badges['client_reviews'] ?? 0);
            if ($n > 0) $items[] = $this->group('client_reviews', trans('dashboard.g_client_reviews_title'), trans('dashboard.g_client_reviews_reason', ['n' => $n]), 'approval', $n, trans('dashboard.g_client_reviews_action'), '/app/client-reviews');
        }
        if ($this->can(self::FINANCE)) {
            $n = Payout::where('status', 'pending')->count();
            if ($n > 0) $items[] = $this->group('payouts', trans('dashboard.g_payouts_title'), trans('dashboard.g_payouts_reason', ['n' => $n]), 'approval', $n, trans('dashboard.g_payouts_action'), '/app/payouts');
        }
        if ($this->can(self::CREATOR_MGMT)) {
            $n = (int) ($badges['creator_applications'] ?? 0);
            if ($n > 0) $items[] = $this->group('applications', trans('dashboard.g_applications_title'), trans('dashboard.g_applications_reason', ['n' => $n]), 'approval', $n, trans('dashboard.g_applications_action'), '/app/creator-applications');
        }

        // 3) حملات متأخرة (خطر تشغيلي)
        if ($this->can(self::CAMPAIGN_VIEW)) {
            $late = Campaign::query()->whereIn('status', ['active', 'paused'])
                ->whereNotNull('end_date')->whereDate('end_date', '<', $now)->count();
            if ($late > 0) $items[] = $this->group('late_campaigns', trans('dashboard.g_late_title'), trans('dashboard.g_late_reason', ['n' => $late]), 'critical', $late, trans('dashboard.g_late_action'), '/app/campaigns?seg=late');
        }

        // 4) مراحل الترشيح — إجراءات حتمية من حالة الإصدار الحالي (لا حالة مُختلَقة).
        // كل عدّاد لقوائم إصدارها الحالي في تلك الحالة؛ لا صفوف = لا عنصر (غير مزعج).
        if ($this->can(self::NOMINATION)) {
            $current = CampaignShortlistVersion::query()
                ->whereIn('status', ['draft', 'changes_requested', 'approved', 'partially_approved'])
                ->whereExists(fn ($q) => $q->from('campaign_shortlists as sl')
                    ->whereColumn('sl.id', 'campaign_shortlist_versions.shortlist_id')
                    ->whereColumn('sl.current_version', 'campaign_shortlist_versions.version'))
                ->get(['id', 'status']);

            $changes = $current->where('status', 'changes_requested')->count();
            if ($changes > 0) $items[] = $this->group('nom_alt', trans('dashboard.g_nom_alt_title'), trans('dashboard.g_nom_alt_reason', ['n' => $changes]), 'today', $changes, trans('dashboard.g_nom_alt_action'), '/app/shortlisting');

            $toConvert = $current->whereIn('status', ['approved', 'partially_approved'])->count();
            if ($toConvert > 0) $items[] = $this->group('nom_convert', trans('dashboard.g_nom_convert_title'), trans('dashboard.g_nom_convert_reason', ['n' => $toConvert]), 'today', $toConvert, trans('dashboard.g_nom_convert_action'), '/app/shortlisting');

            $draftIds = $current->where('status', 'draft')->pluck('id');
            if ($draftIds->isNotEmpty()) {
                $ready = CampaignShortlistItem::whereIn('shortlist_version_id', $draftIds)
                    ->where('is_backup', false)->distinct()->count('shortlist_version_id');
                if ($ready > 0) $items[] = $this->group('nom_send', trans('dashboard.g_nom_send_title'), trans('dashboard.g_nom_send_reason', ['n' => $ready]), 'normal', $ready, trans('dashboard.g_nom_send_action'), '/app/shortlisting');
            }
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
            'prioLabel' => trans('dashboard.prio_' . $prio),
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
        return $this->item($key, $title, trans('dashboard.entity_queue'), $reason, $prio, null, $actionLabel, $href, false, $count);
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
                'role' => in_array($role, self::ROLES, true) ? trans('dashboard.role_' . $role) : $role,
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
