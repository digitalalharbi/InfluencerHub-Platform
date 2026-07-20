<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\Payout;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** المستحقات React/Inertia — قائمة + تفاصيل بإجراءات صرف صادقة + عزل + بوابة manage. */
class InertiaPayoutsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(array $statuses = [], string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active', 'iban_last4' => '6789']);
        foreach ($statuses as $i => $st) {
            Payout::create(['tenant_id' => $t->id, 'payout_number' => 'PY-' . $t->id . '-' . $i, 'creator_id' => $cr->id,
                'amount_minor' => 5000000, 'currency' => 'SAR', 'status' => $st, 'iban_last4' => '6789']);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    private function payout(int $tenantId): Payout
    {
        TenantContext::bypass(true);
        $p = Payout::where('tenant_id', $tenantId)->first();
        TenantContext::reset();
        return $p;
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/payouts')->assertRedirect('/login');
    }

    public function test_renders_list_with_financial_summary(): void
    {
        [, , $u] = $this->agency(['pending', 'approved', 'paid']);
        $this->actingAs($u)->get('/beta/payouts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payouts/Index')->where('summary.total', 3)
                ->where('summary.paidMinor', 5000000)->has('payouts.data', 3));
    }

    public function test_approve_action_via_workflow(): void
    {
        [$t, , $u] = $this->agency(['pending']);
        $p = $this->payout($t->id);
        $this->actingAs($u)->post("/beta/payouts/{$p->id}/approve")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('approved', $p->fresh()->status);
        TenantContext::reset();
    }

    public function test_mark_paid_requires_reference(): void
    {
        [$t, , $u] = $this->agency(['waiting_for_provider']);
        $p = $this->payout($t->id);
        $this->actingAs($u)->post("/beta/payouts/{$p->id}/mark-paid", [])->assertSessionHasErrors('payment_reference');
        $this->actingAs($u)->post("/beta/payouts/{$p->id}/mark-paid", ['payment_reference' => 'TRX-123'])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('paid', $p->fresh()->status);
        TenantContext::reset();
    }

    public function test_provider_note_shown_when_waiting(): void
    {
        [$t, , $u] = $this->agency(['waiting_for_provider']);
        $p = $this->payout($t->id);
        $this->actingAs($u)->get("/beta/payouts/{$p->id}")
            ->assertInertia(fn (Assert $page) => $page->where('providerNote', true));
    }

    public function test_viewer_cannot_manage(): void
    {
        [$t, , $u] = $this->agency(['pending'], 'viewer');
        $p = $this->payout($t->id);
        $this->actingAs($u)->get("/beta/payouts/{$p->id}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('actions', []));
        $this->actingAs($u)->post("/beta/payouts/{$p->id}/approve")->assertForbidden();
    }

    public function test_detail_is_idor_safe(): void
    {
        [$t1] = $this->agency(['pending']);
        $other = $this->payout($t1->id);
        [, , $u2] = $this->agency([]);
        $this->actingAs($u2)->get("/beta/payouts/{$other->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade ===== */

    private function creatorId(int $tenantId): int
    {
        TenantContext::bypass(true);
        $id = Creator::where('tenant_id', $tenantId)->first()->id;
        TenantContext::reset();

        return $id;
    }

    public function test_app_payouts_renders_react_list(): void
    {
        [, , $u] = $this->agency(['pending', 'approved']);
        $this->actingAs($u)->get('/app/payouts')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payouts/Index')->where('base', '/app')->has('payouts.data', 2)->where('canCreate', true));
    }

    public function test_app_payout_detail_renders_react(): void
    {
        [$t, , $u] = $this->agency(['pending']);
        $p = $this->payout($t->id);
        $this->actingAs($u)->get("/app/payouts/{$p->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Payouts/Show')->where('base', '/app'));
    }

    public function test_app_store_creates_payout_and_redirects_to_it(): void
    {
        [$t, , $u] = $this->agency();
        $res = $this->actingAs($u)->post('/app/payouts', [
            'creator_id' => $this->creatorId($t->id), 'amount_minor' => 1350000, 'description' => 'أجر تعاون',
        ]);
        TenantContext::bypass(true);
        $p = Payout::where('tenant_id', $t->id)->first();
        TenantContext::reset();
        $this->assertNotNull($p, 'لم يُنشأ المستحق');
        $this->assertSame(1350000, (int) $p->amount_minor);
        $res->assertRedirect("/app/payouts/{$p->id}");
    }

    /** لا يجوز إنشاء مستحق لمبدع خارج المستأجر. */
    public function test_app_store_rejects_creator_from_another_tenant(): void
    {
        [, , $u] = $this->agency();
        [$other, , ] = $this->agency();
        $this->actingAs($u)->post('/app/payouts', [
            'creator_id' => $this->creatorId($other->id), 'amount_minor' => 1000,
        ])->assertNotFound();
    }

    public function test_app_store_rejects_non_positive_amount(): void
    {
        [$t, , $u] = $this->agency();
        $this->actingAs($u)->post('/app/payouts', ['creator_id' => $this->creatorId($t->id), 'amount_minor' => 0])
            ->assertSessionHasErrors('amount_minor');
    }

    public function test_app_store_denied_without_create_ability(): void
    {
        [$t, , $u] = $this->agency([], 'viewer');
        $this->actingAs($u)->post('/app/payouts', ['creator_id' => $this->creatorId($t->id), 'amount_minor' => 1000])
            ->assertForbidden();
    }

    /** دور العرض لا يرى نموذج الإنشاء ولا خيارات المبدعين. */
    public function test_viewer_gets_no_create_form_data(): void
    {
        [, , $u] = $this->agency(['pending'], 'viewer');
        $this->actingAs($u)->get('/app/payouts')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', false)->has('creatorOptions', 0));
    }

    public function test_app_payouts_guest_redirected(): void
    {
        $this->get('/app/payouts')->assertRedirect('/login');
    }
}
