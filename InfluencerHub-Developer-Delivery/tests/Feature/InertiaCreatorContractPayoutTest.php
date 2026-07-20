<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion};
use App\Domain\Contracts\Models\Contract;
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\Payout;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** عقود ومستحقات المبدع React/Inertia — توقيع عقد + عرض مستحقات، معزول على المبدع النشِط. */
class InertiaCreatorContractPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Creator,2:Tenant} */
    private function creatorUser(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new CreateSubscription)->handle($org, $pv);
        $u = User::create(['name' => 'مبدع', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        $seq = Creator::withTrashed()->count() + 1;
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-' . $seq, 'type' => 'influencer',
            'display_name' => 'نورة', 'status' => 'active', 'user_id' => $u->id, 'financial_verification_status' => 'not_provided']);
        TenantContext::reset();
        return [$u, $c, $t];
    }

    public function test_creator_can_sign_own_contract(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        TenantContext::set($t->id);
        $ct = Contract::create(['tenant_id' => $t->id, 'contract_number' => 'CT-' . $t->id, 'party_type' => 'creator',
            'creator_id' => $c->id, 'title' => 'عقد تعاون', 'terms' => 'بنود', 'value_minor' => 400000, 'currency' => 'SAR',
            'status' => 'sent', 'sent_at' => now()]);
        TenantContext::reset();

        $this->actingAs($u)->get("/beta/creator/contracts/{$ct->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('CreatorPortal/Contracts/Show')->where('isPending', true));

        $this->actingAs($u)->post("/beta/creator/contracts/{$ct->id}/sign", ['signer_name' => 'نورة', 'agree' => true])->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame('signed', $ct->fresh()->status);
        TenantContext::reset();
    }

    public function test_contract_idor_safe(): void
    {
        [$u1] = $this->creatorUser();
        [, $c2, $t2] = $this->creatorUser();
        TenantContext::set($t2->id);
        $ctB = Contract::create(['tenant_id' => $t2->id, 'contract_number' => 'CT-B', 'party_type' => 'creator',
            'creator_id' => $c2->id, 'title' => 'B', 'value_minor' => 1, 'currency' => 'SAR', 'status' => 'sent', 'sent_at' => now()]);
        TenantContext::reset();
        $this->actingAs($u1)->get("/beta/creator/contracts/{$ctB->id}")->assertNotFound();
    }

    public function test_payouts_show_totals(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        TenantContext::set($t->id);
        Payout::create(['tenant_id' => $t->id, 'payout_number' => 'PO-1', 'creator_id' => $c->id, 'description' => 'دفعة',
            'amount_minor' => 300000, 'currency' => 'SAR', 'status' => 'paid']);
        Payout::create(['tenant_id' => $t->id, 'payout_number' => 'PO-2', 'creator_id' => $c->id, 'description' => 'دفعة2',
            'amount_minor' => 200000, 'currency' => 'SAR', 'status' => 'pending']);
        TenantContext::reset();

        $this->actingAs($u)->get('/beta/creator/payouts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreatorPortal/Payouts/Index')
                ->where('paidMinor', 300000)
                ->where('openMinor', 200000)
                ->has('items.data', 2));
    }
}
