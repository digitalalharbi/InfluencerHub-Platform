<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Publishers\Models\Publisher;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** الناشرون — ذكاء الاكتشاف React/Inertia: عرض صادق + حفظ + تحويل idempotent + عزل + بوابة الدور. */
class InertiaPublishersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Tenant} */
    private function agent(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();
        return [$u, $t];
    }

    private function publisher(Tenant $t, array $over = []): Publisher
    {
        TenantContext::set($t->id);
        $p = Publisher::create(array_merge([
            'tenant_id' => $t->id, 'publisher_number' => 'PB-' . $t->id . '-' . Str::random(3),
            'platform' => 'instagram', 'handle' => '@' . Str::random(5), 'display_name' => 'ناشر',
            'followers_count' => 120000, 'engagement_rate' => 4.5, 'growth_30d' => 2.0,
            'categories' => ['fashion'], 'source' => 'sandbox', 'quality_score' => 80, 'last_synced_at' => now(),
        ], $over));
        TenantContext::reset();
        return $p;
    }

    public function test_index_shows_honest_connectors_and_publishers(): void
    {
        [$u, $t] = $this->agent();
        $this->publisher($t);
        $this->actingAs($u)->get('/beta/publishers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publishers/Index')
                ->has('publishers.data', 1)
                ->where('publishers.data.0.source', 'sandbox')
                ->has('connectors')
                // لا موصّل بحالة connected وهمية — الاكتشاف الحيّ بانتظار الاعتماد
                ->where('connectors.0.discoveryState', fn ($s) => $s !== 'connected')
                ->where('connectorSummary.live', 0));
    }

    public function test_tabs_analytics_and_comparison(): void
    {
        [$u, $t] = $this->agent();
        $this->publisher($t, ['handle' => '@a', 'followers_count' => 300000, 'engagement_rate' => 6.0]);
        $this->publisher($t, ['handle' => '@b', 'followers_count' => 100000, 'engagement_rate' => 2.0]);

        $this->actingAs($u)->get('/beta/publishers?tab=analytics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'analytics')
                ->where('analytics.totals.publishers', 2)
                ->where('analytics.totals.followers', 400000)
                ->has('analytics.byPlatform')
                ->has('analytics.topEngagement'));

        $this->actingAs($u)->get('/beta/publishers?tab=comparison')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tab', 'comparison')->has('compareOptions', 2));
    }

    public function test_lists_tab_shows_saved_only(): void
    {
        [$u, $t] = $this->agent();
        $saved = $this->publisher($t, ['handle' => '@s', 'saved' => true]);
        $this->publisher($t, ['handle' => '@n', 'saved' => false]);

        $this->actingAs($u)->get('/beta/publishers?tab=lists')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tab', 'lists')->has('publishers.data', 1)->where('publishers.data.0.id', $saved->id));
    }

    public function test_show_renders_profile(): void
    {
        [$u, $t] = $this->agent();
        $p = $this->publisher($t);
        $this->actingAs($u)->get("/beta/publishers/{$p->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Publishers/Show')->where('publisher.id', $p->id)->where('publisher.source', 'sandbox'));
    }

    public function test_save_toggles(): void
    {
        [$u, $t] = $this->agent();
        $p = $this->publisher($t);
        $this->actingAs($u)->post("/beta/publishers/{$p->id}/save")->assertRedirect();
        TenantContext::set($t->id);
        $this->assertTrue($p->fresh()->saved);
        TenantContext::reset();
    }

    public function test_convert_is_idempotent_no_duplicate(): void
    {
        [$u, $t] = $this->agent('agency_admin');
        $p = $this->publisher($t);
        $this->actingAs($u)->post("/beta/publishers/{$p->id}/convert")->assertRedirect();
        $this->actingAs($u)->post("/beta/publishers/{$p->id}/convert")->assertRedirect(); // مرّتان
        TenantContext::set($t->id);
        $this->assertSame(1, Creator::where('publisher_id', $p->id)->count()); // لا تكرار
        $this->assertNotNull($p->fresh()->converted_creator_id);
        TenantContext::reset();
    }

    public function test_viewer_cannot_convert(): void
    {
        [$u, $t] = $this->agent('viewer');
        $p = $this->publisher($t);
        $this->actingAs($u)->post("/beta/publishers/{$p->id}/convert")->assertForbidden();
    }

    public function test_tenant_isolation(): void
    {
        [$u1] = $this->agent();
        [, $t2] = $this->agent();
        $pB = $this->publisher($t2);
        $this->actingAs($u1)->get("/beta/publishers/{$pB->id}")->assertNotFound();
    }
}
