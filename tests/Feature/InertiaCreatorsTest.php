<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** قائمة المبدعين React/Inertia — عرض مكوّن، عزل مستأجر، وصلاحية viewAny. */
class InertiaCreatorsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(int $creators = 0, string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        for ($i = 0; $i < $creators; $i++) {
            Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-' . $i, 'type' => 'influencer',
                'display_name' => 'مبدع' . $i, 'status' => 'active', 'followers_count' => 100000 + $i]);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/creators')->assertRedirect('/login');
    }

    public function test_renders_creators_with_summary(): void
    {
        [, , $u] = $this->agency(3);
        $this->actingAs($u)->get('/beta/creators')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Creators/Index')
                ->where('summary.total', 3)
                ->has('creators.data', 3)
                ->has('platformOptions'));
    }

    public function test_creators_are_tenant_isolated(): void
    {
        $this->agency(4);              // مستأجر آخر
        [, , $u2] = $this->agency(2);  // الحالي: 2
        $this->actingAs($u2)->get('/beta/creators')
            ->assertInertia(fn (Assert $page) => $page->where('summary.total', 2)->has('creators.data', 2));
    }

    public function test_detail_renders_intelligence_and_tabs(): void
    {
        [$t, , $u] = $this->agency(1);
        TenantContext::bypass(true);
        $cr = Creator::where('tenant_id', $t->id)->first();
        TenantContext::reset();
        $this->actingAs($u)->get("/beta/creators/{$cr->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Creators/Show')
                ->where('creator.id', $cr->id)
                ->has('intel.subscores', 7)
                ->has('intel.metrics')
                ->has('collaborations')->has('content')->has('contracts')->has('payouts'));
    }

    public function test_detail_is_idor_safe_across_tenants(): void
    {
        [$t1] = $this->agency(1);
        TenantContext::bypass(true);
        $other = Creator::where('tenant_id', $t1->id)->first();
        TenantContext::reset();
        [, , $u2] = $this->agency(0); // مستخدم مستأجر آخر
        $this->actingAs($u2)->get("/beta/creators/{$other->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade ===== */

    public function test_app_creators_renders_react_list(): void
    {
        [, , $u] = $this->agency(3);
        $this->actingAs($u)->get('/app/creators')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Creators/Index')->where('base', '/app')->has('creators.data', 3));
    }

    /** فلتر النوع كان في رابط القائمة (?type=) — يجب أن يبقى عاملًا بعد التحويل. */
    public function test_app_creators_type_filter_still_works(): void
    {
        [$t, , $u] = $this->agency(2);
        TenantContext::bypass(true);
        Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-U', 'type' => 'ugc_creator',
            'display_name' => 'صانع', 'status' => 'active', 'followers_count' => 500]);
        TenantContext::reset();
        $this->actingAs($u)->get('/app/creators?type=ugc_creator')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('creators.data', 1)->where('type', 'ugc_creator'));
    }

    public function test_app_creator_detail_renders_react(): void
    {
        [$t, , $u] = $this->agency(1);
        TenantContext::bypass(true);
        $id = Creator::where('tenant_id', $t->id)->first()->id;
        TenantContext::reset();
        $this->actingAs($u)->get("/app/creators/{$id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Creators/Show')->where('base', '/app'));
    }

    /** الإضافة تُنشئ سجلًا فعليًا وتعيد التوجيه داخل المجموعة نفسها. */
    public function test_app_store_creates_creator(): void
    {
        [$t, , $u] = $this->agency();
        $this->actingAs($u)->post('/app/creators', [
            'display_name' => 'نورة', 'capabilities' => ['influencer'], 'status' => 'prospect',
            'handle' => 'noura', 'followers_count' => 12000, 'city' => 'الرياض',
        ])->assertRedirect('/app/creators');

        TenantContext::bypass(true);
        $c = Creator::where('tenant_id', $t->id)->where('display_name', 'نورة')->first();
        TenantContext::reset();
        $this->assertNotNull($c, 'لم يُنشأ المبدع');
        $this->assertSame('influencer', $c->type);
    }

    public function test_beta_store_redirects_within_beta(): void
    {
        [, , $u] = $this->agency();
        $this->actingAs($u)->post('/beta/creators', ['display_name' => 'سارة', 'capabilities' => ['influencer']])
            ->assertRedirect('/beta/creators');
    }

    public function test_app_store_validates_required_name(): void
    {
        [, , $u] = $this->agency();
        $this->actingAs($u)->post('/app/creators', ['capabilities' => ['influencer']])
            ->assertSessionHasErrors('display_name');
    }

    public function test_app_store_rejects_invalid_capability(): void
    {
        [, , $u] = $this->agency();
        $this->actingAs($u)->post('/app/creators', ['display_name' => 'س', 'capabilities' => ['not_a_capability']])
            ->assertSessionHasErrors('capabilities.0');
    }

    public function test_app_store_denied_for_viewer(): void
    {
        [, , $u] = $this->agency(0, 'viewer');
        $this->actingAs($u)->post('/app/creators', ['display_name' => 'س', 'capabilities' => ['influencer']])
            ->assertForbidden();
    }

    public function test_app_creators_guest_redirected(): void
    {
        $this->get('/app/creators')->assertRedirect('/login');
    }
}
