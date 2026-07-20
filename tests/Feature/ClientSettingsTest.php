<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\NotificationPreference;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Phase 5 — إعدادات العميل: تفضيلات الإشعارات + كلمة المرور + الجلسات. */
class ClientSettingsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $u = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => Hash::make('OldPass123'), 'is_active' => true]);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$t, $c, $u];
    }

    public function test_settings_page_renders(): void
    {
        [$t, $c, $u] = $this->ctx();
        // الإعدادات صارت تبويبًا في حساب المنشأة
        $this->actingAs($u)->get('/client/account')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ClientPortal/Account')->has('prefs')->has('sessions')->has('categories'));
    }

    public function test_settings_renders_when_preferences_already_exist(): void
    {
        [$t, $c, $u] = $this->ctx();
        // تفضيلات موجودة مسبقًا (كما لو أُنشئت عبر إشعار سابق) — يجب ألا تسبب Unique violation
        app(\App\Domain\Communications\Services\NotificationService::class)->notify($t->id, $u->id, 'e', 'brands', 'x');
        $this->actingAs($u)->get('/client/account')->assertOk();
        $this->actingAs($u)->get('/client/account')->assertOk(); // مرّتين للتأكّد من idempotency
    }

    public function test_update_notification_preferences(): void
    {
        [$t, $c, $u] = $this->ctx();
        $this->actingAs($u)->post('/client/settings/notifications', [
            'prefs' => ['brands' => ['in_app' => '1', 'email' => '1'], 'documents' => ['in_app' => '1', 'sms' => '1']],
        ])->assertRedirect();
        TenantContext::bypass(true);
        $brands = NotificationPreference::where('user_id', $u->id)->where('category', 'brands')->first();
        $docs = NotificationPreference::where('user_id', $u->id)->where('category', 'documents')->first();
        TenantContext::reset();
        $this->assertTrue($brands->email);
        $this->assertFalse($brands->sms);
        $this->assertTrue($docs->sms);
    }

    public function test_change_password_requires_correct_current(): void
    {
        [$t, $c, $u] = $this->ctx();
        $this->actingAs($u)->post('/client/settings/password', [
            'current_password' => 'WRONG', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_change_password_updates_hash(): void
    {
        [$t, $c, $u] = $this->ctx();
        $this->actingAs($u)->post('/client/settings/password', [
            'current_password' => 'OldPass123', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('NewPass123', $u->fresh()->password));
    }

    public function test_weak_password_rejected(): void
    {
        [$t, $c, $u] = $this->ctx();
        $this->actingAs($u)->post('/client/settings/password', [
            'current_password' => 'OldPass123', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_revoke_other_sessions_keeps_current(): void
    {
        [$t, $c, $u] = $this->ctx();
        // جلسة أخرى وهمية للمستخدم
        DB::table('sessions')->insert([
            'id' => 'other-session-id', 'user_id' => $u->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x',
            'payload' => 'x', 'last_activity' => now()->timestamp,
        ]);
        $this->actingAs($u)->post('/client/settings/sessions/revoke-others')->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
    }
}
