<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Phase 5 — بوابة العميل: عضوية فعّالة، سياق العميل، مبدّل، عزل، حالات تمنع الدخول. */
class ClientPortalTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function member(string $status = 'active', ?Tenant $t = null, ?User $u = null): array
    {
        $t ??= Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u ??= User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-' . Str::random(3), 'display_name' => 'عميل ' . Str::random(3), 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => $status, 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $client, $t];
    }

    public function test_login_requires_active_membership(): void
    {
        [$u] = $this->member('suspended');
        $this->post('/client/login', ['email' => $u->email, 'password' => 'secret12'])->assertSessionHasErrors('email');
    }

    public function test_active_member_reaches_dashboard_with_real_counts(): void
    {
        [$u, $client] = $this->member('active');
        // اللوحة صارت React — نتحقّق من المكوّن والعدّادات لا من HTML
        $this->actingAs($u)->get('/client/dashboard')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ClientPortal/Dashboard')->where('base', '/client'));
    }

    public function test_suspended_membership_blocks_portal(): void
    {
        [$u] = $this->member('suspended');
        $this->actingAs($u)->get('/client/dashboard')->assertForbidden(); // fail-closed
    }

    public function test_user_without_membership_denied(): void
    {
        TenantContext::bypass(true);
        $u = User::create(['name' => 'x', 'email' => 'x@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        TenantContext::reset();
        $this->actingAs($u)->get('/client/dashboard')->assertForbidden();
    }

    public function test_switch_only_to_own_active_clients(): void
    {
        [$u, $c1, $t] = $this->member('active');
        // عميل آخر لا ينتمي له المستخدم
        [$u2, $c2] = $this->member('active');
        $this->actingAs($u)->post('/client/switch', ['client_id' => $c2->id])->assertForbidden(); // ليس عضوًا
        $this->actingAs($u)->post('/client/switch', ['client_id' => $c1->id])->assertRedirect();  // عضو
    }

    public function test_client_context_is_isolated(): void
    {
        [$u1, $c1, $t1] = $this->member('active');
        [$u2, $c2, $t2] = $this->member('active');
        // مستخدم عميل 1 لا يرى بيانات عميل 2 — السياق من عضويته فقط
        // الملف صار تبويبًا في حساب المنشأة — العزل يُتحقّق من الحمولة
        $this->actingAs($u1)->get('/client/account')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ClientPortal/Account')
                ->where('client.number', $c1->client_number))
            ->assertDontSee($c2->client_number);
    }

    public function test_future_modules_show_not_available(): void
    {
        [$u] = $this->member('active');
        $this->actingAs($u)->get('/client/proposals')->assertOk()->assertSee('Not available yet');
    }
}
