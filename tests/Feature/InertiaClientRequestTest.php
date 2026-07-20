<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** طلبات العميل React/Inertia — إنشاء/عرض/تعليق، معزول على العميل النشِط. */
class InertiaClientRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Client,2:Tenant} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'شركة', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $client, $t];
    }

    public function test_list_renders_with_options(): void
    {
        [$u] = $this->world();
        $this->actingAs($u)->get('/beta/client/requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Requests/Index')
                ->where('open', 0)
                ->has('types')->has('priorities'));
    }

    public function test_create_request(): void
    {
        [$u, $client, $t] = $this->world();
        $this->actingAs($u)->post('/beta/client/requests', [
            'type' => 'content', 'title' => 'طلب محتوى', 'priority' => 'high', 'description' => 'تفاصيل',
        ])->assertRedirect('/beta/client/requests');
        TenantContext::set($t->id);
        $sr = ServiceRequest::where('requester_client_id', $client->id)->first();
        $this->assertNotNull($sr);
        $this->assertSame('طلب محتوى', $sr->title);
        $this->assertSame('submitted', $sr->status);
        TenantContext::reset();
    }

    public function test_create_validates_type(): void
    {
        [$u] = $this->world();
        $this->actingAs($u)->post('/beta/client/requests', ['type' => 'bogus', 'title' => 'x', 'priority' => 'normal'])
            ->assertSessionHasErrors('type');
    }

    public function test_detail_and_comment(): void
    {
        [$u, $client, $t] = $this->world();
        TenantContext::set($t->id);
        $sr = ServiceRequest::create(['tenant_id' => $t->id, 'request_number' => 'SR-' . $t->id, 'requester_type' => 'client',
            'requester_client_id' => $client->id, 'client_id' => $client->id, 'type' => 'content', 'title' => 'طلب',
            'priority' => 'normal', 'status' => 'in_progress', 'requested_by' => $u->id]);
        TenantContext::reset();

        $this->actingAs($u)->get("/beta/client/requests/{$sr->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('ClientPortal/Requests/Show')->where('request.id', $sr->id)->where('request.isOpen', true));

        $this->actingAs($u)->post("/beta/client/requests/{$sr->id}/comment", ['body' => 'أي جديد؟'])->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame(1, $sr->comments()->where('is_internal', false)->count());
        TenantContext::reset();
    }

    public function test_idor_safe(): void
    {
        [$u1] = $this->world();
        [$u2, $c2, $t2] = $this->world();
        TenantContext::set($t2->id);
        $srB = ServiceRequest::create(['tenant_id' => $t2->id, 'request_number' => 'SR-B', 'requester_type' => 'client',
            'requester_client_id' => $c2->id, 'client_id' => $c2->id, 'type' => 'other', 'title' => 'B',
            'priority' => 'normal', 'status' => 'submitted', 'requested_by' => $u2->id]);
        TenantContext::reset();
        $this->actingAs($u1)->get("/beta/client/requests/{$srB->id}")->assertNotFound();
    }
}
