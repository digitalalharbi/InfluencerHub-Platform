<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** قائمة العملاء React/Inertia — عرض، عدّادات، عزل مستأجر، بوابة الصلاحية. */
class InertiaClientsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(int $clients = 0, string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        for ($i = 0; $i < $clients; $i++) {
            Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-' . $i, 'display_name' => 'ع' . $i, 'type' => 'company', 'status' => 'active']);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/clients')->assertRedirect('/login');
    }

    public function test_renders_clients_with_metrics(): void
    {
        [, , $u] = $this->agency(3);
        $this->actingAs($u)->get('/beta/clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->where('summary.total', 3)
                ->has('clients.data', 3)
                ->has('operational')
                ->where('canCreate', true));
    }

    public function test_viewer_cannot_create(): void
    {
        [, , $u] = $this->agency(1, 'viewer');
        $this->actingAs($u)->get('/beta/clients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
    }

    public function test_clients_are_tenant_isolated(): void
    {
        $this->agency(5);
        [, , $u2] = $this->agency(2);
        $this->actingAs($u2)->get('/beta/clients')
            ->assertInertia(fn (Assert $page) => $page->where('summary.total', 2)->has('clients.data', 2));
    }

    public function test_detail_renders_workspace(): void
    {
        [$t, , $u] = $this->agency(1);
        TenantContext::bypass(true);
        $c = Client::where('tenant_id', $t->id)->first();
        TenantContext::reset();
        $this->actingAs($u)->get("/beta/clients/{$c->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show')
                ->where('client.id', $c->id)
                ->has('metrics.revenueMinor')->has('risks')
                ->has('campaigns')->has('brands')->has('contacts')->has('team')
                ->has('content')->has('contracts')->has('payouts')
                // تبويبات المصطلحات المعتمدة (docs/PRODUCT-TERMINOLOGY.md)
                ->has('requests')->has('creators')->has('documents')->has('customFields')
                // مساحة العمل: الخطوة التالية + آخر نشاط
                ->has('activity')->has('nextAction'));
    }

    public function test_detail_is_idor_safe_across_tenants(): void
    {
        [$t1] = $this->agency(1);
        TenantContext::bypass(true);
        $other = Client::where('tenant_id', $t1->id)->first();
        TenantContext::reset();
        [, , $u2] = $this->agency(0);
        $this->actingAs($u2)->get("/beta/clients/{$other->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade — العميل وإجراءاته الفرعية ===== */

    private function clientId(int $tenantId): int
    {
        TenantContext::bypass(true);
        $id = Client::where('tenant_id', $tenantId)->first()->id;
        TenantContext::reset();

        return $id;
    }

    public function test_app_clients_renders_react_list(): void
    {
        [, , $u] = $this->agency(2);
        $this->actingAs($u)->get('/app/clients')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Clients/Index')->where('base', '/app')->has('clients.data', 2));
    }

    public function test_app_client_detail_exposes_action_abilities(): void
    {
        [$t, , $u] = $this->agency(1);
        $this->actingAs($u)->get('/app/clients/' . $this->clientId($t->id))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show')->where('base', '/app')
                ->where('can.update', true)->where('can.documents', true)->where('can.portal', true));
    }

    /** دور العرض يرى الصفحة بلا أي قدرة على الإجراءات الفرعية. */
    public function test_app_client_detail_hides_abilities_from_viewer(): void
    {
        [$t, , $u] = $this->agency(1, 'viewer');
        $this->actingAs($u)->get('/app/clients/' . $this->clientId($t->id))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)->where('can.documents', false)->where('can.portal', false)
                ->has('fieldDefinitions', 0));
    }

    public function test_app_store_brand_creates_brand(): void
    {
        [$t, , $u] = $this->agency(1);
        $id = $this->clientId($t->id);
        $this->actingAs($u)->post("/app/clients/{$id}/brands", ['name' => 'علامة جديدة', 'sector' => 'أزياء'])
            ->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame(1, \App\Domain\CRM\Models\Brand::where('client_id', $id)->count());
        TenantContext::reset();
    }

    public function test_app_store_brand_requires_name(): void
    {
        [$t, , $u] = $this->agency(1);
        $this->actingAs($u)->post('/app/clients/' . $this->clientId($t->id) . '/brands', [])
            ->assertSessionHasErrors('name');
    }

    public function test_app_store_contact_creates_contact(): void
    {
        [$t, , $u] = $this->agency(1);
        $id = $this->clientId($t->id);
        $this->actingAs($u)->post("/app/clients/{$id}/contacts", ['name' => 'سارة', 'email' => 's@ex.com'])
            ->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame(1, \App\Domain\CRM\Models\ClientContact::where('client_id', $id)->count());
        TenantContext::reset();
    }

    public function test_app_define_custom_field_then_set_value(): void
    {
        [$t, , $u] = $this->agency(1);
        $id = $this->clientId($t->id);
        $this->actingAs($u)->post("/app/clients/{$id}/custom-fields", [
            'key' => 'owner', 'label' => 'المسؤول', 'type' => 'text',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $def = \App\Domain\CRM\Models\CustomFieldDefinition::where('tenant_id', $t->id)->where('key', 'owner')->first();
        TenantContext::reset();
        $this->assertNotNull($def, 'لم يُعرَّف الحقل');

        $this->actingAs($u)->post("/app/clients/{$id}/custom-fields/{$def->id}/set", ['value' => 'ليان'])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    /** لا يجوز ضبط قيمة تعريف عائد لمستأجر آخر. */
    public function test_app_set_field_rejects_definition_from_another_tenant(): void
    {
        [$t, , $u] = $this->agency(1);
        [$other, , $ou] = $this->agency(1);
        $otherClient = $this->clientId($other->id);
        $this->actingAs($ou)->post("/app/clients/{$otherClient}/custom-fields", ['key' => 'x', 'label' => 'x', 'type' => 'text']);
        TenantContext::bypass(true);
        $foreign = \App\Domain\CRM\Models\CustomFieldDefinition::where('tenant_id', $other->id)->first();
        TenantContext::reset();

        $mine = $this->clientId($t->id);
        $this->actingAs($u)->post("/app/clients/{$mine}/custom-fields/{$foreign->id}/set", ['value' => 'تسريب'])
            ->assertNotFound();
    }

    public function test_app_invite_member_returns_token_once(): void
    {
        [$t, , $u] = $this->agency(1);
        $this->actingAs($u)->post('/app/clients/' . $this->clientId($t->id) . '/members/invite', [
            'email' => 'member@ex.com', 'role' => 'client_member',
        ])->assertRedirect()->assertSessionHas('invite_token');
    }

    public function test_app_sub_actions_denied_for_viewer(): void
    {
        [$t, , $u] = $this->agency(1, 'viewer');
        $id = $this->clientId($t->id);
        $this->actingAs($u)->post("/app/clients/{$id}/brands", ['name' => 'ممنوع'])->assertForbidden();
        $this->actingAs($u)->post("/app/clients/{$id}/contacts", ['name' => 'ممنوع'])->assertForbidden();
        $this->actingAs($u)->post("/app/clients/{$id}/members/invite", ['email' => 'a@b.co', 'role' => 'client_member'])->assertForbidden();
    }

    public function test_app_clients_guest_redirected(): void
    {
        $this->get('/app/clients')->assertRedirect('/login');
    }
}
