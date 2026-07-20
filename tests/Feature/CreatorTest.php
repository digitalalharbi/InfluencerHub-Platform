<?php

namespace Tests\Feature;

use App\Domain\Creators\Actions\CreateCreator;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 — المبدعون: إنشاء + ترقيم + عزل المستأجر + سياسات الأدوار. */
class CreatorTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(string $role = 'creator_manager'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $u = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();
        TenantContext::set($t->id, $org->id);
        return [$org, $u, $t];
    }

    public function test_create_creator_assigns_number_and_audits(): void
    {
        [$org, $u] = $this->ctx();
        $c = app(CreateCreator::class)->handle($org, ['display_name' => 'نورة', 'type' => 'influencer', 'status' => 'active'], $u);
        $this->assertStringStartsWith('CR-', $c->creator_number);
        $this->assertEquals('influencer', $c->type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'creator.created']);
    }

    public function test_invalid_type_is_rejected(): void
    {
        [$org, $u] = $this->ctx();
        $this->expectException(\RuntimeException::class);
        app(CreateCreator::class)->handle($org, ['display_name' => 'x', 'type' => 'robot'], $u);
    }

    public function test_creators_are_tenant_isolated(): void
    {
        [$orgA, $uA] = $this->ctx();
        $a = app(CreateCreator::class)->handle($orgA, ['display_name' => 'A', 'type' => 'influencer'], $uA);
        TenantContext::reset();
        [$orgB] = $this->ctx();
        $this->assertNull(Creator::find($a->id));           // مستأجر آخر لا يراه
        $this->assertEquals(0, Creator::count());
    }

    public function test_creator_number_is_sequential_per_tenant(): void
    {
        [$org, $u] = $this->ctx();
        $c1 = app(CreateCreator::class)->handle($org, ['display_name' => 'A', 'type' => 'influencer'], $u);
        $c2 = app(CreateCreator::class)->handle($org, ['display_name' => 'B', 'type' => 'ugc_creator'], $u);
        $this->assertNotEquals($c1->creator_number, $c2->creator_number);
    }

    public function test_policy_creator_manager_writes_campaign_manager_views_only(): void
    {
        [$org, $mgr] = $this->ctx('creator_manager');
        $this->assertTrue(Gate::forUser($mgr)->allows('create', Creator::class));

        [$org2, $cm] = $this->ctx('campaign_manager');
        $this->assertTrue(Gate::forUser($cm)->allows('viewAny', Creator::class)); // يرى
        $this->assertFalse(Gate::forUser($cm)->allows('create', Creator::class)); // لا ينشئ
    }

    public function test_policy_influencer_role_cannot_view(): void
    {
        [$org, $inf] = $this->ctx('influencer');
        $this->assertFalse(Gate::forUser($inf)->allows('viewAny', Creator::class));
    }
}
