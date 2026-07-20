<?php

namespace Tests\Feature;

use App\Domain\Contracts\Models\Contract;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** عقود العميل React/Inertia — عرض معزول + توقيع client_admin عبر ContractWorkflowService. */
class InertiaClientContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Client,2:Tenant,3:Contract} */
    private function world(string $role = 'client_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'شركة', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'accepted_at' => now()]);
        $ct = Contract::create(['tenant_id' => $t->id, 'contract_number' => 'CT-' . $t->id, 'party_type' => 'client',
            'client_id' => $client->id, 'title' => 'عقد رعاية', 'terms' => 'بنود العقد', 'value_minor' => 500000,
            'currency' => 'SAR', 'status' => 'sent', 'sent_at' => now()]);
        TenantContext::reset();
        return [$u, $client, $t, $ct];
    }

    public function test_list_shows_awaiting(): void
    {
        [$u, , , $ct] = $this->world();
        $this->actingAs($u)->get('/beta/client/contracts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Contracts/Index')
                ->where('awaiting', 1)
                ->where('items.data.0.id', $ct->id));
    }

    public function test_detail_pending_signable_for_admin(): void
    {
        [$u, , , $ct] = $this->world('client_admin');
        $this->actingAs($u)->get("/beta/client/contracts/{$ct->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Contracts/Show')
                ->where('isPending', true)->where('canSign', true));
    }

    public function test_admin_can_sign(): void
    {
        [$u, , $t, $ct] = $this->world('client_admin');
        $this->actingAs($u)->post("/beta/client/contracts/{$ct->id}/sign", ['signer_name' => 'محمد العلي', 'agree' => true])
            ->assertRedirect();
        TenantContext::set($t->id);
        $fresh = $ct->fresh();
        $this->assertSame('signed', $fresh->status);
        $this->assertSame('محمد العلي', $fresh->signed_by_name);
        TenantContext::reset();
    }

    public function test_sign_requires_agreement(): void
    {
        [$u, , , $ct] = $this->world('client_admin');
        $this->actingAs($u)->post("/beta/client/contracts/{$ct->id}/sign", ['signer_name' => 'محمد'])
            ->assertSessionHasErrors('agree');
    }

    public function test_non_admin_cannot_sign(): void
    {
        [$u, , , $ct] = $this->world('client_finance');
        $this->actingAs($u)->get("/beta/client/contracts/{$ct->id}")
            ->assertInertia(fn (Assert $page) => $page->where('canSign', false));
        $this->actingAs($u)->post("/beta/client/contracts/{$ct->id}/sign", ['signer_name' => 'x', 'agree' => true])->assertForbidden();
    }

    public function test_idor_safe(): void
    {
        [$u1] = $this->world();
        [, , , $ctB] = $this->world();
        $this->actingAs($u1)->get("/beta/client/contracts/{$ctB->id}")->assertNotFound();
    }
}
