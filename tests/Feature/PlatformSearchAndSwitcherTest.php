<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * P2 — مبدّل المستأجرين + البحث الشامل. حوكمة مالك المنصّة فقط، نتائج فعلية عابرة
 * للمستأجرين، رابط لكل نتيجة يدخل سياق المستأجر، وتدقيق البحث.
 */
class PlatformSearchAndSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::firstOrCreate(['email' => 'owner@platform.test'], ['name' => 'Owner', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true, 'is_platform_owner' => true])->save();
        return $u;
    }

    private function nonOwner(): User
    {
        $u = User::firstOrCreate(['email' => 'sys@platform.test'], ['name' => 'Sys', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true, 'is_platform_owner' => false])->save();
        return $u;
    }

    /** مستأجر «Acme» بمؤسسة ومستخدم عضو — بيانات بحث فعلية. */
    private function seedTenant(): Tenant
    {
        $t = Tenant::create(['name' => 'Acme Agency', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'Acme Org', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $member = User::create(['name' => 'Zaid Member', 'email' => 'zaid@acme.test', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $member->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();
        return $t;
    }

    public function test_non_owner_cannot_reach_tenants_detail_or_search(): void
    {
        $sys = $this->nonOwner();
        $t = $this->seedTenant();
        $this->actingAs($sys)->get('/platform/tenants')->assertForbidden();
        $this->actingAs($sys)->get("/platform/tenants/{$t->id}")->assertForbidden();
        $this->actingAs($sys)->get('/platform/search?q=Acme')->assertForbidden();
    }

    public function test_owner_sees_tenant_directory(): void
    {
        $this->seedTenant();
        $this->actingAs($this->owner())->get('/platform/tenants')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Platform/Tenants')
                ->where('total', fn ($v) => (int) $v >= 1)->has('tenants.data'));
    }

    public function test_owner_sees_tenant_detail_with_real_stats(): void
    {
        $t = $this->seedTenant();
        $this->actingAs($this->owner())->get("/platform/tenants/{$t->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Platform/TenantDetail')
                ->where('tenant.id', $t->id)
                ->where('stats.organizations', 1)
                ->where('stats.users', 1)
                ->where('portals.agency', true)
                ->where('portals.client', false));
    }

    public function test_global_search_finds_entities_across_types_with_context_links(): void
    {
        $t = $this->seedTenant();
        $res = $this->actingAs($this->owner())->getJson('/platform/search?q=Acme');
        $res->assertOk();
        $data = $res->json();
        $this->assertNotEmpty($data['results']);
        // يجد المستأجر ورابطه يدخل سياق المستأجر
        $tenantHit = collect($data['results'])->firstWhere('type', 'tenant');
        $this->assertNotNull($tenantHit);
        $this->assertSame("/platform/tenants/{$t->id}", $tenantHit['href']);

        // يبحث عبر أنواع مختلفة (مستخدم بالاسم)
        $userHit = $this->actingAs($this->owner())->getJson('/platform/search?q=Zaid')->json('results');
        $this->assertTrue(collect($userHit)->contains('type', 'user'));
    }

    public function test_search_requires_min_length_and_is_audited(): void
    {
        $this->actingAs($this->owner())->getJson('/platform/search?q=a')->assertOk()->assertJson(['results' => []]);

        $this->seedTenant();
        $this->actingAs($this->owner())->getJson('/platform/search?q=Acme')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.search']);
    }
}
