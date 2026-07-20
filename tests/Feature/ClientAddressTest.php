<?php

namespace Tests\Feature;

use App\Domain\CRM\Actions\ClientAddressActions;
use App\Domain\CRM\Models\{Client, ClientAddress, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 5 — عناوين العميل: إنشاء/تعديل/افتراضي فريد/أرشفة/استعادة/عزل. */
class ClientAddressTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(string $role = 'client_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'c', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-1', 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $c, $t];
    }
    private function act(): ClientAddressActions { return app(ClientAddressActions::class); }
    private function fresh(ClientAddress $a): ClientAddress { TenantContext::bypass(true); $f = $a->fresh(); TenantContext::reset(); return $f; }

    public function test_create_and_update_address(): void
    {
        [$u, $c] = $this->ctx();
        $a = $this->act()->create($c, ['type' => 'shipping', 'city' => 'الرياض'], $u->id);
        $this->assertDatabaseHas('client_addresses', ['client_id' => $c->id, 'city' => 'الرياض']);
        $this->act()->update($a, ['type' => 'shipping', 'city' => 'جدة'], $u->id);
        $this->assertEquals('جدة', $this->fresh($a)->city);
    }

    public function test_default_is_unique_per_type(): void
    {
        [$u, $c] = $this->ctx();
        $a1 = $this->act()->create($c, ['type' => 'shipping', 'is_default' => true], $u->id);
        $a2 = $this->act()->create($c, ['type' => 'shipping', 'is_default' => true], $u->id);
        $this->assertFalse($this->fresh($a1)->is_default); // أُزيل الافتراضي عن الأول
        $this->assertTrue($this->fresh($a2)->is_default);
        // نوع آخر له افتراضيّه المستقل
        $b = $this->act()->create($c, ['type' => 'billing', 'is_default' => true], $u->id);
        $this->assertTrue($this->fresh($b)->is_default);
        $this->assertTrue($this->fresh($a2)->is_default); // shipping لم يتأثّر
    }

    public function test_archive_clears_default_and_restore_works(): void
    {
        [$u, $c] = $this->ctx();
        $a = $this->act()->create($c, ['type' => 'shipping', 'is_default' => true], $u->id);
        $this->act()->archive($a, $u->id);
        $this->assertNotNull($this->fresh($a)->archived_at);
        $this->assertFalse($this->fresh($a)->is_default); // المؤرشف لا يبقى افتراضيًا
        $this->act()->restore($a, $u->id);
        $this->assertNull($this->fresh($a)->archived_at);
    }

    public function test_cannot_set_archived_as_default(): void
    {
        [$u, $c] = $this->ctx();
        $a = $this->act()->create($c, ['type' => 'shipping'], $u->id);
        $this->act()->archive($a, $u->id);
        $this->expectException(\RuntimeException::class);
        $this->act()->setDefault($this->fresh($a), $u->id);
    }

    public function test_active_client_isolation_over_http(): void
    {
        [$u1, $c1] = $this->ctx();
        [$u2, $c2] = $this->ctx();
        $aOther = $this->act()->create($c2, ['type' => 'shipping'], $u2->id);
        // مستخدم عميل 1 لا يعدّل عنوان عميل 2 (لا يُثق بـid) → 404
        $this->actingAs($u1)->post("/client/addresses/{$aOther->id}/default");
        // الخاصية الأمنية: عنوان عميل آخر لم يُعدَّل (لم يُصبح افتراضيًا) — منع IDOR
        TenantContext::bypass(true);
        $this->assertFalse(ClientAddress::find($aOther->id)->is_default);
        TenantContext::reset();
    }

    public function test_member_role_cannot_manage_addresses(): void
    {
        [$u, $c] = $this->ctx('client_member');
        $this->actingAs($u)->post('/client/addresses', ['type' => 'shipping'])->assertForbidden();
    }

    public function test_http_create_uses_active_client_not_form_client_id(): void
    {
        [$u, $c] = $this->ctx();
        // نحاول حقن client_id مختلف → يُتجاهل، يُستخدم العميل النشِط
        $this->actingAs($u)->post('/client/addresses', ['type' => 'branch', 'city' => 'الدمام', 'client_id' => 99999])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertDatabaseHas('client_addresses', ['client_id' => $c->id, 'city' => 'الدمام']);
        $this->assertDatabaseMissing('client_addresses', ['client_id' => 99999]);
        TenantContext::reset();
    }
}
