<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Brand, Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** علامات العميل React/Inertia — عرض/إنشاء/تعديل/إرسال معزول + بوابة الدور. */
class InertiaClientBrandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Client,2:Tenant} */
    private function member(string $role = 'client_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'شركة', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $client, $t];
    }

    public function test_list_and_manage_flag(): void
    {
        [$u, $client, $t] = $this->member('client_admin');
        TenantContext::set($t->id);
        Brand::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'name' => 'علامة', 'slug' => $t->id . '-' . Str::random(4), 'status' => 'draft']);
        TenantContext::reset();
        $this->actingAs($u)->get('/beta/client/brands')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ClientPortal/Brands/Index')->where('canManage', true)->has('brands', 1));
    }

    public function test_create_brand(): void
    {
        [$u, $client, $t] = $this->member('client_admin');
        $this->actingAs($u)->post('/beta/client/brands', ['name' => 'علامتي'])->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame(1, Brand::where('client_id', $client->id)->where('name', 'علامتي')->count());
        TenantContext::reset();
    }

    public function test_update_and_submit(): void
    {
        [$u, $client, $t] = $this->member('client_admin');
        TenantContext::set($t->id);
        $b = Brand::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'name' => 'علامة', 'slug' => $t->id . '-' . Str::random(4), 'status' => 'draft']);
        TenantContext::reset();
        $this->actingAs($u)->post("/beta/client/brands/{$b->id}/update", ['name' => 'محدّثة'])->assertRedirect();
        $this->actingAs($u)->post("/beta/client/brands/{$b->id}/submit")->assertRedirect();
        TenantContext::set($t->id);
        $fresh = $b->fresh();
        $this->assertSame('محدّثة', $fresh->name);
        $this->assertSame('submitted', $fresh->status);
        TenantContext::reset();
    }

    public function test_viewer_role_cannot_manage(): void
    {
        [$u, $client, $t] = $this->member('client_finance');
        TenantContext::set($t->id);
        $b = Brand::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'name' => 'علامة', 'slug' => $t->id . '-' . Str::random(4), 'status' => 'draft']);
        TenantContext::reset();
        $this->actingAs($u)->get('/beta/client/brands')->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        $this->actingAs($u)->post('/beta/client/brands', ['name' => 'x'])->assertForbidden();
        $this->actingAs($u)->post("/beta/client/brands/{$b->id}/submit")->assertForbidden();
    }

    public function test_idor_safe(): void
    {
        [$u1] = $this->member();
        [, $c2, $t2] = $this->member();
        TenantContext::set($t2->id);
        $bB = Brand::create(['tenant_id' => $t2->id, 'client_id' => $c2->id, 'name' => 'B', 'slug' => 'b-' . $t2->id, 'status' => 'draft']);
        TenantContext::reset();
        $this->actingAs($u1)->get("/beta/client/brands/{$bB->id}")->assertNotFound();
    }
}
