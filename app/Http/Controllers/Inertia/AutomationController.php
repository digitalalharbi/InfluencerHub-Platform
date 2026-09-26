<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Automation\DefaultAutomationRules;
use App\Domain\Automation\Models\AutomationRule;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * إدارة الأتمتة (React/Inertia) — للإداريين. يعرض القواعد (محفّز/شروط/إجراءات/
 * آخر تشغيلة) وسجلّ التشغيلات، ويسمح بالتفعيل/التعطيل. لا مُنشئ بصري معقّد؛
 * فقط ما يدعمه النموذج فعلًا.
 */
class AutomationController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'agency_admin', 'operations_manager'];

    private const TRIGGER_LABEL = [
        'service_request.created' => 'إنشاء طلب خدمة', 'service_request.assigned' => 'إسناد طلب',
        'content.approved' => 'اعتماد محتوى', 'content.submitted' => 'تقديم محتوى',
        'content.revision_requested' => 'طلب تعديل محتوى', 'creator.declined' => 'اعتذار مبدع',
    ];

    private const ACTION_LABEL = ['notify' => 'إشعار', 'create_task' => 'إنشاء مهمة', 'escalate' => 'تصعيد'];

    // وصف بلغة المستخدم لكل محفّز: «متى» يعمل التشغيل — لا مصطلحات أحداث تقنية.
    private const TRIGGER_DESC = [
        'service_request.created' => 'عند إنشاء طلب خدمة جديد',
        'service_request.assigned' => 'عند إسناد طلب إلى عضو',
        'content.approved' => 'عند اعتماد محتوى',
        'content.submitted' => 'عند تقديم محتوى للمراجعة',
        'content.revision_requested' => 'عند طلب تعديل على محتوى',
        'creator.declined' => 'عند اعتذار مبدع عن التعاون',
    ];

    // وصف بلغة المستخدم لكل إجراء: «ماذا» يحدث.
    private const ACTION_DESC = ['notify' => 'يُرسَل إشعار للمعنيّ', 'create_task' => 'تُنشأ مهمة متابعة', 'escalate' => 'يُصعَّد الأمر للمسؤول'];

    /**
     * التذكيرات الزمنيّة المجدولة (أوامر مجدولة تعمل دوريًّا، لا قواعد أحداث).
     * كلٌّ يعتمد تاريخًا حقيقيًّا ويكتب سجلّ تدقيق عند إطلاقه — منه نشتقّ العدّ وآخر تنفيذ.
     */
    private const SCHEDULED_REMINDERS = [
        ['key' => 'invoice_overdue', 'action' => 'invoice.overdue_notified', 'schedule' => 'يوميًّا',
            'desc' => 'إذا تأخّرت فاتورة مُصدَرة عن موعد استحقاقها ← تذكير المسؤولين لمتابعة التحصيل'],
        ['key' => 'content_publishing', 'action' => 'content.publish_reminded', 'schedule' => 'كل ساعة',
            'desc' => 'إذا اقترب موعد نشر محتوى مُجدوَل أو فات دون نشر ← تذكير المبدع وصاحب الحملة'],
        ['key' => 'creator_response', 'action' => 'collaboration.response_reminded', 'schedule' => 'كل ساعة',
            'desc' => 'إذا لم يردّ المؤثر على عرض التعاون خلال ٤٨ ساعة ← تذكيره وصاحب العرض'],
        ['key' => 'client_decision', 'action' => 'shortlist.decision_reminded', 'schedule' => 'كل ساعة',
            'desc' => 'إذا لم يبتّ العميل في الترشيح خلال ٧٢ ساعة ← تذكير العميل والوكالة'],
        ['key' => 'contract_signature', 'action' => 'contract.signature_reminded', 'schedule' => 'كل ساعة',
            'desc' => 'إذا لم يوقّع الطرف العقد المُرسَل خلال ٧٢ ساعة ← تذكير الطرف وصاحب العقد'],
        ['key' => 'sla', 'action' => 'sla.breach', 'schedule' => 'كل ساعة',
            'desc' => 'إذا تجاوز طلب خدمة موعد استحقاقه ← رصد التجاوز وإشعار المسؤولين'],
    ];

    /** جملة «متى → ماذا» مقروءة للإنسان من محفّز القاعدة وأول إجراء فيها. */
    private function humanDescription(AutomationRule $rule): string
    {
        $when = self::TRIGGER_DESC[$rule->trigger] ?? (self::TRIGGER_LABEL[$rule->trigger] ?? $rule->trigger);
        $firstAction = collect($rule->actions ?? [])->first()['type'] ?? null;
        $then = $firstAction ? (self::ACTION_DESC[$firstAction] ?? null) : null;

        return $then ? "{$when} ← {$then}" : $when;
    }

    private function gate(Request $r): void
    {
        $u = $r->user();
        $oid = TenantContext::organizationId();
        abort_unless($u->is_system_admin || ($oid && in_array($u->roleIn($oid), self::ADMIN_ROLES, true)), 403);
    }

    public function index(Request $r): Response
    {
        $this->gate($r);
        // تثبيت الافتراضيات حتى تظهر أوّل مرة
        app(DefaultAutomationRules::class)->ensure(TenantContext::tenantId());

        $lastRuns = AutomationRun::whereNotNull('rule_id')->where('status', 'executed')
            ->get(['rule_id', 'created_at'])->groupBy('rule_id')->map(fn ($g) => $g->max('created_at'));

        // إحصاء التشغيل لكل قاعدة (تنفيذ/فشل) — تجميع واحد بلا N+1.
        $runStats = AutomationRun::whereNotNull('rule_id')
            ->selectRaw('rule_id, count(*) filter (where status = \'executed\') as executed, count(*) filter (where status = \'failed\') as failed')
            ->groupBy('rule_id')->get()->keyBy('rule_id');

        $rules = AutomationRule::orderByDesc('is_system')->orderBy('priority')->get()->map(fn (AutomationRule $rule) => [
            'id' => $rule->id, 'name' => $rule->name, 'key' => $rule->key,
            'trigger' => $rule->trigger, 'triggerLabel' => self::TRIGGER_LABEL[$rule->trigger] ?? $rule->trigger,
            'description' => $this->humanDescription($rule),
            'enabled' => $rule->enabled, 'isSystem' => $rule->is_system,
            'conditions' => $rule->conditions ?? [],
            'actions' => collect($rule->actions ?? [])->map(fn ($a) => self::ACTION_LABEL[$a['type'] ?? ''] ?? ($a['type'] ?? '?'))->values(),
            'lastRun' => optional($lastRuns[$rule->id] ?? null)?->format('Y-m-d H:i'),
            'runCount' => (int) ($runStats[$rule->id]->executed ?? 0),
            'failures' => (int) ($runStats[$rule->id]->failed ?? 0),
        ]);

        $runs = AutomationRun::with([])->latest('id')->limit(40)->get()->map(fn (AutomationRun $x) => [
            'id' => $x->id, 'trigger' => self::TRIGGER_LABEL[$x->trigger] ?? $x->trigger,
            'status' => $x->status, 'eventKey' => $x->event_key,
            'actions' => collect($x->result ?? [])->map(fn ($rr) => $rr['type'] ?? '?')->values(),
            'error' => $x->error, 'at' => $x->created_at?->format('Y-m-d H:i'),
        ]);

        // التذكيرات المجدولة — العدّ وآخر تنفيذ من سجلّ التدقيق (لكلّ إجراء) لهذا المستأجر.
        $reminderActions = array_column(self::SCHEDULED_REMINDERS, 'action');
        $reminderStats = AuditLog::where('tenant_id', TenantContext::tenantId())
            ->whereIn('action', $reminderActions)
            ->selectRaw('action, count(*) as c, max(created_at) as last')
            ->groupBy('action')->get()->keyBy('action');

        $scheduledReminders = collect(self::SCHEDULED_REMINDERS)->map(function (array $rm) use ($reminderStats) {
            $stat = $reminderStats[$rm['action']] ?? null;

            return [
                'key' => $rm['key'], 'description' => $rm['desc'], 'schedule' => $rm['schedule'],
                'count' => (int) ($stat->c ?? 0),
                'lastRun' => $stat?->last ? Carbon::parse($stat->last)->format('Y-m-d H:i') : null,
            ];
        })->values();

        return Inertia::render('Automation/Index', ['rules' => $rules, 'runs' => $runs, 'scheduledReminders' => $scheduledReminders]);
    }

    public function toggle(Request $r, int $rule): RedirectResponse
    {
        $this->gate($r);
        $m = AutomationRule::findOrFail($rule);
        $m->update(['enabled' => ! $m->enabled]);
        AuditLogger::log('automation.rule_toggled', $m, ['enabled' => $m->enabled], $m->tenant_id, $r->user()->id);

        return back()->with('ok', $m->enabled ? 'فُعّلت القاعدة.' : 'عُطّلت القاعدة.');
    }

    public function update(Request $r, int $rule): RedirectResponse
    {
        $this->gate($r);
        $data = $r->validate(['name' => 'required|string|max:160', 'priority' => 'required|integer|min:1|max:999']);
        $m = AutomationRule::findOrFail($rule);
        $m->update($data);

        return back()->with('ok', 'حُدّثت القاعدة.');
    }
}
