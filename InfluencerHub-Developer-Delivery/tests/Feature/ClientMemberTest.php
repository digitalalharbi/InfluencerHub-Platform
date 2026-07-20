<?php

namespace Tests\Feature;

use App\Domain\CRM\Actions\{InviteClientMember, AcceptClientMemberInvitation, ChangeClientMemberStatus, ChangeClientMemberRole};
use App\Domain\CRM\Models\{Client, ClientMember, ClientMemberInvitation};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientMemberTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $inviter = User::create(['name' => 'inv', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'C', 'type' => 'company', 'status' => 'active']);
        TenantContext::reset();
        TenantContext::set($t->id, $org->id);
        return [$client, $inviter];
    }

    public function test_invite_stores_hash_not_raw_and_returns_raw_once(): void
    {
        [$client, $inviter] = $this->ctx();
        [$inv, $raw] = app(InviteClientMember::class)->handle($client, 'x@ex.com', 'client_admin', $inviter);
        $this->assertNotEquals($raw, $inv->token_hash);
        $this->assertEquals(hash('sha256', $raw), $inv->token_hash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_member.invited']);
    }

    public function test_invalid_role_is_rejected(): void
    {
        [$client, $inviter] = $this->ctx();
        $this->expectException(\RuntimeException::class);
        app(InviteClientMember::class)->handle($client, 'x@ex.com', 'agency_admin', $inviter); // ليس دور بوابة عميل
    }

    public function test_accept_valid_invitation_creates_active_member(): void
    {
        [$client, $inviter] = $this->ctx();
        [$inv, $raw] = app(InviteClientMember::class)->handle($client, 'm@ex.com', 'client_member', $inviter);
        $user = User::create(['name' => 'm', 'email' => 'm@ex.com', 'password' => 'x', 'is_active' => true]);
        $member = app(AcceptClientMemberInvitation::class)->handle($raw, $user);
        $this->assertEquals('active', $member->status);
        $this->assertNotNull($inv->fresh()->accepted_at);
    }

    public function test_expired_invitation_is_rejected(): void
    {
        [$client, $inviter] = $this->ctx();
        [$inv, $raw] = app(InviteClientMember::class)->handle($client, 'm@ex.com', 'client_member', $inviter);
        $inv->update(['expires_at' => now()->subDay()]);
        $user = User::create(['name' => 'm', 'email' => 'm@ex.com', 'password' => 'x', 'is_active' => true]);
        $this->expectException(\RuntimeException::class);
        app(AcceptClientMemberInvitation::class)->handle($raw, $user);
    }

    public function test_reused_invitation_is_rejected(): void
    {
        [$client, $inviter] = $this->ctx();
        [$inv, $raw] = app(InviteClientMember::class)->handle($client, 'm@ex.com', 'client_member', $inviter);
        $user = User::create(['name' => 'm', 'email' => 'm@ex.com', 'password' => 'x', 'is_active' => true]);
        app(AcceptClientMemberInvitation::class)->handle($raw, $user);
        $this->expectException(\RuntimeException::class);
        app(AcceptClientMemberInvitation::class)->handle($raw, $user); // مرة ثانية → مرفوض
    }

    public function test_suspend_and_revoke_change_status(): void
    {
        [$client, $inviter] = $this->ctx();
        [$inv, $raw] = app(InviteClientMember::class)->handle($client, 'm@ex.com', 'client_member', $inviter);
        $user = User::create(['name' => 'm', 'email' => 'm@ex.com', 'password' => 'x', 'is_active' => true]);
        $member = app(AcceptClientMemberInvitation::class)->handle($raw, $user);

        $s = app(ChangeClientMemberStatus::class);
        $this->assertEquals('suspended', $s->handle($member, 'suspend', $inviter)->status);
        $this->assertFalse($member->fresh()->isActive());
        $this->assertEquals('active', $s->handle($member->fresh(), 'reactivate', $inviter)->status);
        $this->assertEquals('revoked', $s->handle($member->fresh(), 'revoke', $inviter)->status);
    }

    public function test_change_role_rejects_non_client_role(): void
    {
        [$client, $inviter] = $this->ctx();
        [$inv, $raw] = app(InviteClientMember::class)->handle($client, 'm@ex.com', 'client_member', $inviter);
        $user = User::create(['name' => 'm', 'email' => 'm@ex.com', 'password' => 'x', 'is_active' => true]);
        $member = app(AcceptClientMemberInvitation::class)->handle($raw, $user);
        $this->assertEquals('client_finance', app(ChangeClientMemberRole::class)->handle($member, 'client_finance', $inviter)->role);
        $this->expectException(\RuntimeException::class);
        app(ChangeClientMemberRole::class)->handle($member->fresh(), 'system_admin', $inviter);
    }
}
