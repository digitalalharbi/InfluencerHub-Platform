<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** موافقات المحتوى — بوابة العميل React/Inertia: عرض + اعتماد/طلب تعديل + بوابة الدور + عزل. */
class InertiaClientContentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Client,2:Tenant,3:ContentItem} */
    private function world(string $role = 'client_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'شركة', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active', 'accepted_at' => now()]);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $client->id,
            'name' => 'حملة', 'status' => 'active', 'budget_minor' => 100000, 'currency' => 'SAR']);
        $item = ContentItem::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'campaign_id' => $cm->id,
            'content_number' => 'CT-' . $t->id, 'type' => 'post', 'title' => 'منشور إطلاق', 'caption' => 'نص',
            'status' => 'client_review', 'version' => 1]);
        TenantContext::reset();
        return [$u, $client, $t, $item];
    }

    public function test_list_shows_awaiting(): void
    {
        [$u, , , $item] = $this->world();
        $this->actingAs($u)->get('/beta/client/content')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Content/Index')
                ->where('awaiting', 1)
                ->where('canReview', true)
                ->where('items.data.0.id', $item->id));
    }

    public function test_detail_renders(): void
    {
        [$u, , , $item] = $this->world();
        $this->actingAs($u)->get("/beta/client/content/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Content/Show')
                ->where('item.id', $item->id)
                ->where('isPending', true)
                ->where('canReview', true));
    }

    public function test_admin_can_approve(): void
    {
        [$u, , $t, $item] = $this->world('client_admin');
        $this->actingAs($u)->post("/beta/client/content/{$item->id}/approve")->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame('approved', $item->fresh()->status);
        TenantContext::reset();
    }

    public function test_request_changes_requires_reason(): void
    {
        [$u, , , $item] = $this->world('client_admin');
        $this->actingAs($u)->post("/beta/client/content/{$item->id}/request-changes", [])->assertSessionHasErrors('reason');
        $this->actingAs($u)->post("/beta/client/content/{$item->id}/request-changes", ['reason' => 'عدّل الألوان'])->assertRedirect();
    }

    public function test_unprivileged_role_cannot_approve(): void
    {
        [$u, , , $item] = $this->world('client_finance');
        $this->actingAs($u)->get("/beta/client/content/{$item->id}")
            ->assertInertia(fn (Assert $page) => $page->where('canReview', false));
        $this->actingAs($u)->post("/beta/client/content/{$item->id}/approve")->assertForbidden();
    }

    public function test_isolated_across_clients(): void
    {
        [$u1] = $this->world();
        [, , , $itemB] = $this->world();
        $this->actingAs($u1)->get("/beta/client/content/{$itemB->id}")->assertNotFound();
    }
}
