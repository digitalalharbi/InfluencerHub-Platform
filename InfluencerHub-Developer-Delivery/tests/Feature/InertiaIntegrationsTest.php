<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** التكاملات React/Inertia — سجل منصّات صادق (لا "connected" وهمي). */
class InertiaIntegrationsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agent(string $role = 'agency_admin'): User
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();
        return $u;
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/integrations')->assertRedirect('/login');
    }

    public function test_renders_honest_registry(): void
    {
        $u = $this->agent();
        $this->actingAs($u)->get('/beta/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Integrations/Index')
                ->has('platforms')
                ->has('summary.total')->has('summary.available')
                // لا حالة "connected" وهمية — كل المتاح يدوي
                ->where('platforms.0.status', fn ($s) => $s !== 'connected'));
    }
}
