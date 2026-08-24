<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Exports\Models\ExportJob;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أثر مقترح الترشيحات (آمن للعميل) بنفس معمارية الحملة: المعاينة والتنزيل نفس
 * البايتات (sha256)، روابط نسبية للتركيب، ومقصور بالمستأجر.
 */
class ShortlistProposalArtifactTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Tenant,1:User,2:Campaign} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-9', 'client_id' => $cl->id,
                'name' => 'حملة', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR']);
            $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-1', 'type' => 'influencer',
                'display_name' => 'مبدع', 'handle' => 'creator', 'primary_platform' => 'instagram', 'followers_count' => 120000, 'status' => 'active']);
            $sl = app(ShortlistService::class)->getOrCreate($cm, $u->id);
            $it = app(ShortlistService::class)->addCreator($sl->currentVersion(), $cr);
            $it->update(['proposed_fee_minor' => 300000]);
            return [$t, $u, $cm];
        });
    }

    public function test_proposal_preview_and_download_same_bytes_and_mount_relative_urls(): void
    {
        Storage::fake('local');
        [$t, $u, $cm] = $this->world();

        $prev = $this->actingAs($u)->get("/app/campaigns/{$cm->id}/shortlist/proposal/preview");
        $prev->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $prev->headers->get('content-disposition'));
        $down = $this->actingAs($u)->get("/app/campaigns/{$cm->id}/shortlist/proposal/download");
        $down->assertOk();
        $this->assertStringContainsString('attachment', $down->headers->get('content-disposition'));

        $this->assertStringStartsWith('%PDF-', $prev->getContent());
        $this->assertSame(hash('sha256', $prev->getContent()), hash('sha256', $down->getContent()));
        $this->assertSame(1, TenantContext::withBypass(fn () => ExportJob::where('type', 'shortlist_client_proposal')->count()));

        $this->actingAs($u)->get("/app/campaigns/{$cm->id}/shortlist")
            ->assertInertia(fn ($p) => $p
                ->where('documents.proposal.previewUrl', "/campaigns/{$cm->id}/shortlist/proposal/preview")
                ->where('documents.proposal.hasArtifact', true));
    }

    public function test_proposal_preview_tenant_scoped(): void
    {
        Storage::fake('local');
        [$t, $u, $cm] = $this->world();
        [, $stranger] = $this->world();
        $this->actingAs($stranger)->get("/app/campaigns/{$cm->id}/shortlist/proposal/preview")->assertNotFound();
    }
}
