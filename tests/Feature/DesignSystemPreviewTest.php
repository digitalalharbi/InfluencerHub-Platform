<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** معرض نظام التصميم (ih-) — صفحة تطوير: تُعرض للمصادَق في dev، وتُحجب 404 في الإنتاج. */
class DesignSystemPreviewTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agencyUser(): User
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();
        TenantContext::set($t->id, $org->id);
        return $u;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/app/preview/design-system')->assertRedirect('/login');
    }

    public function test_renders_for_authenticated_user_in_non_production(): void
    {
        $u = $this->agencyUser();
        $this->actingAs($u)->get('/app/preview/design-system')
            ->assertOk()
            ->assertSee('نظام التصميم')
            ->assertSee('ih-status-under_review', false)  // نظام الحالات الموحّد
            ->assertSee('#6252E5');                         // لون الهوية
    }

    public function test_is_blocked_with_404_in_production(): void
    {
        $u = $this->agencyUser();
        app()->detectEnvironment(fn () => 'production');
        $this->actingAs($u)->get('/app/preview/design-system')->assertNotFound();
    }
}
