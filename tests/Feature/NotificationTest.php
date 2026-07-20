<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\{Notification, NotificationDeliveryAttempt};
use App\Domain\Communications\Services\NotificationService;
use App\Domain\CRM\Models\{Brand, Client, ClientMember};
use App\Domain\CRM\Services\BrandWorkflowService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 5 — الإشعارات: محايدة للمزوّد، حالات تسليم صادقة، تفضيلات، عزل، ووصل بالأحداث. */
class NotificationTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $agency = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $agency->id, 'role' => 'agency_admin', 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $clientUser = User::create(['name' => 'C', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $clientUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$t, $org, $agency, $c, $clientUser];
    }
    private function svc(): NotificationService { return app(NotificationService::class); }

    public function test_in_app_delivered_email_sms_waiting_for_credentials(): void
    {
        [$t, $org, $agency, $c, $u] = $this->ctx();
        // فعّل email + sms في التفضيلات
        $this->svc()->setPreference($t->id, $u->id, 'general', true, true, true);
        $n = $this->svc()->notify($t->id, $u->id, 'test.event', 'general', 'عنوان', 'نص');

        TenantContext::bypass(true);
        $attempts = NotificationDeliveryAttempt::where('notification_id', $n->id)->pluck('status', 'channel');
        TenantContext::reset();
        $this->assertEquals('sent', $attempts['in_app']);                       // in_app فعلي
        $this->assertEquals('waiting_for_credentials', $attempts['email']);      // لا تسليم وهمي
        $this->assertEquals('waiting_for_credentials', $attempts['sms']);
    }

    public function test_unread_count_and_mark_read(): void
    {
        [$t, $org, $agency, $c, $u] = $this->ctx();
        $this->svc()->notify($t->id, $u->id, 'e1', 'general', 'أ');
        $n2 = $this->svc()->notify($t->id, $u->id, 'e2', 'general', 'ب');
        $this->assertEquals(2, $this->svc()->unreadCount($t->id, $u->id));
        $this->svc()->markRead($n2);
        $this->assertEquals(1, $this->svc()->unreadCount($t->id, $u->id));
        $this->svc()->markAllRead($t->id, $u->id);
        $this->assertEquals(0, $this->svc()->unreadCount($t->id, $u->id));
    }

    public function test_restores_previous_tenant_context(): void
    {
        [$t, $org, $agency, $c, $u] = $this->ctx();
        TenantContext::set(999, 888); // سياق سابق مصطنع
        $this->svc()->notify($t->id, $u->id, 'e', 'general', 'x');
        $this->assertEquals(999, TenantContext::tenantId());   // استُعيد السياق
        $this->assertEquals(888, TenantContext::organizationId());
        TenantContext::reset();
    }

    public function test_brand_approval_notifies_client_members_over_http(): void
    {
        [$t, $org, $agency, $c, $u] = $this->ctx();
        $wf = app(BrandWorkflowService::class);
        $b = $wf->createDraft($t->id, $c->id, ['name' => 'نايك'], $u->id);
        $b = $wf->submit($b, $u->id);
        $b = $wf->startReview($b, $agency->id);
        // الوكالة تعتمد عبر HTTP → يجب أن يصل إشعار لعضو العميل
        $this->actingAs($agency)->post("/app/brands/{$b->id}/approve")->assertRedirect();
        TenantContext::bypass(true);
        $n = Notification::where('user_id', $u->id)->where('type', 'brand.approved')->first();
        TenantContext::reset();
        $this->assertNotNull($n);
        $this->assertStringContainsString('نايك', $n->title);
    }

    public function test_notifications_are_tenant_isolated(): void
    {
        [$t1, , , , $u1] = $this->ctx();
        [$t2, , , , $u2] = $this->ctx();
        $this->svc()->notify($t1->id, $u1->id, 'e', 'general', 'سرّي');
        // عدّاد المستخدم في المستأجر 2 لا يرى إشعار المستأجر 1
        $this->assertEquals(0, $this->svc()->unreadCount($t2->id, $u2->id));
        $this->assertEquals(1, $this->svc()->unreadCount($t1->id, $u1->id));
    }
}
