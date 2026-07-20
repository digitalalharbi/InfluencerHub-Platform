<?php

namespace Tests\Feature;

use App\Domain\Automation\Models\AutomationLog;
use App\Domain\Automation\Services\SlaEngineService;
use App\Domain\Communications\Models\Notification;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 13 — محرّك SLA: رصد التجاوزات + التذكيرات مرة واحدة (dedup) + إشعار + سجلّ + عزل. */
class SlaEngineTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $admin = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $admin->id, 'role' => 'agency_admin', 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        return [$t, $org, $admin, $c];
    }
    private function req(Tenant $t, Client $c, ?\DateTimeInterface $dueAt, ?int $assignee = null): ServiceRequest
    {
        TenantContext::bypass(true);
        $sr = ServiceRequest::create(['tenant_id' => $t->id, 'request_number' => 'SR-' . Str::random(4), 'requester_type' => 'client',
            'requester_client_id' => $c->id, 'client_id' => $c->id, 'type' => 'campaign', 'title' => 'طلب', 'priority' => 'high',
            'status' => 'submitted', 'due_at' => $dueAt, 'assigned_to' => $assignee]);
        TenantContext::reset();
        return $sr;
    }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }
    private function engine(): SlaEngineService { return app(SlaEngineService::class); }

    public function test_overdue_request_is_flagged_notified_logged(): void
    {
        [$t, $org, $admin, $c] = $this->ctx();
        $sr = $this->req($t, $c, now()->subHours(2), $admin->id); // متأخّر ساعتين، مُسنَد للمدير
        $r = $this->engine()->scan();
        $this->assertEquals(1, $r['breaches']);
        $this->assertNotNull($this->fresh($sr)->sla_breached_at);
        TenantContext::bypass(true);
        $this->assertTrue(Notification::where('user_id', $admin->id)->where('type', 'sla.alert')->exists());
        $this->assertEquals(1, AutomationLog::where('rule', 'sla.breach')->where('subject_id', $sr->id)->count());
        TenantContext::reset();
    }

    public function test_breach_is_idempotent(): void
    {
        [$t, $org, $admin, $c] = $this->ctx();
        $this->req($t, $c, now()->subHours(2), $admin->id);
        $this->engine()->scan();
        $r2 = $this->engine()->scan(); // مسح ثانٍ — لا رصد جديد
        $this->assertEquals(0, $r2['breaches']);
        TenantContext::bypass(true);
        $this->assertEquals(1, AutomationLog::where('rule', 'sla.breach')->count()); // مرة واحدة فقط
        TenantContext::reset();
    }

    public function test_approaching_due_gets_one_reminder(): void
    {
        [$t, $org, $admin, $c] = $this->ctx();
        $sr = $this->req($t, $c, now()->addHours(3), $admin->id); // خلال نافذة 12 ساعة
        $r = $this->engine()->scan();
        $this->assertEquals(1, $r['reminders']);
        $this->assertNotNull($this->fresh($sr)->sla_reminded_at);
        $r2 = $this->engine()->scan();
        $this->assertEquals(0, $r2['reminders']); // لا تذكير مكرّر
    }

    public function test_far_future_due_no_action(): void
    {
        [$t, $org, $admin, $c] = $this->ctx();
        $this->req($t, $c, now()->addDays(3), $admin->id); // بعيد
        $r = $this->engine()->scan();
        $this->assertEquals(0, $r['breaches']);
        $this->assertEquals(0, $r['reminders']);
    }

    public function test_closed_request_ignored(): void
    {
        [$t, $org, $admin, $c] = $this->ctx();
        $sr = $this->req($t, $c, now()->subDay(), $admin->id);
        TenantContext::bypass(true); $sr->update(['status' => 'closed']); TenantContext::reset();
        $r = $this->engine()->scan();
        $this->assertEquals(0, $r['breaches']); // مغلق لا يُرصد
    }

    public function test_unassigned_breach_notifies_agency_admins(): void
    {
        [$t, $org, $admin, $c] = $this->ctx();
        $this->req($t, $c, now()->subHours(2), null); // بلا إسناد
        $this->engine()->scan();
        TenantContext::bypass(true);
        $this->assertTrue(Notification::where('user_id', $admin->id)->where('type', 'sla.alert')->exists()); // المدير أُشعِر
        TenantContext::reset();
    }

    public function test_sla_engine_tenant_isolated(): void
    {
        [$t1, , $a1, $c1] = $this->ctx();
        [$t2, , $a2, $c2] = $this->ctx();
        $this->req($t1, $c1, now()->subHours(2), $a1->id); // متأخّر في المستأجر 1 فقط
        $r = $this->engine()->scan();
        $this->assertEquals(1, $r['breaches']); // طلب واحد فقط عبر كل المستأجرين
        TenantContext::bypass(true);
        // إشعار المستأجر 1 فقط
        $this->assertTrue(Notification::where('user_id', $a1->id)->exists());
        $this->assertFalse(Notification::where('user_id', $a2->id)->exists());
        TenantContext::reset();
    }
}
