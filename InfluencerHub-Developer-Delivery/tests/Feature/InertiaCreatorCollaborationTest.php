<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion};
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** تعاونات المبدع React/Inertia — عرض + قبول/تسليم معزول عبر CollaborationWorkflowService. */
class InertiaCreatorCollaborationTest extends TestCase
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

    private function collab(Tenant $t, Creator $c, string $status): Collaboration
    {
        TenantContext::set($t->id);
        $col = Collaboration::create(['tenant_id' => $t->id, 'collaboration_number' => 'CO-' . $t->id . '-' . Str::random(3),
            'creator_id' => $c->id, 'title' => 'تعاون', 'fee_minor' => 200000, 'currency' => 'SAR', 'status' => $status,
            'offered_at' => now()]);
        TenantContext::reset();
        return $col;
    }

    public function test_list_renders(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $col = $this->collab($t, $c, 'offered');
        $this->actingAs($u)->get('/beta/creator/collaborations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreatorPortal/Collaborations/Index')
                ->where('actionable', 1)
                ->where('items.data.0.id', $col->id));
    }

    public function test_detail_exposes_actions_for_offered(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $col = $this->collab($t, $c, 'offered');
        $this->actingAs($u)->get("/beta/creator/collaborations/{$col->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreatorPortal/Collaborations/Show')
                ->where('collab.id', $col->id)
                ->where('actions.0.key', 'accept'));
    }

    public function test_accept_transitions(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $col = $this->collab($t, $c, 'offered');
        $this->actingAs($u)->post("/beta/creator/collaborations/{$col->id}/accept")->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame('accepted', $col->fresh()->status);
        TenantContext::reset();
    }

    public function test_invalid_action_404(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        $col = $this->collab($t, $c, 'offered');
        $this->actingAs($u)->post("/beta/creator/collaborations/{$col->id}/bogus")->assertNotFound();
    }

    public function test_idor_safe(): void
    {
        [$u1] = $this->creatorUser();
        [, $c2, $t2] = $this->creatorUser();
        $colB = $this->collab($t2, $c2, 'offered');
        $this->actingAs($u1)->get("/beta/creator/collaborations/{$colB->id}")->assertNotFound();
    }
}
