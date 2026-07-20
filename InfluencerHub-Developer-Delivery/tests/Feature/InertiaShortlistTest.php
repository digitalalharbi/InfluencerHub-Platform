<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\{Campaign, CampaignShortlist};
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** ترشيح المؤثرين React/Inertia — عرض + إضافة/إرسال + عزل + بوابة الدور. */
class InertiaShortlistTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Tenant,1:User,2:Campaign,3:Creator} */
    private function world(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $cl->id,
            'name' => 'حملة الصيف', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR']);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer',
            'display_name' => 'مبدع مطابق', 'handle' => '@match', 'primary_platform' => 'instagram',
            'followers_count' => 250000, 'status' => 'active', 'rate_per_post_minor' => 300000, 'mowthooq_status' => 'verified']);
        TenantContext::reset();
        return [$t, $u, $cm, $cr];
    }

    public function test_guest_redirected(): void
    {
        [, , $cm] = $this->world();
        $this->get("/beta/campaigns/{$cm->id}/shortlist")->assertRedirect('/login');
    }

    public function test_renders_workspace_with_candidate(): void
    {
        [, $u, $cm, $cr] = $this->world();
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shortlist/Index')
                ->where('campaign.id', $cm->id)
                ->where('version.status', 'draft')
                ->where('canEdit', true)
                ->has('items', 0)
                ->where('candidates.0.id', $cr->id)
                ->where('candidates.0.verified', true)
                ->where('candidates.0.score', fn ($s) => $s > 0)
                // تاريخ الإصدارات
                ->has('versions', 1)
                ->where('versions.0.isCurrent', true));
    }

    public function test_add_and_submit_flow(): void
    {
        [, $u, $cm, $cr] = $this->world();
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/shortlist/add", ['creator_id' => $cr->id, 'backup' => false])
            ->assertRedirect();
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")
            ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.creator', 'مبدع مطابق'));

        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/shortlist/submit")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('submitted', CampaignShortlist::where('campaign_id', $cm->id)->first()->currentVersion()->status);
        TenantContext::reset();
    }

    public function test_idor_safe_across_tenants(): void
    {
        [, , $cmA] = $this->world();
        [, $uB] = $this->world();
        $this->actingAs($uB)->get("/beta/campaigns/{$cmA->id}/shortlist")->assertNotFound();
    }

    public function test_viewer_can_view_but_not_add(): void
    {
        [, $u, $cm, $cr] = $this->world('viewer');
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canEdit', false));
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/shortlist/add", ['creator_id' => $cr->id])->assertForbidden();
    }
}
