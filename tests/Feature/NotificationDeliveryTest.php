<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Models\NotificationDeliveryAttempt;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Mail\NotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * التسليم عبر خطّ الأنابيب الحقيقي (NotificationService → DeliveryDispatcher → القنوات):
 * تفضيل البريد ON ⇒ محاولة بريد؛ OFF ⇒ لا بريد (والداخل-التطبيق يبقى). عزل قراءة الإشعار
 * (IDOR)، والرابط العميق يفتح عند القراءة. لا محاكاة للمنطق — القنوات الفعليّة.
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User,2:Organization} */
    private function world(string $email): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مستخدم', 'email' => $email, 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $u, $org];
    }

    public function test_preference_email_on_produces_email_attempt(): void
    {
        config(['channels.email.enabled' => true]);
        Mail::fake();
        [$t, $u] = $this->world('on@ex.com');
        $svc = app(NotificationService::class);
        $svc->setPreference($t->id, $u->id, 'general', inApp: true, email: true, sms: false);

        $svc->notify($t->id, $u->id, 'test.event', 'general', 'حدث مهم', 'تفاصيل', '/app/x');

        Mail::assertQueued(NotificationMail::class);
        $emailAttempts = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::where('channel', 'email')->count());
        $this->assertGreaterThan(0, $emailAttempts, 'يجب تسجيل محاولة بريد عند تفعيل التفضيل');
    }

    public function test_preference_email_off_sends_no_email_but_keeps_in_app(): void
    {
        config(['channels.email.enabled' => true]);
        Mail::fake();
        [$t, $u] = $this->world('off@ex.com');
        $svc = app(NotificationService::class);
        $svc->setPreference($t->id, $u->id, 'general', inApp: true, email: false, sms: false);

        $svc->notify($t->id, $u->id, 'test.event', 'general', 'حدث مهم', 'تفاصيل', '/app/x');

        Mail::assertNothingQueued(); // التفضيل OFF ⇒ لا بريد
        [$inApp, $email] = TenantContext::withBypass(fn () => [
            NotificationDeliveryAttempt::where('channel', 'in_app')->count(),
            NotificationDeliveryAttempt::where('channel', 'email')->where('status', '!=', 'skipped')->count(),
        ]);
        $this->assertGreaterThan(0, $inApp, 'الداخل-التطبيق يبقى بحسب تفضيله');
        $this->assertSame(0, $email, 'لا محاولة بريد فعليّة عند إيقاف التفضيل');
    }

    public function test_notification_read_is_tenant_and_user_isolated_idor(): void
    {
        [$tA, $uA] = $this->world('a@ex.com');
        [, $uB] = $this->world('b@ex.com');
        $nA = TenantContext::withTenant($tA->id, fn () => Notification::create([
            'tenant_id' => $tA->id, 'user_id' => $uA->id, 'type' => 'x', 'category' => 'general', 'title' => 'خاص بـA', 'action_url' => '/app/contracts/1',
        ]));

        // مستخدم من مستأجر آخر لا يستطيع قراءة/فتح إشعار A
        $this->actingAs($uB)->post("/app/notifications/{$nA->id}/read")->assertNotFound();
        $this->assertNull($nA->fresh()->read_at, 'لم تتغيّر حالة إشعار A بفعل مستخدم آخر');
    }

    public function test_read_opens_deep_link(): void
    {
        [$t, $u] = $this->world('link@ex.com');
        $n = TenantContext::withTenant($t->id, fn () => Notification::create([
            'tenant_id' => $t->id, 'user_id' => $u->id, 'type' => 'x', 'category' => 'general', 'title' => 'افتح', 'action_url' => '/app/contracts/9',
        ]));

        $this->actingAs($u)->post("/app/notifications/{$n->id}/read")->assertRedirect('/app/contracts/9');
        $this->assertNotNull($n->fresh()->read_at);
    }
}
