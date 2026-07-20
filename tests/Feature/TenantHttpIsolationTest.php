<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Jobs\CreateTenantNoteJob;
use App\Domain\Tenancy\Models\{Tenant, Organization, Workspace, OrganizationMembership, Note};
use App\Domain\Tenancy\Support\{TenantContext, TenantCache};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase 1 gate — عزل المستأجر عبر HTTP + IDOR + Route binding + Queue + Cache. */
class TenantHttpIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** يُنشئ tenant + org + user + membership فعّالة، ويعيد [user, tenant, org, ws]. */
    private function actor(string $slug, string $role = 'agency_admin', string $memberStatus = 'active'): array
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $tenant->id, 'name' => $slug, 'slug' => $slug, 'type' => 'agency']);
        $ws = Workspace::create(['tenant_id' => $tenant->id, 'organization_id' => $org->id, 'name' => 'ws', 'slug' => 'ws']);
        $user = User::create(['name' => $slug, 'email' => $slug . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $tenant->id, 'organization_id' => $org->id, 'workspace_id' => $ws->id, 'user_id' => $user->id, 'role' => $role, 'status' => $memberStatus]);
        TenantContext::reset();
        return [$user, $tenant, $org, $ws];
    }

    private function noteFor(Tenant $tenant, int $wsId, int $userId, string $body): Note
    {
        TenantContext::set($tenant->id);
        $n = Note::create(['workspace_id' => $wsId, 'user_id' => $userId, 'body' => $body]);
        TenantContext::reset();
        return $n;
    }

    public function test_user_cannot_read_update_delete_other_tenant_note_over_http(): void
    {
        [$ua, $ta, , $wsa] = $this->actor('t-a');
        [$ub] = $this->actor('t-b');
        $noteA = $this->noteFor($ta, $wsa->id, $ua->id, 'سرّي A');

        Sanctum::actingAs($ub);
        // 1) قراءة عبر ID (IDOR) → 404
        $this->getJson("/api/v1/notes/{$noteA->id}")->assertNotFound();
        // 2) تعديل → 404
        $this->putJson("/api/v1/notes/{$noteA->id}", ['body' => 'اختراق'])->assertNotFound();
        // 3) حذف → 404
        $this->deleteJson("/api/v1/notes/{$noteA->id}")->assertNotFound();
        // القائمة لا تتضمن ملاحظة A
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonPath('data', []);
        // لم تُعدَّل/تُحذَف فعليًا
        TenantContext::bypass(true);
        $this->assertEquals('سرّي A', Note::find($noteA->id)->body);
        TenantContext::reset();
    }

    public function test_owner_can_access_own_note_route_binding_respects_tenant(): void
    {
        [$ua, $ta, , $wsa] = $this->actor('t-own');
        $noteA = $this->noteFor($ta, $wsa->id, $ua->id, 'ملكي');
        Sanctum::actingAs($ua);
        $this->getJson("/api/v1/notes/{$noteA->id}")->assertOk()->assertJsonPath('data.body', 'ملكي');
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_user_without_active_membership_is_fail_closed(): void
    {
        [$u, $t, , $ws] = $this->actor('t-noctx', 'viewer', 'suspended'); // عضوية معطّلة
        $this->noteFor($t, $ws->id, $u->id, 'x');
        Sanctum::actingAs($u);
        // لا سياق مستأجر (لا عضوية فعّالة) → لا بيانات
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonPath('data', []);
    }

    public function test_create_is_scoped_to_actors_tenant(): void
    {
        [$ua] = $this->actor('t-create');
        Sanctum::actingAs($ua);
        $id = $this->postJson('/api/v1/notes', ['body' => 'جديد'])->assertCreated()->json('data.id');
        // الملاحظة أُنشئت تحت مستأجر المُنشئ فقط
        TenantContext::bypass(true);
        $this->assertEquals($ua->memberships()->first()->tenant_id, Note::find($id)->tenant_id);
        TenantContext::reset();
    }

    public function test_queue_job_preserves_tenant_id_and_reinitializes_context(): void
    {
        [$ua, $ta] = $this->actor('t-queue');
        // تنفيذ متزامن (sync) يحاكي الـWorker
        (new CreateTenantNoteJob($ta->id, 'من الطابور'))->handle();
        TenantContext::set($ta->id);
        $this->assertEquals(1, Note::where('body', 'من الطابور')->count());
        $this->assertEquals($ta->id, Note::where('body', 'من الطابور')->first()->tenant_id);
        TenantContext::reset();
    }

    public function test_cache_keys_are_isolated_per_tenant(): void
    {
        [$ua, $ta] = $this->actor('t-c1');
        [$ub, $tb] = $this->actor('t-c2');
        TenantContext::set($ta->id); TenantCache::put('k', 'A');
        TenantContext::set($tb->id); TenantCache::put('k', 'B');
        TenantContext::set($ta->id); $this->assertEquals('A', TenantCache::get('k'));
        TenantContext::set($tb->id); $this->assertEquals('B', TenantCache::get('k'));
        TenantContext::reset();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/notes')->assertStatus(401);
    }
}
