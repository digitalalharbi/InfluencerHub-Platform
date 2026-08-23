<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\{Notification, NotificationDeliveryAttempt, NotificationPreference};
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * مركز إشعارات الوكالة — قائمة بحالة التسليم، تحديد مقروء (فردي/كل)، عزل IDOR،
 * عدّاد غير المقروء المشترَك، وتفضيلات تُغيّر سلوك التسليم فعليًّا.
 */
class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            return [$t, $u];
        });
    }

    public function test_index_lists_notifications_with_delivery_state_and_unread(): void
    {
        [$t, $u] = $this->agency();
        app(NotificationService::class)->notify($t->id, $u->id, 'content.approved', 'content_reviews', 'اعتُمد', 'x', '/app/content/1');

        $this->actingAs($u)->get('/app/notifications')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Notifications/Index')
                ->where('unread', 1)
                ->has('items.data', 1)
                ->where('items.data.0.delivery', fn ($d) => collect($d)->contains(fn ($x) => $x['channel'] === 'داخل التطبيق')));
    }

    public function test_shared_unread_count_is_exposed(): void
    {
        [$t, $u] = $this->agency();
        app(NotificationService::class)->notify($t->id, $u->id, 'x', 'general', 't');
        $this->actingAs($u)->get('/app')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('unreadNotifications', 1));
    }

    public function test_mark_read_and_read_all(): void
    {
        [$t, $u] = $this->agency();
        $n = app(NotificationService::class)->notify($t->id, $u->id, 'x', 'general', 't');
        $this->actingAs($u)->post("/app/notifications/{$n->id}/read")->assertRedirect();
        $this->assertNotNull(TenantContext::withBypass(fn () => Notification::find($n->id)->read_at));

        app(NotificationService::class)->notify($t->id, $u->id, 'x', 'general', 't2');
        $this->actingAs($u)->post('/app/notifications/read-all')->assertRedirect();
        $this->assertSame(0, TenantContext::withBypass(fn () => Notification::where('user_id', $u->id)->whereNull('read_at')->count()));
    }

    public function test_cannot_read_another_users_notification(): void
    {
        [$t, $u] = $this->agency();
        $other = TenantContext::withBypass(fn () => User::create(['name' => 'x', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]));
        $n = app(NotificationService::class)->notify($t->id, $other->id, 'x', 'general', 't');
        $this->actingAs($u)->post("/app/notifications/{$n->id}/read")->assertNotFound();
    }

    public function test_preferences_page_and_update_changes_delivery(): void
    {
        [$t, $u] = $this->agency();
        $this->actingAs($u)->get('/app/notifications/preferences')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Notifications/Preferences')->has('categories')->has('channels'));

        // فعّل البريد لفئة المهام
        $this->actingAs($u)->post('/app/notifications/preferences', [
            'category' => 'tasks', 'in_app' => true, 'email' => true, 'whatsapp' => false, 'sms' => false,
        ])->assertRedirect()->assertSessionHas('ok');

        $pref = TenantContext::withBypass(fn () => NotificationPreference::where('user_id', $u->id)->where('category', 'tasks')->first());
        $this->assertTrue($pref->email);

        // الآن إشعار في فئة tasks يسجّل محاولة بريد (waiting_for_credentials — لا مزوّد)
        $n = app(NotificationService::class)->notify($t->id, $u->id, 'task.assigned', 'tasks', 'مهمة');
        $emailAttempt = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::where('notification_id', $n->id)->where('channel', 'email')->first());
        $this->assertNotNull($emailAttempt, 'تفعيل التفضيل غيّر سلوك التسليم فعليًّا');
    }

    public function test_guest_redirected(): void
    {
        $this->get('/app/notifications')->assertRedirect('/login');
    }
}
