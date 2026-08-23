<?php

namespace Tests\Feature;

use App\Domain\Automation\Engine\{ActionRegistry, AutomationEngine, ConditionEvaluator};
use App\Domain\Automation\Actions\NotifyAction;
use App\Domain\Automation\Models\{AutomationRule, AutomationRun};
use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * محرّك الأتمتة الحدثي — محفّز يطابق قاعدة، يُقيّم شروطها الحتمية، وينفّذ إجراء
 * الإشعار عبر طبقة القنوات، ويسجّل التشغيلة. الشرط غير المتحقّق يُتخطّى.
 */
class AutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function engine(): AutomationEngine
    {
        return new AutomationEngine(new ConditionEvaluator, new ActionRegistry([app(NotifyAction::class)]));
    }

    private function seedTU(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $u = TenantContext::withBypass(fn () => User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]));
        return [$t, $u];
    }

    public function test_condition_evaluator_operators(): void
    {
        $e = new ConditionEvaluator;
        $this->assertTrue($e->passes([['field' => 'priority', 'op' => 'eq', 'value' => 'high']], ['priority' => 'high']));
        $this->assertFalse($e->passes([['field' => 'priority', 'op' => 'eq', 'value' => 'high']], ['priority' => 'low']));
        $this->assertTrue($e->passes([['field' => 'days', 'op' => 'gte', 'value' => 2]], ['days' => 3]));
        $this->assertTrue($e->passes([['field' => 'status', 'op' => 'in', 'value' => ['a', 'b']]], ['status' => 'b']));
        $this->assertTrue($e->passes(null, [])); // بلا شروط = يمرّ
    }

    public function test_matching_rule_executes_notify_action(): void
    {
        [$t, $u] = $this->seedTU();
        TenantContext::withTenant($t->id, fn () => AutomationRule::create([
            'tenant_id' => $t->id, 'key' => 'sys.req.assign', 'name' => 'إشعار الإسناد', 'trigger' => 'request_created',
            'conditions' => [['field' => 'priority', 'op' => 'in', 'value' => ['high', 'urgent']]],
            'actions' => [['type' => 'notify', 'to' => 'owner_id', 'title' => 'طلب {{priority}} جديد', 'body' => '{{number}}', 'action_url' => '/app/service-requests/{{id}}']],
            'enabled' => true, 'is_system' => true,
        ]));

        $count = $this->engine()->fire('request_created', [
            'owner_id' => $u->id, 'priority' => 'high', 'number' => 'SR-1-9', 'id' => 9,
        ], $t->id);

        $this->assertSame(1, $count);
        $n = TenantContext::withBypass(fn () => Notification::where('user_id', $u->id)->first());
        $this->assertNotNull($n);
        $this->assertSame('طلب high جديد', $n->title);
        $this->assertSame('/app/service-requests/9', $n->action_url);

        $run = TenantContext::withBypass(fn () => AutomationRun::where('tenant_id', $t->id)->first());
        $this->assertSame('executed', $run->status);
    }

    public function test_unmet_condition_skips_and_records(): void
    {
        [$t, $u] = $this->seedTU();
        TenantContext::withTenant($t->id, fn () => AutomationRule::create([
            'tenant_id' => $t->id, 'key' => 'sys.req.assign', 'name' => 'x', 'trigger' => 'request_created',
            'conditions' => [['field' => 'priority', 'op' => 'eq', 'value' => 'urgent']],
            'actions' => [['type' => 'notify', 'to' => 'owner_id', 'title' => 'x']],
            'enabled' => true,
        ]));

        $count = $this->engine()->fire('request_created', ['owner_id' => $u->id, 'priority' => 'low'], $t->id);

        $this->assertSame(0, $count);
        $this->assertSame(0, TenantContext::withBypass(fn () => Notification::where('user_id', $u->id)->count()));
        $run = TenantContext::withBypass(fn () => AutomationRun::where('tenant_id', $t->id)->first());
        $this->assertSame('skipped', $run->status);
    }

    public function test_disabled_rule_does_not_fire(): void
    {
        [$t, $u] = $this->seedTU();
        TenantContext::withTenant($t->id, fn () => AutomationRule::create([
            'tenant_id' => $t->id, 'key' => 'k', 'name' => 'x', 'trigger' => 'request_created',
            'actions' => [['type' => 'notify', 'to' => 'owner_id', 'title' => 'x']], 'enabled' => false,
        ]));
        $count = $this->engine()->fire('request_created', ['owner_id' => $u->id], $t->id);
        $this->assertSame(0, $count);
    }

    public function test_action_failure_does_not_break_other_rules(): void
    {
        [$t, $u] = $this->seedTU();
        TenantContext::withTenant($t->id, fn () => AutomationRule::create([
            'tenant_id' => $t->id, 'key' => 'k', 'name' => 'x', 'trigger' => 'request_created',
            'actions' => [['type' => 'unknown_type'], ['type' => 'notify', 'to' => 'owner_id', 'title' => 'ok']],
            'enabled' => true,
        ]));
        // لا استثناء يتسرّب؛ الإشعار الصالح يمرّ رغم إجراء غير معروف
        $count = $this->engine()->fire('request_created', ['owner_id' => $u->id], $t->id);
        $this->assertSame(1, $count);
        $this->assertSame(1, TenantContext::withBypass(fn () => Notification::where('user_id', $u->id)->count()));
    }

    public function test_tenant_isolation(): void
    {
        [$t1, $u1] = $this->seedTU();
        [$t2] = $this->seedTU();
        TenantContext::withTenant($t1->id, fn () => AutomationRule::create([
            'tenant_id' => $t1->id, 'key' => 'k', 'name' => 'x', 'trigger' => 'request_created',
            'actions' => [['type' => 'notify', 'to' => 'owner_id', 'title' => 'x']], 'enabled' => true,
        ]));
        // إطلاق لمستأجر آخر لا يطابق قاعدة t1
        $count = $this->engine()->fire('request_created', ['owner_id' => $u1->id], $t2->id);
        $this->assertSame(0, $count);
    }
}
