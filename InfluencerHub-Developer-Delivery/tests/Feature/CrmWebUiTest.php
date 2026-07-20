<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\CRM\Actions\CreateClient;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Phase 3 — واجهة CRM (Blade/جلسة): بوابة الدخول + عرض + إنشاء + عزل المستأجر. لا بيانات وهمية. */
class CrmWebUiTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function actor(int $max = 5, string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'customers.max', 'value' => $max]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        TenantContext::set($t->id, $org->id);
        return [$u, $org, $t];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/app')->assertRedirect('/login');
        $this->get('/app/clients')->assertRedirect('/login');
    }

    public function test_login_page_renders_rtl_arabic(): void
    {
        $this->get('/login')->assertOk()->assertSee('تسجيل الدخول')->assertSee('dir="rtl"', false);
    }

    public function test_dashboard_renders_for_authenticated_user(): void
    {
        [$u, $org] = $this->actor();
        app(CreateClient::class)->handle($org, ['display_name' => 'عميل لوحة', 'status' => 'active', 'type' => 'company'], $u);
        // اللوحة صارت React حسب الدور — نتحقّق من المكوّن والحمولة
        $this->actingAs($u)->get('/app')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('base', '/app')
                ->has('brief')
                ->has('myWork'));
    }

    public function test_clients_index_lists_real_db_data(): void
    {
        [$u, $org] = $this->actor();
        app(CreateClient::class)->handle($org, ['display_name' => 'عميل حقيقي', 'status' => 'active', 'type' => 'company'], $u);
        // ‏/app/clients صار React/Inertia — نتحقّق من الحمولة لا من HTML الخادم
        $this->actingAs($u)->get('/app/clients')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Clients/Index')
                ->where('clients.data.0.name', 'عميل حقيقي'));
    }

    public function test_create_client_via_web_form(): void
    {
        [$u] = $this->actor();
        // بعد التحويل يفتح الإنشاء صفحة العميل مباشرة داخل /app
        TenantContext::bypass(true);
        $this->actingAs($u)->post('/app/clients', ['display_name' => 'عميل نموذج', 'status' => 'active', 'type' => 'company'])
            ->assertRedirect('/app/clients/' . Client::where('display_name', 'عميل نموذج')->firstOrFail()->id);
        $this->assertDatabaseHas('clients', ['display_name' => 'عميل نموذج']);
        TenantContext::reset();
    }

    public function test_web_ui_is_tenant_isolated(): void
    {
        [$uA, $orgA] = $this->actor();
        $clientA = app(CreateClient::class)->handle($orgA, ['display_name' => 'سرّي', 'status' => 'active', 'type' => 'company'], $uA);
        TenantContext::reset();

        [$uB] = $this->actor();
        // مستخدم مستأجر آخر لا يرى عميل A في القائمة ولا صفحته
        $this->actingAs($uB)->get('/app/clients')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('clients.data', 0));
        $this->actingAs($uB)->get("/app/clients/{$clientA->id}")->assertNotFound();
    }

    public function test_viewer_cannot_create_via_web(): void
    {
        [$u] = $this->actor(5, 'viewer');
        $this->actingAs($u)->post('/app/clients', ['display_name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
