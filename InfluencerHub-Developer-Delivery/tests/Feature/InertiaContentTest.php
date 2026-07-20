<?php

namespace Tests\Feature;

use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** المحتوى React/Inertia — طابور موافقات + تفاصيل بإجراءات مراجعة (send-to-client/request-changes) + عزل. */
class InertiaContentTest extends TestCase
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
        $cr = \App\Domain\Creators\Models\Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active']);
        foreach ($statuses as $i => $st) {
            ContentItem::create(['tenant_id' => $t->id, 'content_number' => 'CN-' . $t->id . '-' . $i, 'creator_id' => $cr->id,
                'client_id' => $cl->id, 'title' => 'محتوى' . $i, 'type' => 'post', 'platform' => 'tiktok', 'status' => $st, 'version' => 1]);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    private function item(int $tenantId): ContentItem
    {
        TenantContext::bypass(true);
        $c = ContentItem::where('tenant_id', $tenantId)->first();
        TenantContext::reset();
        return $c;
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/content')->assertRedirect('/login');
    }

    public function test_renders_queue_with_summary(): void
    {
        [, , $u] = $this->agency(['agency_review', 'client_review', 'published']);
        $this->actingAs($u)->get('/beta/content')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Content/Index')->where('summary.total', 3)
                ->where('summary.agency_review', 1)->has('items.data', 3));
    }

    public function test_detail_shows_actions(): void
    {
        [$t, , $u] = $this->agency(['agency_review']);
        $c = $this->item($t->id);
        $this->actingAs($u)->get("/beta/content/{$c->id}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Content/Show')->where('content.id', $c->id)
                ->where('canReview', true)->has('actions')->has('approvals'));
    }

    public function test_send_to_client_via_workflow(): void
    {
        [$t, , $u] = $this->agency(['agency_review']);
        $c = $this->item($t->id);
        $this->actingAs($u)->post("/beta/content/{$c->id}/send-to-client")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('client_review', $c->fresh()->status);
        TenantContext::reset();
    }

    public function test_request_changes_requires_reason(): void
    {
        [$t, , $u] = $this->agency(['agency_review']);
        $c = $this->item($t->id);
        $this->actingAs($u)->post("/beta/content/{$c->id}/request-changes", [])->assertSessionHasErrors('reason');
        $this->actingAs($u)->post("/beta/content/{$c->id}/request-changes", ['reason' => 'حسّن الإضاءة'])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('changes_requested', $c->fresh()->status);
        TenantContext::reset();
    }

    public function test_viewer_cannot_review(): void
    {
        [$t, , $u] = $this->agency(['agency_review'], 'viewer');
        $c = $this->item($t->id);
        $this->actingAs($u)->get("/beta/content/{$c->id}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canReview', false)->where('actions', []));
        $this->actingAs($u)->post("/beta/content/{$c->id}/send-to-client")->assertForbidden();
    }

    public function test_detail_is_idor_safe(): void
    {
        [$t1] = $this->agency(['agency_review']);
        $other = $this->item($t1->id);
        [, , $u2] = $this->agency([]);
        $this->actingAs($u2)->get("/beta/content/{$other->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade — نفس المكوّنات والإجراءات ===== */

    public function test_app_content_renders_react_queue(): void
    {
        [, , $u] = $this->agency(['agency_review', 'client_review', 'published']);
        $this->actingAs($u)->get('/app/content')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Content/Index')
                ->where('base', '/app')
                ->has('items.data', 3));
    }

    public function test_app_content_detail_renders_react(): void
    {
        [$t, , $u] = $this->agency(['agency_review']);
        $c = $this->item($t->id);
        $this->actingAs($u)->get("/app/content/{$c->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Content/Show')->where('base', '/app'));
    }

    /** فلتر `?status=` القديم من Blade ما زال يعمل بعد التحويل. */
    public function test_app_content_honours_legacy_status_filter(): void
    {
        [, , $u] = $this->agency(['agency_review', 'published', 'published']);
        $this->actingAs($u)->get('/app/content?status=published')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('items.data', 2)->where('filters.seg', 'published'));
    }

    /** الإجراء يمرّ عبر مسار /app الجديد ويغيّر الحالة فعليًا (لا واجهة فقط). */
    public function test_app_content_transition_changes_status(): void
    {
        [$t, , $u] = $this->agency(['agency_review']);
        $c = $this->item($t->id);
        $this->actingAs($u)->post("/app/content/{$c->id}/send-to-client", ['note' => 'جاهز'])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('client_review', ContentItem::find($c->id)->status);
        TenantContext::reset();
    }

    public function test_app_content_transition_rejects_unknown_action(): void
    {
        [$t, , $u] = $this->agency(['agency_review']);
        $c = $this->item($t->id);
        $this->actingAs($u)->post("/app/content/{$c->id}/delete-everything")->assertNotFound();
    }

    public function test_app_content_guest_redirected(): void
    {
        $this->get('/app/content')->assertRedirect('/login');
    }
}
