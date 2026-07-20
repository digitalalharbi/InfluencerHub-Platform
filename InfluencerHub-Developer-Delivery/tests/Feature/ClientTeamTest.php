<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientMember, ClientMemberInvitation};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 5 — إدارة فريق العميل من البوابة: دعوة/دور/حالة + حماية آخر مدير + عزل IDOR. */
class ClientTeamTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** يبني مستأجرًا + عميلًا + مستخدم عضو بدور معيّن (نشِط). يعيد [tenant, client, user, member]. */
    private function ctx(string $role = 'client_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $u = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $m = ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$t, $c, $u, $m];
    }
    private function addMember(Tenant $t, Client $c, string $role, string $status = 'active'): ClientMember
    {
        TenantContext::bypass(true);
        $u = User::create(['name' => 'M', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $m = ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => $role, 'status' => $status, 'accepted_at' => now()]);
        TenantContext::reset();
        return $m;
    }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }

    public function test_admin_can_invite_member_and_token_shown_once(): void
    {
        [$t, $c, $u] = $this->ctx();
        $this->actingAs($u)->post('/client/team/invite', ['email' => 'new@ex.com', 'role' => 'client_member'])
            ->assertRedirect()->assertSessionHas('invite_token');
        TenantContext::bypass(true);
        $inv = ClientMemberInvitation::where('email', 'new@ex.com')->first();
        TenantContext::reset();
        $this->assertNotNull($inv);
        $this->assertEquals(64, strlen($inv->token_hash)); // sha256 مُخزّن، لا الرمز الخام
    }

    public function test_non_admin_cannot_invite(): void
    {
        [$t, $c, $u] = $this->ctx('client_member');
        $this->actingAs($u)->post('/client/team/invite', ['email' => 'x@ex.com', 'role' => 'client_member'])->assertForbidden();
    }

    public function test_admin_cannot_assign_agency_or_system_role(): void
    {
        [$t, $c, $u] = $this->ctx();
        $this->actingAs($u)->post('/client/team/invite', ['email' => 'x@ex.com', 'role' => 'agency_admin'])
            ->assertSessionHasErrors('team');
    }

    public function test_change_member_role(): void
    {
        [$t, $c, $u] = $this->ctx();
        $other = $this->addMember($t, $c, 'client_member');
        $this->actingAs($u)->post("/client/team/{$other->id}/role", ['role' => 'client_finance'])->assertRedirect();
        $this->assertEquals('client_finance', $this->fresh($other)->role);
    }

    public function test_suspend_and_reactivate_member(): void
    {
        [$t, $c, $u] = $this->ctx();
        $other = $this->addMember($t, $c, 'client_member');
        $this->actingAs($u)->post("/client/team/{$other->id}/status", ['action' => 'suspend'])->assertRedirect();
        $this->assertEquals('suspended', $this->fresh($other)->status);
        $this->actingAs($u)->post("/client/team/{$other->id}/status", ['action' => 'reactivate'])->assertRedirect();
        $this->assertEquals('active', $this->fresh($other)->status);
    }

    public function test_cannot_demote_last_admin(): void
    {
        [$t, $c, $u, $me] = $this->ctx(); // المدير الوحيد
        $this->actingAs($u)->post("/client/team/{$me->id}/role", ['role' => 'client_member'])->assertSessionHasErrors('team');
        $this->assertEquals('client_admin', $this->fresh($me)->role);
    }

    public function test_cannot_suspend_last_admin(): void
    {
        [$t, $c, $u, $me] = $this->ctx();
        $this->actingAs($u)->post("/client/team/{$me->id}/status", ['action' => 'suspend'])->assertSessionHasErrors('team');
        $this->assertEquals('active', $this->fresh($me)->status);
    }

    public function test_can_demote_admin_when_another_admin_exists(): void
    {
        [$t, $c, $u, $me] = $this->ctx();
        $this->addMember($t, $c, 'client_admin'); // مدير ثانٍ
        $this->actingAs($u)->post("/client/team/{$me->id}/role", ['role' => 'client_member'])->assertRedirect();
        $this->assertEquals('client_member', $this->fresh($me)->role);
    }

    public function test_cannot_manage_member_of_another_client(): void
    {
        [$t1, $c1, $u1] = $this->ctx();
        [$t2, $c2, $u2] = $this->ctx();
        $memberOfC2 = $this->addMember($t2, $c2, 'client_member');
        // مدير العميل 1 يحاول تعديل عضو العميل 2 → 404 (IDOR)
        $this->actingAs($u1)->post("/client/team/{$memberOfC2->id}/role", ['role' => 'client_finance'])->assertNotFound();
        $this->assertEquals('client_member', $this->fresh($memberOfC2)->role);
    }
}
