<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientDocument, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** فريق ومستندات العميل React/Inertia — عرض/دعوة/أدوار + مستندات مرئية، معزول + بوابة الدور. */
class InertiaClientTeamDocsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Client,2:Tenant} */
    private function member(string $role = 'client_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'شركة', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $client, $t];
    }

    public function test_team_lists_self_and_flags_manage(): void
    {
        [$u] = $this->member('client_admin');
        $this->actingAs($u)->get('/beta/client/team')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Team/Index')
                ->where('canManage', true)
                ->where('members.0.isMe', true)
                ->has('roles'));
    }

    public function test_admin_can_invite(): void
    {
        [$u, $client, $t] = $this->member('client_admin');
        $this->actingAs($u)->post('/beta/client/team/invite', ['email' => 'new@ex.com', 'role' => 'client_member'])->assertRedirect();
        TenantContext::set($t->id);
        $this->assertDatabaseHas('client_member_invitations', ['client_id' => $client->id, 'email' => 'new@ex.com']);
        TenantContext::reset();
    }

    public function test_cannot_remove_last_admin(): void
    {
        [$u, $client, $t] = $this->member('client_admin');
        TenantContext::set($t->id);
        $me = ClientMember::where('client_id', $client->id)->where('user_id', $u->id)->first();
        TenantContext::reset();
        $this->actingAs($u)->post("/beta/client/team/{$me->id}/status", ['action' => 'revoke'])->assertSessionHasErrors('team');
    }

    public function test_non_admin_cannot_manage_team(): void
    {
        [$u] = $this->member('client_member');
        $this->actingAs($u)->get('/beta/client/team')->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        $this->actingAs($u)->post('/beta/client/team/invite', ['email' => 'x@ex.com', 'role' => 'client_member'])->assertForbidden();
    }

    public function test_documents_shows_only_client_visible(): void
    {
        [$u, $client, $t] = $this->member('client_admin');
        TenantContext::set($t->id);
        ClientDocument::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'category' => 'report', 'visibility' => 'client_visible',
            'title' => 'تقرير الأداء', 'disk' => 'local', 'path' => 'x/report.pdf', 'original_name' => 'report.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 20480, 'checksum_sha256' => str_repeat('a', 64), 'status' => 'active']);
        ClientDocument::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'category' => 'legal', 'visibility' => 'agency_internal',
            'title' => 'داخلي', 'disk' => 'local', 'path' => 'x/internal.pdf', 'original_name' => 'internal.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 10240, 'checksum_sha256' => str_repeat('b', 64), 'status' => 'active']);
        TenantContext::reset();

        $this->actingAs($u)->get('/beta/client/documents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Documents/Index')
                ->has('docs', 1)
                ->where('docs.0.title', 'تقرير الأداء'));
    }

    public function test_team_idor_role_change_safe(): void
    {
        [$u1] = $this->member('client_admin');
        [, $c2, $t2] = $this->member('client_admin');
        TenantContext::set($t2->id);
        $otherMember = ClientMember::where('client_id', $c2->id)->first();
        TenantContext::reset();
        $this->actingAs($u1)->post("/beta/client/team/{$otherMember->id}/role", ['role' => 'client_member'])->assertNotFound();
    }
}
