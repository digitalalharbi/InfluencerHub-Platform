<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** معرض البريد التطويريّ: متاح لعضو الوكالة حين dev_tools مفعّل، و404 في الإنتاج. */
class DevMailGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function agencyUser(): User
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return $u;
    }

    public function test_gallery_available_when_dev_tools_enabled(): void
    {
        config(['app.dev_tools' => true]);
        $u = $this->agencyUser();
        $this->actingAs($u)->get('/app/preview/mail')->assertOk()->assertSee('معرض بريد');
        $this->actingAs($u)->get('/app/preview/mail/action_required?locale=en')->assertOk()->assertSee('Open in', false);
        $this->actingAs($u)->get('/app/preview/mail/action_required?locale=ar')->assertOk()->assertSee('الفتح في', false);
    }

    public function test_gallery_hidden_when_dev_tools_disabled(): void
    {
        config(['app.dev_tools' => false]);
        $u = $this->agencyUser();
        $this->actingAs($u)->get('/app/preview/mail')->assertNotFound();
        $this->actingAs($u)->get('/app/preview/mail/action_required')->assertNotFound();
    }
}
