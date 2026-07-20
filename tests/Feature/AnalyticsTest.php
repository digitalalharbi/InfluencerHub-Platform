<?php

namespace Tests\Feature;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Campaigns\Services\CampaignWorkflowService;
use App\Domain\Collaborations\Services\CollaborationWorkflowService;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Services\PayoutWorkflowService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Phase 12 — التقارير: تجميعات حقيقية + عزل مستأجر (لا تسرّب بيانات). */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $ag = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $ag->id, 'role' => 'agency_admin', 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-1', 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active']);
        TenantContext::reset();
        return [$t, $org, $ag, $c, $cr];
    }

    private function seedData(Tenant $t, Client $c, Creator $cr, int $by): void
    {
        $cw = app(CampaignWorkflowService::class);
        $cm = $cw->create($t->id, ['client_id' => $c->id, 'name' => 'حملة', 'budget_minor' => 5000000], $by);
        $cw->addDeliverable($cm, ['type' => 'reel', 'quantity' => 1, 'fee_minor' => 300000], $by);
        $cw->activate($cw->plan($cm, $by), $by); // active campaign
        app(CollaborationWorkflowService::class)->offer($t->id, ['creator_id' => $cr->id, 'title' => 'ت', 'fee_minor' => 100000], $by);
        $pw = app(PayoutWorkflowService::class);
        $p = $pw->create($t->id, ['creator_id' => $cr->id, 'amount_minor' => 200000], $by);
        $pw->markPaid($pw->sendToProvider($pw->schedule($pw->approve($p, $by), $by), $by), $by, 'TRX');
    }

    public function test_overview_reflects_real_aggregates(): void
    {
        [$t, $org, $ag, $c, $cr] = $this->ctx();
        $this->seedData($t, $c, $cr, $ag->id);
        TenantContext::set($t->id, $org->id);
        $data = app(AnalyticsService::class)->agencyOverview();
        TenantContext::reset();

        $this->assertEquals(1, $data['clients']['active']);
        $this->assertEquals(1, $data['creators']['active']);
        $this->assertEquals(1, $data['campaigns']['active']);
        $this->assertEquals(5000000, $data['campaigns']['budget_minor']);
        $this->assertEquals(1, $data['collaborations']['total']);
        $this->assertEquals(200000, $data['payouts']['paid_minor']);
        $this->assertArrayHasKey('influencer', $data['creators']['by_capability']);
    }

    public function test_overview_is_tenant_isolated(): void
    {
        [$t1, $o1, $ag1, $c1, $cr1] = $this->ctx();
        [$t2, $o2, $ag2, $c2, $cr2] = $this->ctx();
        $this->seedData($t1, $c1, $cr1, $ag1->id); // بيانات المستأجر 1 فقط

        TenantContext::set($t2->id, $o2->id);
        $data2 = app(AnalyticsService::class)->agencyOverview();
        TenantContext::reset();
        // المستأجر 2 لا يرى حملات/مستحقات المستأجر 1
        $this->assertEquals(0, $data2['campaigns']['total']);
        $this->assertEquals(0, $data2['payouts']['paid_minor']);
        $this->assertEquals(1, $data2['clients']['total']); // عميله هو فقط
    }

    public function test_reports_page_renders_over_http(): void
    {
        [$t, $org, $ag, $c, $cr] = $this->ctx();
        $this->seedData($t, $c, $cr, $ag->id);
        // /app/reports صار React/Inertia — نتحقّق من المكوّن والبيانات لا من HTML الخادم
        $this->actingAs($ag)->get('/app/reports')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Reports/Index')
                ->has('financial.revenueMinor')
                ->has('breakdowns.campaigns')
                ->has('timeline')
                ->has('topClients'));
    }

    public function test_viewer_can_view_reports(): void
    {
        // العرض متاح لدور العرض (viewaAny=VIEW)
        [$t, $org, $ag, $c, $cr] = $this->ctx();
        TenantContext::bypass(true);
        $viewer = User::create(['name' => 'V', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active']);
        TenantContext::reset();
        $this->actingAs($viewer)->get('/app/reports')->assertOk();
    }

    public function test_creator_accept_rate_stays_bounded_with_cancelled_collabs(): void
    {
        [$t, $org, $ag, $c, $cr] = $this->ctx();
        TenantContext::set($t->id, $org->id);
        // عروض ملغاة (لا يجب أن تُحتسب قبولًا) + عرض مقبول واحد
        foreach (['cancelled', 'cancelled', 'accepted'] as $i => $st) {
            \App\Domain\Collaborations\Models\Collaboration::create([
                'tenant_id' => $t->id, 'creator_id' => $cr->id, 'title' => 'ت' . $i,
                'fee_minor' => 100000, 'currency' => 'SAR', 'status' => $st,
            ]);
        }
        $intel = \App\Support\Analytics\CreatorAnalytics::intelligence($cr->fresh());
        $rate = $intel['metrics']['accept_rate'];
        $this->assertNotNull($rate);
        $this->assertGreaterThanOrEqual(0, $rate);
        $this->assertLessThanOrEqual(100, $rate, 'معدّل القبول يجب ألا يتجاوز 100% رغم وجود تعاونات ملغاة');
        TenantContext::reset();
    }
}
