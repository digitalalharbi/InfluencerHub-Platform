<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\AddOn;
use App\Domain\Billing\Models\OrganizationAddOn;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\PlanEntitlement;
use App\Domain\Billing\Models\PlanVersion;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * قاعدة المؤثرين (المنتج المميّز) — استحقاق المؤسسة + RBAC + خصوصية المصدر.
 *
 * ثلاث ضمانات حرجة تُفرَض في الخادم:
 *  1) لا تصل المؤسسة بلا استحقاق (خطة/إضافة/تجاوز)، ويُرفَض المُلغى.
 *  2) دور المستخدم يحكم التصفّح وكشف التواصل.
 *  3) لا يتسرّب أيّ مصدر/متجر/موظّف/بنك/عنوان/تكلفة في الحمولة أبدًا.
 */
class CreatorDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** ينشئ مؤسسة وكالة + مستخدمًا بدور، مع/بلا استحقاق قاعدة المؤثرين. @return array{0:User,1:Organization,2:PlanVersion} */
    private function agency(bool $access, string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);

        return TenantContext::withBypass(function () use ($t, $access, $role) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
            $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
            PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_database.access', 'value' => $access ? 1 : 0]);
            (new CreateSubscription)->handle($org, $pv);
            $u = User::create(['name' => 'م', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);

            return [$u, $org, $pv];
        });
    }

    /** مبدع قاعدة يحمل كلّ حقل محظور — لإثبات عدم تسرّبه. */
    private function poolCreator(array $o = []): PoolCreator
    {
        return PoolCreator::create(array_merge([
            'name' => 'مبدع تجريبي', 'phone' => '0501234567', 'platform' => 'tiktok',
            'account_url' => 'https://www.tiktok.com/@demo'.Str::random(4), 'followers' => 120000,
            'tier' => 'B', 'gender' => 'female', 'categories' => ['أسلوب حياة', 'تغطية'],
            'price_post_minor' => 300000, 'price_coverage_minor' => 500000,
            'cost_post_minor' => 150000, 'cost_coverage_minor' => 250000, // تكلفة داخلية — يجب ألّا تظهر
            'shows_face' => false, 'region' => 'الوسطى', 'city' => 'الرياض', 'rating' => 'جيد',
            'likes' => 6000, 'store' => 'وزنة', 'source_type' => 'ugc', // متجر/تصنيف — المتجر محظور
            'imported_at' => now(),
        ], $o));
    }

    // ==================== الاستحقاق ====================

    public function test_no_entitlement_is_denied(): void
    {
        [$u] = $this->agency(access: false);
        $this->actingAs($u)->get('/app/creator-database')->assertForbidden();
    }

    public function test_plan_entitlement_grants_access(): void
    {
        [$u] = $this->agency(access: true);
        $this->poolCreator();
        $this->actingAs($u)->get('/app/creator-database')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('CreatorDatabase/Index')->has('creators.data', 1));
    }

    public function test_addon_grants_access_without_plan(): void
    {
        [$u, $org] = $this->agency(access: false);
        TenantContext::withBypass(function () use ($org) {
            $addon = AddOn::create(['key' => 'cdb', 'label' => 'قاعدة المؤثرين', 'feature_key' => 'creator_database.access', 'grant_boolean' => true]);
            OrganizationAddOn::create(['tenant_id' => $org->tenant_id, 'organization_id' => $org->id, 'add_on_id' => $addon->id, 'quantity' => 1, 'status' => 'active']);
        });
        $this->actingAs($u)->get('/app/creator-database')->assertOk();
    }

    public function test_subscription_override_grants_access(): void
    {
        [$u, $org] = $this->agency(access: false);
        TenantContext::withBypass(function () use ($org) {
            $sub = Subscription::where('organization_id', $org->id)->firstOrFail();
            $sub->update(['overrides' => ['creator_database.access' => true]]);
        });
        $this->actingAs($u)->get('/app/creator-database')->assertOk();
    }

    // ==================== RBAC ====================

    public function test_viewer_can_browse_but_gets_no_contact(): void
    {
        [$u] = $this->agency(access: true, role: 'viewer');
        $this->poolCreator();
        $this->actingAs($u)->get('/app/creator-database')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('canContact', false)
                ->missing('creators.data.0.contact')); // مفتاح التواصل غائب تمامًا (لا تسرّب)
    }

    public function test_manager_gets_contact(): void
    {
        [$u] = $this->agency(access: true, role: 'agency_admin');
        $this->poolCreator(['phone' => '0555555555']);
        $this->actingAs($u)->get('/app/creator-database')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('canContact', true)
                ->where('creators.data.0.contact.phone', '966555555555'));
    }

    public function test_non_member_denied(): void
    {
        // مستخدم بلا عضوية وكالة — الوسيط/الحارس يمنعه
        $u = User::create(['name' => 'x', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $this->actingAs($u)->get('/app/creator-database')->assertForbidden();
    }

    // ==================== خصوصية المصدر (تسلسل) ====================

    public function test_payload_never_exposes_source_or_private_fields(): void
    {
        [$u] = $this->agency(access: true);
        $c = $this->poolCreator();
        $res = $this->actingAs($u)->get('/app/creator-database');
        $res->assertOk();
        $json = json_encode($res->viewData('page')['props'], JSON_UNESCAPED_UNICODE);

        // لا مفاتيح مصدر/متجر/موظّف/بنك/تكلفة
        foreach (['store', 'sourceType', 'source_type', 'costPost', 'costCoverage', 'cost_post_minor', 'employee', 'sellPost'] as $forbidden) {
            $this->assertStringNotContainsString('"'.$forbidden.'"', $json, "المفتاح المحظور {$forbidden} ظهر في الحمولة");
        }
        // لا قيم مصدر/بنك/عنوان
        foreach (['وزنة', 'اسم الموظف', 'المتجر', 'IBAN', 'رقم الحساب'] as $val) {
            $this->assertStringNotContainsString($val, $json, "قيمة محظورة «{$val}» ظهرت في الحمولة");
        }
        // «نوع المبدع» تصنيف لا مصدر — موجود بمفتاح creatorType
        $this->assertStringContainsString('creatorType', $json);
    }

    public function test_show_profile_also_hides_source(): void
    {
        [$u] = $this->agency(access: true);
        $c = $this->poolCreator();
        $res = $this->actingAs($u)->get('/app/creator-database/'.$c->id);
        $res->assertOk();
        $json = json_encode($res->viewData('page')['props'], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('وزنة', $json);
        $this->assertStringNotContainsString('costPost', $json);
    }

    // ==================== ترقيم ====================

    public function test_index_paginates(): void
    {
        [$u] = $this->agency(access: true);
        for ($i = 0; $i < 30; $i++) {
            $this->poolCreator(['account_url' => 'https://www.tiktok.com/@p'.$i]);
        }
        $this->actingAs($u)->get('/app/creator-database')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('creators.data', 24)->where('creators.total', 30));
    }

    // ==================== تصنيف المحتوى (اكتشاف) ====================

    public function test_category_facet_reports_real_counts(): void
    {
        [$u] = $this->agency(access: true);
        $this->poolCreator(['account_url' => 'https://tt/@a', 'categories' => ['أزياء', 'جمال']]);
        $this->poolCreator(['account_url' => 'https://tt/@b', 'categories' => ['أزياء']]);
        $this->poolCreator(['account_url' => 'https://tt/@c', 'categories' => ['تقنية']]);

        $this->actingAs($u)->get('/app/creator-database')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('facets.categories.أزياء', 2)
                ->where('facets.categories.جمال', 1)
                ->where('facets.categories.تقنية', 1));
    }

    public function test_category_filter_narrows_to_real_matches(): void
    {
        [$u] = $this->agency(access: true);
        $this->poolCreator(['account_url' => 'https://tt/@a', 'categories' => ['أزياء']]);
        $this->poolCreator(['account_url' => 'https://tt/@b', 'categories' => ['تقنية']]);

        $this->actingAs($u)->get('/app/creator-database?category='.urlencode('أزياء'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('creators.total', 1)->where('filters.category', 'أزياء'));
    }

    public function test_has_price_filter_only_priced_creators(): void
    {
        [$u] = $this->agency(access: true);
        $this->poolCreator(['account_url' => 'https://tt/@priced', 'price_post_minor' => 300000, 'price_coverage_minor' => 0]);
        $this->poolCreator(['account_url' => 'https://tt/@free', 'price_post_minor' => 0, 'price_coverage_minor' => 0]);

        $this->actingAs($u)->get('/app/creator-database?has_price=1')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('creators.total', 1));
    }
}
