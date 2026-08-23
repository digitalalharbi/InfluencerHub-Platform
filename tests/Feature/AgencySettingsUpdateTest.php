<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * إعدادات مساحة العمل الفعلية — كانت الصفحة عرضًا فقط (index) بلا حفظ.
 * تُثبت حفظ الاسم/بريد التواصل، التحقّق، وبوابة الصلاحية.
 */
class AgencySettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Organization $org;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function boot(): void
    {
        $this->tenant = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $this->org = TenantContext::withBypass(fn () => Organization::create([
            'tenant_id' => $this->tenant->id, 'name' => 'وكالة قديمة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active',
        ]));
        TenantContext::set($this->tenant->id, $this->org->id);
    }

    private function user(string $role): User
    {
        return TenantContext::withBypass(function () use ($role) {
            $u = User::create(['name' => 'م' . Str::random(3), 'email' => Str::random(8) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $this->tenant->id, 'organization_id' => $this->org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
            return $u;
        });
    }

    public function test_admin_saves_name_and_contact_email(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');

        $this->actingAs($admin)->post('/app/settings', ['name' => 'وكالة جديدة', 'contact_email' => 'hi@agency.com'])
            ->assertRedirect()->assertSessionHas('ok');

        $fresh = TenantContext::withBypass(fn () => Organization::find($this->org->id));
        $this->assertSame('وكالة جديدة', $fresh->name);
        $this->assertSame('hi@agency.com', $fresh->contact_email);
    }

    public function test_name_is_required(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $this->actingAs($admin)->post('/app/settings', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_invalid_email_rejected(): void
    {
        $this->boot();
        $admin = $this->user('agency_admin');
        $this->actingAs($admin)->post('/app/settings', ['name' => 'اسم صحيح', 'contact_email' => 'not-an-email'])
            ->assertSessionHasErrors('contact_email');
    }

    public function test_non_admin_forbidden(): void
    {
        $this->boot();
        $this->user('agency_admin');
        $viewer = $this->user('viewer');
        $this->actingAs($viewer)->post('/app/settings', ['name' => 'محاولة'])->assertForbidden();

        $fresh = TenantContext::withBypass(fn () => Organization::find($this->org->id));
        $this->assertSame('وكالة قديمة', $fresh->name);
    }
}
