<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Support\FinancialCrypto;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 — بوابة المبدع: يملك ملفه فقط، لا يغيّر الحقول المحمية، IBAN مشفّر، منع IDOR. */
class CreatorPortalTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function creatorUser(string $status = 'active'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        // اشتراك يفعّل بوابة المبدع
        $plan = \App\Domain\Billing\Models\Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = \App\Domain\Billing\Models\PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        \App\Domain\Billing\Models\PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new \App\Domain\Billing\Actions\CreateSubscription)->handle($org, $pv);
        $u = User::create(['name' => 'مبدع', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        $seq = Creator::withTrashed()->count() + 1;
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-' . $seq, 'type' => 'influencer',
            'display_name' => 'نورة', 'status' => $status, 'user_id' => $u->id, 'financial_verification_status' => 'not_provided']);
        TenantContext::reset();
        return [$u, $c, $t];
    }

    public function test_login_requires_creator_profile(): void
    {
        TenantContext::bypass(true);
        $plain = User::create(['name' => 'x', 'email' => 'plain@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        TenantContext::reset();
        $this->post('/creator/login', ['email' => 'plain@ex.com', 'password' => 'secret12'])
            ->assertSessionHasErrors('email'); // ليس مبدعًا
    }

    public function test_creator_can_update_own_allowed_profile_fields(): void
    {
        [$u, $c] = $this->creatorUser();
        $this->actingAs($u)->post('/creator/profile', ['display_name' => 'نورة الجديدة', 'city' => 'الرياض', 'bio' => 'نبذة'])
            ->assertRedirect();
        TenantContext::bypass(true);
        $this->assertEquals('نورة الجديدة', $c->fresh()->display_name);
        TenantContext::reset();
    }

    public function test_creator_cannot_change_protected_fields_via_profile(): void
    {
        [$u, $c] = $this->creatorUser();
        // يحاول حقن حقول محمية
        $this->actingAs($u)->post('/creator/profile', [
            'display_name' => 'x', 'status' => 'blocked', 'financial_verification_status' => 'verified',
            'mowthooq_status' => 'verified', 'tenant_id' => 999, 'user_id' => 999,
        ])->assertRedirect();
        TenantContext::bypass(true);
        $fresh = $c->fresh();
        $this->assertEquals('active', $fresh->status);                              // لم يتغيّر
        $this->assertEquals('not_provided', $fresh->financial_verification_status); // لم يتغيّر
        $this->assertNotEquals(999, $fresh->tenant_id);                             // tenant_id محمي
        TenantContext::reset();
    }

    public function test_financial_update_encrypts_iban_and_keeps_pending(): void
    {
        [$u, $c] = $this->creatorUser();
        $this->actingAs($u)->post('/creator/financial', ['beneficiary_name' => 'نورة', 'bank_name' => 'الأهلي', 'iban' => 'SA0380000000608010167519'])
            ->assertRedirect();
        TenantContext::bypass(true);
        $fresh = $c->fresh();
        $this->assertEquals('7519', $fresh->iban_last4);
        $this->assertStringNotContainsString('0380000000', (string) $fresh->iban_encrypted); // ليس خامًا
        $this->assertEquals('SA0380000000608010167519', FinancialCrypto::decryptIban($fresh->iban_encrypted)); // يُفكّ صحيحًا
        $this->assertEquals('pending', $fresh->financial_verification_status);       // المبدع لا يعتمد نفسه
        TenantContext::reset();
    }

    public function test_creator_cannot_access_others_via_portal_context(): void
    {
        [$uA, $cA] = $this->creatorUser();
        [$uB, $cB, $tB] = $this->creatorUser();
        // المبدع A يعدّل ملفه؛ يجب ألا يمسّ ملف B (السياق من المبدع نفسه)
        $this->actingAs($uA)->post('/creator/profile', ['display_name' => 'A فقط']);
        TenantContext::bypass(true);
        $this->assertEquals('نورة', $cB->fresh()->display_name); // B لم يتأثّر
        TenantContext::reset();
    }

    public function test_later_modules_show_not_available_not_demo(): void
    {
        [$u] = $this->creatorUser();
        $this->actingAs($u)->get('/creator/opportunities')->assertOk()->assertSee('Not available yet');
    }

    public function test_mowthooq_status_not_self_verifiable(): void
    {
        [$u, $c] = $this->creatorUser();
        $this->actingAs($u)->post('/creator/mowthooq', ['mowthooq_license_number' => 'LIC-1', 'mowthooq_status' => 'verified']);
        TenantContext::bypass(true);
        $this->assertNotEquals('verified', $c->fresh()->mowthooq_status); // بقيت pending
        TenantContext::reset();
    }
}
