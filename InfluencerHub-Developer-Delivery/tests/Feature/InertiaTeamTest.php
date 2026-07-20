<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** الفريق React/Inertia — أعضاء المؤسسة وأدوارهم + بوابة الإدارة + العزل. */
class InertiaTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Tenant,2:Organization} */
    private function org(string $role = 'agency_admin', int $extra = 0): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $o = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $o->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        for ($i = 0; $i < $extra; $i++) {
            $m = User::create(['name' => "عضو$i", 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $o->id, 'user_id' => $m->id, 'role' => 'campaign_manager', 'status' => 'active']);
        }
        TenantContext::reset();
        return [$u, $t, $o];
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/team')->assertRedirect('/login');
    }

    public function test_admin_sees_members_with_arabic_roles(): void
    {
        [$u] = $this->org('agency_admin', 2);
        $this->actingAs($u)->get('/beta/team')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Index')
                ->where('summary.total', 3)
                ->where('summary.active', 3)
                ->has('members', 3)
                ->has('byRole')
                // لا مفاتيح داخلية في الواجهة
                ->where('members.0.roleLabel', fn ($l) => ! str_contains((string) $l, '_')));
    }

    public function test_non_admin_denied(): void
    {
        [$u] = $this->org('campaign_manager');
        $this->actingAs($u)->get('/beta/team')->assertForbidden();
    }

    public function test_isolated_to_own_organization(): void
    {
        $this->org('agency_admin', 3);      // مؤسسة أخرى بأعضاء
        [$u2] = $this->org('agency_admin');  // مؤسسة المستخدم: عضو واحد
        $this->actingAs($u2)->get('/beta/team')
            ->assertInertia(fn (Assert $page) => $page->where('summary.total', 1)->has('members', 1));
    }
}
