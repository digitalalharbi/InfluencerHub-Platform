<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Automation\Models\{AutomationRule, AutomationRun};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        app(\App\Domain\Automation\DefaultAutomationRules::class)->ensure(TenantContext::tenantId());

        $lastRuns = AutomationRun::whereNotNull('rule_id')->where('status', 'executed')
            ->get(['rule_id', 'created_at'])->groupBy('rule_id')->map(fn ($g) => $g->max('created_at'));

        $rules = AutomationRule::orderByDesc('is_system')->orderBy('priority')->get()->map(fn (AutomationRule $rule) => [
            'id' => $rule->id, 'name' => $rule->name, 'key' => $rule->key,
            'trigger' => $rule->trigger, 'triggerLabel' => self::TRIGGER_LABEL[$rule->trigger] ?? $rule->trigger,
            'enabled' => $rule->enabled, 'isSystem' => $rule->is_system,
            'conditions' => $rule->conditions ?? [],
            'actions' => collect($rule->actions ?? [])->map(fn ($a) => self::ACTION_LABEL[$a['type'] ?? ''] ?? ($a['type'] ?? '?'))->values(),
            'lastRun' => optional($lastRuns[$rule->id] ?? null)?->format('Y-m-d H:i'),
        ]);

        $runs = AutomationRun::with([])->latest('id')->limit(40)->get()->map(fn (AutomationRun $x) => [
            'id' => $x->id, 'trigger' => self::TRIGGER_LABEL[$x->trigger] ?? $x->trigger,
            'status' => $x->status, 'eventKey' => $x->event_key,
            'actions' => collect($x->result ?? [])->map(fn ($rr) => $rr['type'] ?? '?')->values(),
            'error' => $x->error, 'at' => $x->created_at?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Automation/Index', ['rules' => $rules, 'runs' => $runs]);
    }

    public function toggle(Request $r, int $rule): RedirectResponse
    {
        $this->gate($r);
        $m = AutomationRule::findOrFail($rule);
        $m->update(['enabled' => ! $m->enabled]);
        \App\Domain\Audit\Services\AuditLogger::log('automation.rule_toggled', $m, ['enabled' => $m->enabled], $m->tenant_id, $r->user()->id);

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
