<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanPrice};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership, Note};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** بوابة مراجعة Phase 2 — immutability, bypass audit, integer money. */
class Phase2ReviewTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    public function test_audit_log_is_append_only(): void
    {
        $a = AuditLog::create(['action' => 'x', 'created_at' => now()]);
        $this->expectException(\RuntimeException::class);
        $a->update(['action' => 'tampered']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $a = AuditLog::create(['action' => 'x', 'created_at' => now()]);
        $this->expectException(\RuntimeException::class);
        $a->delete();
    }

    public function test_locked_plan_price_cannot_be_edited(): void
    {
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        $price = PlanPrice::create(['plan_version_id' => $v->id, 'currency' => 'SAR', 'interval' => 'monthly', 'amount_minor' => 9900]);
        $v->update(['is_locked' => true]);
        $this->expectException(\RuntimeException::class);
        $price->update(['amount_minor' => 100]);
    }

    public function test_money_is_integer_minor_units_not_float(): void
    {
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        $price = PlanPrice::create(['plan_version_id' => $v->id, 'currency' => 'SAR', 'interval' => 'monthly', 'amount_minor' => 12345]);
        $this->assertIsInt($price->fresh()->amount_minor);
        $this->assertSame(12345, $price->fresh()->amount_minor);
    }

    public function test_system_admin_bypass_requires_flag_and_is_audited(): void
    {
        // مستخدم عادي (بلا is_system_admin) لا يحصل على bypass تلقائيًا
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $normal = User::create(['name' => 'n', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $normal->id, 'role' => 'viewer', 'status' => 'active']);
        // is_system_admin ليست fillable عمدًا (منع تصعيد الصلاحيات) → تُضبط عبر forceFill.
        $admin = User::create(['name' => 'sa', 'email' => Str::random(6) . '@ex.com', 'password' => 'Secret123!', 'is_active' => true]);
        $admin->forceFill(['is_system_admin' => true])->save();
        Note::create(['tenant_id' => $t->id, 'body' => 'n1']);
        Note::create(['tenant_id' => Tenant::create(['name'=>'t2','slug'=>Str::random(8),'deployment_mode'=>'saas','status'=>'active'])->id, 'body' => 'n2']);
        TenantContext::reset();

        // مستخدم عادي: مقيّد بمستأجره (لا bypass) → يرى ملاحظة واحدة فقط
        Sanctum::actingAs($normal);
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(1, 'data');

        // system_admin: bypass → يرى الكل + سجل تدقيق
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/notes')->assertOk()->assertJsonCount(2, 'data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.bypass.system_admin', 'user_id' => $admin->id]);
    }
}
