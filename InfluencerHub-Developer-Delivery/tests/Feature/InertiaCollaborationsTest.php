<?php

namespace Tests\Feature;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** التعاونات React/Inertia — قائمة + تفاصيل بإجراءات (approve/request-revision) + عزل + بوابة manage. */
class InertiaCollaborationsTest extends TestCase
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
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active']);
        foreach ($statuses as $i => $st) {
            Collaboration::create(['tenant_id' => $t->id, 'collaboration_number' => 'CO-' . $t->id . '-' . $i, 'creator_id' => $cr->id,
                'title' => 'تعاون' . $i, 'fee_minor' => 3000000, 'currency' => 'SAR', 'status' => $st]);
        }
        TenantContext::reset();
        return [$t, $org, $u];
    }

    private function collab(int $tenantId): Collaboration
    {
        TenantContext::bypass(true);
        $c = Collaboration::where('tenant_id', $tenantId)->first();
        TenantContext::reset();
        return $c;
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/collaborations')->assertRedirect('/login');
    }

    public function test_renders_list_with_summary(): void
    {
        [, , $u] = $this->agency(['submitted', 'approved', 'completed']);
        $this->actingAs($u)->get('/beta/collaborations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Collaborations/Index')->where('summary.total', 3)
                ->where('summary.submitted', 1)->has('collaborations.data', 3));
    }

    public function test_approve_action_via_workflow(): void
    {
        [$t, , $u] = $this->agency(['submitted']);
        $c = $this->collab($t->id);
        $this->actingAs($u)->post("/beta/collaborations/{$c->id}/approve")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('approved', $c->fresh()->status);
        TenantContext::reset();
    }

    public function test_request_revision_requires_reason(): void
    {
        [$t, , $u] = $this->agency(['submitted']);
        $c = $this->collab($t->id);
        $this->actingAs($u)->post("/beta/collaborations/{$c->id}/request-revision", [])->assertSessionHasErrors('reason');
    }

    public function test_viewer_cannot_manage(): void
    {
        [$t, , $u] = $this->agency(['submitted'], 'viewer');
        $c = $this->collab($t->id);
        $this->actingAs($u)->get("/beta/collaborations/{$c->id}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', false)->where('actions', []));
        $this->actingAs($u)->post("/beta/collaborations/{$c->id}/approve")->assertForbidden();
    }

    public function test_detail_is_idor_safe(): void
    {
        [$t1] = $this->agency(['approved']);
        $other = $this->collab($t1->id);
        [, , $u2] = $this->agency([]);
        $this->actingAs($u2)->get("/beta/collaborations/{$other->id}")->assertNotFound();
    }

    /* ===== /app بعد التحويل من Blade ===== */

    private function creatorId(int $tenantId): int
    {
        TenantContext::bypass(true);
        $id = Creator::where('tenant_id', $tenantId)->first()->id;
        TenantContext::reset();

        return $id;
    }

    public function test_app_collaborations_renders_react_list(): void
    {
        [, , $u] = $this->agency(['offered', 'accepted']);
        $this->actingAs($u)->get('/app/collaborations')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Collaborations/Index')->where('base', '/app')
                ->has('collaborations.data', 2)->where('canCreate', true));
    }

    public function test_app_collaboration_detail_renders_react(): void
    {
        [$t, , $u] = $this->agency(['offered']);
        $c = $this->collab($t->id);
        $this->actingAs($u)->get("/app/collaborations/{$c->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Collaborations/Show')->where('base', '/app'));
    }

    public function test_app_store_creates_offer_and_redirects_to_it(): void
    {
        [$t, , $u] = $this->agency();
        $res = $this->actingAs($u)->post('/app/collaborations', [
            'creator_id' => $this->creatorId($t->id), 'title' => 'ريلز إطلاق', 'fee_minor' => 250000,
        ]);
        TenantContext::bypass(true);
        $c = Collaboration::where('tenant_id', $t->id)->first();
        TenantContext::reset();
        $this->assertNotNull($c, 'لم يُنشأ التعاون');
        $this->assertSame(250000, (int) $c->fee_minor);
        $res->assertRedirect("/app/collaborations/{$c->id}");
    }

    /** الأجر اختياري — عرض بلا أجر متفَق عليه يُنشأ بصفر لا بخطأ. */
    public function test_app_store_defaults_missing_fee_to_zero(): void
    {
        [$t, , $u] = $this->agency();
        $this->actingAs($u)->post('/app/collaborations', [
            'creator_id' => $this->creatorId($t->id), 'title' => 'بلا أجر',
        ])->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame(0, (int) Collaboration::where('tenant_id', $t->id)->first()->fee_minor);
        TenantContext::reset();
    }

    public function test_app_store_rejects_creator_from_another_tenant(): void
    {
        [, , $u] = $this->agency();
        [$other, , ] = $this->agency();
        $this->actingAs($u)->post('/app/collaborations', [
            'creator_id' => $this->creatorId($other->id), 'title' => 'تسريب',
        ])->assertNotFound();
    }

    public function test_app_store_requires_title(): void
    {
        [$t, , $u] = $this->agency();
        $this->actingAs($u)->post('/app/collaborations', ['creator_id' => $this->creatorId($t->id)])
            ->assertSessionHasErrors('title');
    }

    public function test_app_store_denied_for_viewer(): void
    {
        [$t, , $u] = $this->agency([], 'viewer');
        $this->actingAs($u)->post('/app/collaborations', [
            'creator_id' => $this->creatorId($t->id), 'title' => 'ممنوع',
        ])->assertForbidden();
    }

    public function test_app_collaborations_guest_redirected(): void
    {
        $this->get('/app/collaborations')->assertRedirect('/login');
    }
}
