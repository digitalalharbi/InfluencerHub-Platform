<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** N5 — التصدير الداخليّ للترشيح متاح بثلاث صيغ: xlsx/csv/pdf (نفس محرّك التصدير). */
class ShortlistPdfExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        [$u, $cm] = TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'مدير', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'ع', 'type' => 'company', 'status' => 'active']);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'client_id' => $cl->id, 'name' => 'ح', 'status' => 'active', 'budget_minor' => 500000, 'currency' => 'SAR']);
            $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer', 'display_name' => 'نورة', 'handle' => '@n', 'primary_platform' => 'instagram', 'followers_count' => 100000, 'status' => 'active', 'rate_per_post_minor' => 300000]);
            $svc = app(ShortlistService::class);
            $svc->addCreator($svc->getOrCreate($cm, $u->id)->currentVersion(), $cr);

            return [$u, $cm];
        });

        return [$u, $cm];
    }

    public function test_internal_shortlist_exports_as_pdf(): void
    {
        [$u, $cm] = $this->world();
        $res = $this->actingAs($u)->get("/app/campaigns/{$cm->id}/shortlist/export?format=pdf");
        $res->assertOk();
        $this->assertStringContainsString('pdf', strtolower((string) $res->headers->get('content-type')), 'نوع المحتوى PDF');
        $this->assertStringContainsString('%PDF', (string) $res->getContent(), 'محتوى PDF فعليّ');
    }

    public function test_internal_shortlist_exports_as_csv_and_xlsx(): void
    {
        [$u, $cm] = $this->world();
        $this->actingAs($u)->get("/app/campaigns/{$cm->id}/shortlist/export?format=csv")->assertOk();
        $this->actingAs($u)->get("/app/campaigns/{$cm->id}/shortlist/export?format=xlsx")->assertOk();
    }
}
