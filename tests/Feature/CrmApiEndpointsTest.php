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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Phase 3 — تغطية HTTP لنقاط CRM: brands/contacts/members/custom-fields + 403 السياسات. */
class CrmApiEndpointsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function actor(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $u = User::create(['name' => 'U', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'customers.max', 'value' => 10]);
        (new CreateSubscription)->handle($org, $v);
        TenantContext::reset();
        TenantContext::set($t->id, $org->id);
        return [$u, $org, $t];
    }

    private function client(Organization $org, User $u): Client
    {
        return app(CreateClient::class)->handle($org, ['display_name' => 'C', 'status' => 'active', 'type' => 'company'], $u);
    }

    public function test_brand_crud_over_http(): void
    {
        [$u, $org] = $this->actor();
        $client = $this->client($org, $u);
        Sanctum::actingAs($u);
        $id = $this->postJson("/api/v1/clients/{$client->id}/brands", ['name' => 'BrandX'])
            ->assertCreated()->json('data.id');
        $this->getJson("/api/v1/clients/{$client->id}/brands")->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/v1/clients/{$client->id}/brands/{$id}", ['name' => 'BrandY'])
            ->assertOk()->assertJsonPath('data.name', 'BrandY');
        $this->deleteJson("/api/v1/clients/{$client->id}/brands/{$id}")->assertOk();
    }

    public function test_contact_create_over_http(): void
    {
        [$u, $org] = $this->actor();
        $client = $this->client($org, $u);
        Sanctum::actingAs($u);
        $this->postJson("/api/v1/clients/{$client->id}/contacts", ['name' => 'جهة', 'email' => 'a@b.com'])
            ->assertCreated()->assertJsonPath('data.name', 'جهة');
    }

    public function test_member_invite_returns_raw_token_once(): void
    {
        [$u, $org] = $this->actor();
        $client = $this->client($org, $u);
        Sanctum::actingAs($u);
        $token = $this->postJson("/api/v1/clients/{$client->id}/members/invite", ['email' => 'm@ex.com', 'role' => 'client_admin'])
            ->assertCreated()->json('data.token');
        $this->assertNotEmpty($token);
        // الرمز الخام لا يُخزَّن؛ المخزَّن Hash
        $this->assertDatabaseMissing('client_member_invitations', ['token_hash' => $token]);
    }

    public function test_custom_field_define_and_set_over_http(): void
    {
        [$u, $org] = $this->actor();
        $client = $this->client($org, $u);
        Sanctum::actingAs($u);
        $defId = $this->postJson('/api/v1/custom-fields', ['entity_type' => 'client', 'key' => 'tier', 'label' => 'الفئة', 'type' => 'select', 'options' => ['gold', 'silver']])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/v1/clients/{$client->id}/custom-fields/{$defId}", ['value' => 'gold'])->assertOk();
        // خيار غير صالح يُرفض (422)
        $this->putJson("/api/v1/clients/{$client->id}/custom-fields/{$defId}", ['value' => 'bronze'])
            ->assertStatus(422)->assertJsonPath('error', 'custom_field_invalid');
    }

    public function test_viewer_role_gets_403_on_write(): void
    {
        [$u, $org] = $this->actor('viewer');
        $client = $this->client($org, $u); // أُنشئ عبر الأكشن مباشرة (يتجاوز HTTP)
        Sanctum::actingAs($u);
        $this->getJson("/api/v1/clients/{$client->id}")->assertOk();        // viewer يرى
        $this->postJson('/api/v1/clients', ['display_name' => 'X', 'status' => 'active'])->assertStatus(403); // لا ينشئ
        $this->deleteJson("/api/v1/clients/{$client->id}")->assertStatus(403); // لا يحذف
        $this->postJson("/api/v1/clients/{$client->id}/members/invite", ['email' => 'z@ex.com', 'role' => 'client_admin'])->assertStatus(403);
    }

    public function test_influencer_role_cannot_view_crm(): void
    {
        [$u, $org] = $this->actor('influencer');
        $client = $this->client($org, $u);
        Sanctum::actingAs($u);
        $this->getJson('/api/v1/clients')->assertStatus(403);
        $this->getJson("/api/v1/clients/{$client->id}")->assertStatus(403);
    }
}
