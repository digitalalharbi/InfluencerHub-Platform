<?php

namespace Tests\Feature;

use App\Domain\Contracts\Models\Contract;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** العقود React/Inertia — قائمة + تفاصيل بإجراءات (send/terminate) + عزل + بوابة manage. */
class InertiaContractsTest extends TestCase
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
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        foreach ($statuses as $i => $st) {
            Contract::create(['tenant_id' => $t->id, 'contract_number' => 'CT-' . $t->id . '-' . $i, 'party_type' => 'client',
                'client_id' => $cl->id, 'title' => 'عقد' . $i, 'value_minor' => 5000000, 'currency' => 'SAR', 'status' => $st]);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    private function contract(int $tenantId): Contract
    {
        TenantContext::bypass(true);
        $c = Contract::where('tenant_id', $tenantId)->first();
        TenantContext::reset();
        return $c;
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/contracts')->assertRedirect('/login');
    }

    public function test_renders_list_with_summary(): void
    {
        [, , $u] = $this->agency(['active', 'sent', 'draft']);
        $this->actingAs($u)->get('/beta/contracts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Index')->where('summary.total', 3)
                ->where('summary.active', 1)->where('summary.sent', 1)->has('contracts.data', 3));
    }

    public function test_send_action_via_workflow(): void
    {
        [$t, , $u] = $this->agency(['draft']);
        $c = $this->contract($t->id);
        $this->actingAs($u)->post("/beta/contracts/{$c->id}/send")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('sent', $c->fresh()->status);
        TenantContext::reset();
    }

    public function test_terminate_requires_reason(): void
    {
        [$t, , $u] = $this->agency(['active']);
        $c = $this->contract($t->id);
        $this->actingAs($u)->post("/beta/contracts/{$c->id}/terminate", [])->assertSessionHasErrors('reason');
        $this->actingAs($u)->post("/beta/contracts/{$c->id}/terminate", ['reason' => 'إخلال بالبنود'])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('terminated', $c->fresh()->status);
        TenantContext::reset();
    }

    public function test_viewer_cannot_manage(): void
    {
        [$t, , $u] = $this->agency(['draft'], 'viewer');
        $c = $this->contract($t->id);
        $this->actingAs($u)->get("/beta/contracts/{$c->id}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('actions', []));
        $this->actingAs($u)->post("/beta/contracts/{$c->id}/send")->assertForbidden();
    }

    public function test_detail_is_idor_safe(): void
    {
        [$t1] = $this->agency(['active']);
        $other = $this->contract($t1->id);
        [, , $u2] = $this->agency([]);
        $this->actingAs($u2)->get("/beta/contracts/{$other->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade ===== */

    private function clientId(int $tenantId): int
    {
        TenantContext::bypass(true);
        $id = Client::where('tenant_id', $tenantId)->first()->id;
        TenantContext::reset();

        return $id;
    }

    public function test_app_contracts_renders_react_list(): void
    {
        [, , $u] = $this->agency(['draft', 'active']);
        $this->actingAs($u)->get('/app/contracts')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Index')->where('base', '/app')
                ->has('contracts.data', 2)->where('canCreate', true));
    }

    public function test_app_contract_detail_renders_react(): void
    {
        [$t, , $u] = $this->agency(['draft']);
        $c = $this->contract($t->id);
        $this->actingAs($u)->get("/app/contracts/{$c->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contracts/Show')->where('base', '/app'));
    }

    public function test_app_store_creates_draft_contract(): void
    {
        [$t, , $u] = $this->agency();
        $res = $this->actingAs($u)->post('/app/contracts', [
            'party_type' => 'client', 'client_id' => $this->clientId($t->id),
            'title' => 'اتفاقية إطارية', 'value_minor' => 900000,
        ]);
        TenantContext::bypass(true);
        $c = Contract::where('tenant_id', $t->id)->first();
        TenantContext::reset();
        $this->assertNotNull($c, 'لم يُنشأ العقد');
        $this->assertSame('draft', $c->status);
        $res->assertRedirect("/app/contracts/{$c->id}");
    }

    public function test_app_store_rejects_party_from_another_tenant(): void
    {
        [, , $u] = $this->agency();
        [$other, , ] = $this->agency();
        $this->actingAs($u)->post('/app/contracts', [
            'party_type' => 'client', 'client_id' => $this->clientId($other->id), 'title' => 'تسريب',
        ])->assertNotFound();
    }

    public function test_app_store_rejects_end_before_start(): void
    {
        [$t, , $u] = $this->agency();
        $this->actingAs($u)->post('/app/contracts', [
            'party_type' => 'client', 'client_id' => $this->clientId($t->id), 'title' => 'مدة مقلوبة',
            'start_date' => '2026-05-01', 'end_date' => '2026-04-01',
        ])->assertSessionHasErrors('end_date');
    }

    public function test_app_update_saves_draft_changes(): void
    {
        [$t, , $u] = $this->agency(['draft']);
        $c = $this->contract($t->id);
        $this->actingAs($u)->post("/app/contracts/{$c->id}", ['title' => 'عنوان محدَّث', 'value_minor' => 111100])
            ->assertRedirect();
        TenantContext::bypass(true);
        $fresh = Contract::find($c->id);
        TenantContext::reset();
        $this->assertSame('عنوان محدَّث', $fresh->title);
        $this->assertSame(111100, (int) $fresh->value_minor);
    }

    /** بعد مغادرة المسودة يرفض سير العمل التعديل — لا يُحفظ شيء. */
    public function test_app_update_rejected_after_draft(): void
    {
        [$t, , $u] = $this->agency(['active']);
        $c = $this->contract($t->id);
        $this->actingAs($u)->post("/app/contracts/{$c->id}", ['title' => 'محاولة تعديل'])
            ->assertSessionHasErrors('wf');
        TenantContext::bypass(true);
        $this->assertNotSame('محاولة تعديل', Contract::find($c->id)->title);
        TenantContext::reset();
    }

    public function test_app_update_denied_for_viewer(): void
    {
        [$t, , $u] = $this->agency(['draft'], 'viewer');
        $c = $this->contract($t->id);
        $this->actingAs($u)->post("/app/contracts/{$c->id}", ['title' => 'ممنوع'])->assertForbidden();
    }

    public function test_app_contracts_guest_redirected(): void
    {
        $this->get('/app/contracts')->assertRedirect('/login');
    }
}
