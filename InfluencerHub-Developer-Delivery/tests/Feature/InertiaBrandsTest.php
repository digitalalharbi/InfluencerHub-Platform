<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** العلامات React/Inertia — قائمة اعتماد + تفاصيل بإجراءات مراجعة (approve/request-changes) + عزل. */
class InertiaBrandsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(array $brandStatuses = [], string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        foreach ($brandStatuses as $i => $st) {
            Brand::create(['tenant_id' => $t->id, 'client_id' => $cl->id, 'name' => 'علامة' . $i, 'slug' => Str::random(6), 'status' => $st, 'current_version' => 1]);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    private function brand(int $tenantId): Brand
    {
        TenantContext::bypass(true);
        $b = Brand::where('tenant_id', $tenantId)->first();
        TenantContext::reset();
        return $b;
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/brands')->assertRedirect('/login');
    }

    public function test_renders_list_with_summary(): void
    {
        [, , $u] = $this->agency(['approved', 'under_review', 'submitted']);
        $this->actingAs($u)->get('/beta/brands')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Brands/Index')
                ->where('summary.total', 3)
                ->where('summary.needs_review', 2)
                ->has('brands.data', 3));
    }

    public function test_detail_shows_review_actions(): void
    {
        [$t, , $u] = $this->agency(['under_review']);
        $b = $this->brand($t->id);
        $this->actingAs($u)->get("/beta/brands/{$b->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Brands/Show')->where('brand.id', $b->id)
                ->where('canReview', true)->has('actions')->has('decisions')->has('history'));
    }

    public function test_approve_action_via_workflow(): void
    {
        [$t, , $u] = $this->agency(['under_review']);
        $b = $this->brand($t->id);
        $this->actingAs($u)->post("/beta/brands/{$b->id}/approve")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('approved', $b->fresh()->status);
        TenantContext::reset();
    }

    public function test_request_changes_requires_reason(): void
    {
        [$t, , $u] = $this->agency(['under_review']);
        $b = $this->brand($t->id);
        $this->actingAs($u)->post("/beta/brands/{$b->id}/request-changes", [])->assertSessionHasErrors('reason');
        $this->actingAs($u)->post("/beta/brands/{$b->id}/request-changes", ['reason' => 'حدّث الشعار'])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('changes_requested', $b->fresh()->status);
        TenantContext::reset();
    }

    public function test_viewer_cannot_review(): void
    {
        [$t, , $u] = $this->agency(['under_review'], 'viewer');
        $b = $this->brand($t->id);
        $this->actingAs($u)->get("/beta/brands/{$b->id}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReview', false)->where('actions', []));
        $this->actingAs($u)->post("/beta/brands/{$b->id}/approve")->assertForbidden();
    }

    public function test_detail_is_idor_safe(): void
    {
        [$t1] = $this->agency(['approved']);
        $other = $this->brand($t1->id);
        [, , $u2] = $this->agency([]);
        $this->actingAs($u2)->get("/beta/brands/{$other->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade ===== */

    public function test_app_brands_renders_react_list(): void
    {
        [, , $u] = $this->agency(['approved', 'under_review', 'submitted']);
        $this->actingAs($u)->get('/app/brands')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Brands/Index')
                ->where('base', '/app')
                ->where('summary.needs_review', 2)
                ->has('brands.data', 3));
    }

    /** بحث الاسم كان فلتر Blade الوحيد — يجب أن يبقى عاملًا. */
    public function test_app_brands_name_search_still_works(): void
    {
        [, , $u] = $this->agency(['approved', 'approved']);
        $this->actingAs($u)->get('/app/brands?q=' . urlencode('علامة0'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('brands.data', 1));
    }

    public function test_app_brand_detail_renders_react(): void
    {
        [$t, , $u] = $this->agency(['under_review']);
        $b = $this->brand($t->id);
        $this->actingAs($u)->get("/app/brands/{$b->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Brands/Show')->where('base', '/app'));
    }

    public function test_app_brand_action_changes_status(): void
    {
        [$t, , $u] = $this->agency(['under_review']);
        $b = $this->brand($t->id);
        $this->actingAs($u)->post("/app/brands/{$b->id}/approve")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('approved', Brand::find($b->id)->status);
        TenantContext::reset();
    }

    public function test_app_brand_action_denied_for_viewer(): void
    {
        [$t, , $u] = $this->agency(['under_review'], 'viewer');
        $b = $this->brand($t->id);
        $this->actingAs($u)->post("/app/brands/{$b->id}/approve")->assertForbidden();
    }

    public function test_app_brands_guest_redirected(): void
    {
        $this->get('/app/brands')->assertRedirect('/login');
    }

    /* ===== تكافؤ سلوكي مع مسار مراجعة العلامات السابق ===== */

    /**
     * التعليق إجراء هدّام: مدير الحملات يملك التحرير لا الحذف، فلا يعلّق
     * ولا يُعرض له الزر — كما كان في /app/brand-reviews.
     */
    public function test_campaign_manager_cannot_suspend_brand(): void
    {
        [$t, , $u] = $this->agency(['approved'], 'campaign_manager');
        $b = $this->brand($t->id);

        $this->actingAs($u)->get("/app/brands/{$b->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canReview', true)
                ->where('actions', fn ($actions) => collect($actions)->every(fn ($a) => $a[0] !== 'suspend')));

        $this->actingAs($u)->post("/app/brands/{$b->id}/suspend")->assertForbidden();
    }

    public function test_agency_admin_can_suspend_brand(): void
    {
        [$t, , $u] = $this->agency(['approved'], 'agency_admin');
        $b = $this->brand($t->id);
        $this->actingAs($u)->post("/app/brands/{$b->id}/suspend", ['reason' => 'مخالفة'])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('suspended', Brand::find($b->id)->status);
        TenantContext::reset();
    }

    /** اعتماد العلامة يُخطر أعضاء بوابة العميل — القرار بلا إبلاغ يترك العميل ينتظر. */
    public function test_approve_notifies_client_members(): void
    {
        [$t, , $u] = $this->agency(['under_review']);
        $b = $this->brand($t->id);

        TenantContext::bypass(true);
        $member = User::create(['name' => 'عضو', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        \App\Domain\CRM\Models\ClientMember::create([
            'tenant_id' => $t->id, 'client_id' => $b->client_id, 'user_id' => $member->id,
            'role' => 'client_admin', 'status' => 'active',
        ]);
        TenantContext::reset();

        $this->actingAs($u)->post("/app/brands/{$b->id}/approve")->assertRedirect();

        TenantContext::bypass(true);
        $this->assertDatabaseHas('notifications', ['user_id' => $member->id, 'type' => 'brand.approved']);
        TenantContext::reset();
    }

    public function test_request_changes_notifies_client_members(): void
    {
        [$t, , $u] = $this->agency(['under_review']);
        $b = $this->brand($t->id);

        TenantContext::bypass(true);
        $member = User::create(['name' => 'عضو', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        \App\Domain\CRM\Models\ClientMember::create([
            'tenant_id' => $t->id, 'client_id' => $b->client_id, 'user_id' => $member->id,
            'role' => 'client_admin', 'status' => 'active',
        ]);
        TenantContext::reset();

        $this->actingAs($u)->post("/app/brands/{$b->id}/request-changes", ['reason' => 'الشعار غير واضح'])->assertRedirect();

        TenantContext::bypass(true);
        $this->assertDatabaseHas('notifications', ['user_id' => $member->id, 'type' => 'brand.changes_requested']);
        TenantContext::reset();
    }
}
