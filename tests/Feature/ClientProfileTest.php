<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 5 — ملف العميل: تعديل مباشر، حساس→طلب مراجعة، حقول محظورة، تدرّج الأدوار، الفوترة. */
class ClientProfileTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function member(string $role): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'c', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-1', 'display_name' => 'عميل', 'legal_name' => 'قديم', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $c, $t];
    }
    private function fresh(Client $c): Client { TenantContext::bypass(true); $f = $c->fresh(); TenantContext::reset(); return $f; }

    public function test_client_admin_edits_direct_fields_immediately(): void
    {
        [$u, $c] = $this->member('client_admin');
        $this->actingAs($u)->post('/client/profile', ['display_name' => 'اسم جديد', 'city' => 'الرياض'])->assertRedirect();
        $this->assertEquals('اسم جديد', $this->fresh($c)->display_name);
    }

    public function test_sensitive_legal_change_goes_to_review_not_applied(): void
    {
        [$u, $c] = $this->member('client_admin');
        $this->actingAs($u)->post('/client/profile', ['display_name' => 'عميل', 'legal_name' => 'اسم قانوني جديد', 'tax_number' => '3001'])->assertRedirect();
        // لم يُطبَّق على العميل
        $this->assertEquals('قديم', $this->fresh($c)->legal_name);
        // أُنشئ طلب مراجعة
        TenantContext::bypass(true);
        $this->assertDatabaseHas('client_profile_change_requests', ['client_id' => $c->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('client_profile_status_history', ['to_status' => 'submitted']);
        TenantContext::reset();
    }

    public function test_forbidden_fields_not_changed_even_if_injected(): void
    {
        [$u, $c, $t] = $this->member('client_admin');
        $this->actingAs($u)->post('/client/profile', ['display_name' => 'x', 'tenant_id' => 999, 'status' => 'suspended', 'account_manager_id' => 999]);
        $f = $this->fresh($c);
        $this->assertEquals($t->id, $f->tenant_id);      // لم يتغيّر
        $this->assertEquals('active', $f->status);        // لم يتغيّر
    }

    public function test_client_member_cannot_edit_profile(): void
    {
        [$u, $c] = $this->member('client_member');
        $this->actingAs($u)->post('/client/profile', ['display_name' => 'x'])->assertSessionHasErrors('form');
        $this->assertEquals('عميل', $this->fresh($c)->display_name);
    }

    public function test_finance_manages_billing_but_not_profile(): void
    {
        [$u, $c] = $this->member('client_finance');
        // لا يعدّل الملف
        $this->actingAs($u)->post('/client/profile', ['display_name' => 'x'])->assertSessionHasErrors('form');
        // يدير الفوترة
        $this->actingAs($u)->post('/client/billing-profile', ['default_currency' => 'USD', 'payment_terms_days' => 30])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertDatabaseHas('client_billing_profiles', ['client_id' => $c->id, 'default_currency' => 'USD', 'payment_terms_days' => 30]);
        TenantContext::reset();
    }

    public function test_client_member_cannot_edit_billing(): void
    {
        [$u, $c] = $this->member('client_member');
        $this->actingAs($u)->post('/client/billing-profile', ['default_currency' => 'USD'])->assertForbidden();
    }
}
