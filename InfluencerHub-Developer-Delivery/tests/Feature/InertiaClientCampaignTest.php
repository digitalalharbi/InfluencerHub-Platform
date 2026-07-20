<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\{Campaign, CampaignShortlist, CampaignShortlistItem, CampaignShortlistVersion};
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** حملات العميل + قرار الترشيح — React/Inertia: عرض معزول + قرار عبر ShortlistService. */
class InertiaClientCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Client,2:Tenant,3:Campaign} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'شركة', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $client->id,
            'name' => 'حملة', 'status' => 'active', 'budget_minor' => 100000, 'currency' => 'SAR']);
        TenantContext::reset();
        return [$u, $client, $t, $cm];
    }

    private function submittedShortlist(Tenant $t, Campaign $cm): CampaignShortlistItem
    {
        TenantContext::bypass(true);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer',
            'display_name' => 'مؤثر مقترح', 'handle' => '@x', 'primary_platform' => 'instagram', 'followers_count' => 120000,
            'status' => 'active', 'rate_per_post_minor' => 50000]);
        $sl = CampaignShortlist::create(['tenant_id' => $t->id, 'campaign_id' => $cm->id, 'current_version' => 1, 'status' => 'submitted']);
        $v = CampaignShortlistVersion::create(['tenant_id' => $t->id, 'shortlist_id' => $sl->id, 'version' => 1, 'status' => 'submitted', 'submitted_at' => now()]);
        $it = CampaignShortlistItem::create(['tenant_id' => $t->id, 'shortlist_version_id' => $v->id, 'creator_id' => $cr->id,
            'is_backup' => false, 'proposed_fee_minor' => 50000, 'match_score' => 70, 'reasons' => ['المنصّة مطابقة'], 'client_decision' => 'pending']);
        TenantContext::reset();
        return $it;
    }

    public function test_list_isolated(): void
    {
        [$u, , , $cm] = $this->world();
        $this->actingAs($u)->get('/beta/client/campaigns')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Campaigns/Index')
                ->where('items.data.0.id', $cm->id));
    }

    public function test_detail_shows_shortlist_pending(): void
    {
        [$u, , $t, $cm] = $this->world();
        $this->submittedShortlist($t, $cm);
        $this->actingAs($u)->get("/beta/client/campaigns/{$cm->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Campaigns/Show')
                ->where('campaign.id', $cm->id)
                ->where('shortlist.pending', 1));
    }

    public function test_shortlist_decision_approves(): void
    {
        [$u, , $t, $cm] = $this->world();
        $it = $this->submittedShortlist($t, $cm);
        $this->actingAs($u)->post("/beta/client/campaigns/{$cm->id}/shortlist/items/{$it->id}/decision", ['decision' => 'approved'])
            ->assertRedirect();
        TenantContext::set($t->id);
        $this->assertSame('approved', $it->fresh()->client_decision);
        TenantContext::reset();
    }

    public function test_decision_validates(): void
    {
        [$u, , $t, $cm] = $this->world();
        $it = $this->submittedShortlist($t, $cm);
        $this->actingAs($u)->post("/beta/client/campaigns/{$cm->id}/shortlist/items/{$it->id}/decision", ['decision' => 'maybe'])
            ->assertSessionHasErrors('decision');
    }

    public function test_detail_idor_safe(): void
    {
        [$u1] = $this->world();
        [, , , $cmB] = $this->world();
        $this->actingAs($u1)->get("/beta/client/campaigns/{$cmB->id}")->assertNotFound();
    }
}
