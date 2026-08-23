<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * حجب مركز المعاينة في الإنتاج — بعَلَم صريح لا يعتمد على APP_ENV.
 *
 * ثغرة إنتاجية حقيقية: كانت البوابة `app()->environment('production')`، وعند إساءة
 * ضبط APP_ENV في الإنتاج كان مركز المعاينة (أداة تطوير) يظهر ويكشف مسارات وأزرار
 * هدّامة. البوابة الآن `config('app.dev_tools')` وافتراضها false.
 */
class PreviewCenterGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agencyAdmin(): User
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);

        return TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);

            return $u;
        });
    }

    public function test_preview_center_blocked_when_dev_tools_off(): void
    {
        config(['app.dev_tools' => false]); // كما في الإنتاج
        $u = $this->agencyAdmin();

        $this->actingAs($u)->get('/app/preview')->assertNotFound();
        $this->actingAs($u)->get('/app/preview/design-system')->assertNotFound();
        $this->actingAs($u)->post('/app/preview/showcase/reset')->assertNotFound();

        // ورابط القائمة محجوب (dev_tools=false في الحمولة المشتركة)
        $this->actingAs($u)->get('/app')->assertInertia(fn ($p) => $p->where('nav.can.dev_tools', false));
    }

    public function test_preview_center_available_when_dev_tools_on(): void
    {
        config(['app.dev_tools' => true]); // كما في التطوير/الاختبار
        $u = $this->agencyAdmin();

        $this->actingAs($u)->get('/app/preview')->assertOk();
        $this->actingAs($u)->get('/app')->assertInertia(fn ($p) => $p->where('nav.can.dev_tools', true));
    }
}
