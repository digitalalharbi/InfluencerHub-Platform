<?php

namespace Tests\Feature;

use App\Domain\Automation\Automation;
use App\Domain\Automation\DefaultAutomationRules;
use App\Domain\Automation\Engine\AutomationEngine;
use App\Domain\Automation\Models\{AutomationRule, AutomationRun};
use App\Domain\Communications\Models\Notification;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Services\ServiceRequestWorkflowService;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ربط الأتمتة بأحداث النطاق الفعلية: إنشاء طلب خدمة يُطلق محفّزًا فينفّذ قاعدة
 * افتراضية (تُثبَّت تلقائيًا) وتُنشئ إشعارًا وتُسجَّل التشغيلة — بمفتاح حدث ثابت
 * يمنع التكرار عند إعادة المحاولة.
 */
class AutomationWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { Automation::flushEnsured(); TenantContext::reset(); parent::tearDown(); }

    private function tenantUser(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $u = TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $usr = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $usr->id, 'role' => 'agency_admin', 'status' => 'active']);
            return $usr;
        });
        return [$t, $u];
    }

    public function test_defaults_are_installed_and_idempotent(): void
    {
        [$t] = $this->tenantUser();
        app(DefaultAutomationRules::class)->ensure($t->id);
        app(DefaultAutomationRules::class)->ensure($t->id); // مرّتين
        $count = TenantContext::withBypass(fn () => AutomationRule::where('tenant_id', $t->id)->where('is_system', true)->count());
        $this->assertSame(count(DefaultAutomationRules::definitions()), $count, 'لا تكرار للقواعد');
    }

    public function test_creating_service_request_fires_automation_and_notifies(): void
    {
        [$t, $u] = $this->tenantUser();
        $sr = app(ServiceRequestWorkflowService::class)->create($t->id, [
            'requester_type' => 'agency', 'type' => 'consultation', 'title' => 'طلب اختبار',
        ], $u->id);

        // القاعدة الافتراضية أخطرت مُنشئ الطلب
        $n = TenantContext::withBypass(fn () => Notification::where('user_id', $u->id)->where('type', 'automation.request_received')->first());
        $this->assertNotNull($n, 'الأتمتة أنشأت إشعارًا');
        $this->assertStringContainsString($sr->request_number, $n->title);
        $this->assertSame('/app/service-requests/' . $sr->id, $n->action_url);

        // تشغيلة مُسجَّلة بحالة executed
        $run = TenantContext::withBypass(fn () => AutomationRun::where('tenant_id', $t->id)->where('status', 'executed')->first());
        $this->assertNotNull($run);
        $this->assertSame('service_request.created:' . $sr->id, $run->event_key);
    }

    public function test_idempotency_same_event_runs_once(): void
    {
        [$t, $u] = $this->tenantUser();
        app(DefaultAutomationRules::class)->ensure($t->id);
        $engine = app(AutomationEngine::class);
        $ctx = ['requested_by' => $u->id, 'id' => 5, 'number' => 'SR-1-5', 'title' => 'x'];

        $engine->fire('service_request.created', $ctx, $t->id, 'service_request.created:5');
        $engine->fire('service_request.created', $ctx, $t->id, 'service_request.created:5'); // إعادة نفس الحدث

        $notifs = TenantContext::withBypass(fn () => Notification::where('user_id', $u->id)->where('type', 'automation.request_received')->count());
        $this->assertSame(1, $notifs, 'إشعار واحد رغم إطلاقين لنفس الحدث');
    }

    public function test_automation_failure_never_breaks_the_workflow(): void
    {
        [$t, $u] = $this->tenantUser();
        // قاعدة بإجراء غير معروف لا تُفشِل إنشاء الطلب
        TenantContext::withBypass(fn () => AutomationRule::create([
            'tenant_id' => $t->id, 'key' => 'bad', 'name' => 'x', 'trigger' => 'service_request.created',
            'actions' => [['type' => 'nonexistent']], 'enabled' => true,
        ]));
        $sr = app(ServiceRequestWorkflowService::class)->create($t->id, ['requester_type' => 'agency', 'type' => 'consultation', 'title' => 'x'], $u->id);
        $this->assertNotNull($sr->id, 'الطلب أُنشئ رغم قاعدة أتمتة معطوبة');
    }

    public function test_management_page_lists_rules_and_runs(): void
    {
        [$t, $u] = $this->tenantUser();
        app(\App\Domain\Requests\Services\ServiceRequestWorkflowService::class)->create($t->id, ['requester_type' => 'agency', 'type' => 'consultation', 'title' => 'x'], $u->id);
        $this->actingAs($u)->get('/app/automation')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Automation/Index')->has('rules')->has('runs'));
        $this->assertGreaterThanOrEqual(1, TenantContext::withBypass(fn () => AutomationRun::where('tenant_id', $t->id)->where('status', 'executed')->count()));
    }

    public function test_toggle_disables_and_enables_a_rule(): void
    {
        [$t, $u] = $this->tenantUser();
        app(\App\Domain\Automation\DefaultAutomationRules::class)->ensure($t->id);
        $rule = TenantContext::withBypass(fn () => AutomationRule::where('tenant_id', $t->id)->first());
        $this->actingAs($u)->post("/app/automation/{$rule->id}/toggle")->assertRedirect();
        $this->assertFalse(TenantContext::withBypass(fn () => AutomationRule::find($rule->id)->enabled));
        $this->actingAs($u)->post("/app/automation/{$rule->id}/toggle")->assertRedirect();
        $this->assertTrue(TenantContext::withBypass(fn () => AutomationRule::find($rule->id)->enabled));
    }

    public function test_automation_page_forbidden_for_non_admin(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $v = TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $usr = User::create(['name' => 'x', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $usr->id, 'role' => 'viewer', 'status' => 'active']);
            return $usr;
        });
        $this->actingAs($v)->get('/app/automation')->assertForbidden();
    }
}
