<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تصدير الحملات والترشيحات: النسخة الداخلية (CSV/XLSX) تحمل التكلفة والدرجة، أمّا
 * النسخة الآمنة للعميل (PDF) فمسار منفصل مُدقَّق يستبعد تكلفة المبدع والهامش ودرجة
 * المطابقة وأسبابها وبيانات التواصل الخاصّة.
 */
class CampaignShortlistExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Tenant,1:User,2:Campaign,3:Creator} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'أحمد', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-9', 'client_id' => $cl->id,
                'name' => 'حملة الصيف', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR']);
            CampaignDeliverable::create(['tenant_id' => $t->id, 'campaign_id' => $cm->id, 'platform' => 'instagram',
                'type' => 'post', 'quantity' => 2, 'fee_minor' => 444000, 'currency' => 'SAR', 'status' => 'planned']);
            $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-1', 'type' => 'influencer',
                'display_name' => 'مبدع مطابق', 'handle' => 'match', 'primary_platform' => 'instagram',
                'followers_count' => 250000, 'status' => 'active', 'rate_per_post_minor' => 333000,
                'email' => 'secret@creator.test', 'phone' => '0509998888', 'mowthooq_status' => 'verified']);
            return [$t, $u, $cm, $cr];
        });
    }

    private function bodyOf($response): string
    {
        ob_start();
        $response->sendContent();
        return ob_get_clean();
    }

    private function seedShortlist(Tenant $t, User $u, Campaign $cm, Creator $cr): void
    {
        TenantContext::withBypass(function () use ($u, $cm, $cr) {
            $svc = app(ShortlistService::class);
            $sl = $svc->getOrCreate($cm, $u->id);
            $item = $svc->addCreator($sl->currentVersion(), $cr);
            $item->update(['proposed_fee_minor' => 400000, 'match_score' => 87, 'reasons' => ['سبب-داخلي-سرّي']]);
        });
    }

    // ---- Campaign ----

    public function test_campaign_internal_export_includes_committed_cost(): void
    {
        [$t, $u, $cm] = $this->world();
        $res = $this->actingAs($u)->get('/app/campaigns/export?format=csv');
        $res->assertOk();
        $csv = $this->bodyOf($res->baseResponse);
        $this->assertStringContainsString('CM-9', $csv);
        $this->assertStringContainsString('التكلفة الملتزَمة', $csv);       // عمود داخلي موجود
        $this->assertStringContainsString('8,880.00', $csv);                 // 2 × 4,440 التزام
    }

    public function test_campaign_client_brief_is_pdf_and_audited_via_separate_path(): void
    {
        [$t, $u, $cm] = $this->world();
        $res = $this->actingAs($u)->get("/app/campaigns/{$cm->id}/client-brief");
        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $this->bodyOf($res->baseResponse));
        TenantContext::bypass(true);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $t->id, 'action' => 'export.generated']);
        TenantContext::reset();
    }

    public function test_campaign_brief_template_excludes_internal_money(): void
    {
        // القالب الآمن للعميل لا يعرض التكلفة الملتزَمة ولا أتعاب المبدع مهما مُرِّر
        $html = view('exports.campaign-brief', [
            'workspace' => 'وكالة', 'number' => 'CM-9', 'name' => 'حملة', 'client' => 'عميل', 'brand' => null,
            'objective' => 'هدف', 'brief' => 'موجز', 'statusLabel' => 'نشطة', 'budget' => '50,000 SAR',
            'start' => '2026-01-01', 'end' => '2026-02-01', 'progress' => 40,
            'deliverables' => [['platform' => 'instagram', 'type' => 'post', 'quantity' => 2, 'due' => '—', 'status' => 'مخطّط']],
            'generatedAt' => '2026-08-24 10:00',
        ])->render();
        $this->assertStringContainsString('50,000 SAR', $html);           // الميزانية (آمنة) تظهر
        $this->assertStringNotContainsString('التكلفة الملتزَمة', $html);  // لا عمود تكلفة داخلي
        $this->assertStringNotContainsString('fee_minor', $html);
    }

    // ---- Shortlist ----

    public function test_shortlist_internal_export_includes_score_and_fee(): void
    {
        [$t, $u, $cm, $cr] = $this->world();
        $this->seedShortlist($t, $u, $cm, $cr);
        $res = $this->actingAs($u)->get("/app/campaigns/{$cm->id}/shortlist/export?format=csv");
        $res->assertOk();
        $csv = $this->bodyOf($res->baseResponse);
        $this->assertStringContainsString('مبدع مطابق', $csv);
        $this->assertStringContainsString('درجة المطابقة', $csv);   // عمود داخلي
        $this->assertStringContainsString('87', $csv);              // الدرجة
    }

    public function test_shortlist_client_proposal_is_pdf_and_audited(): void
    {
        [$t, $u, $cm, $cr] = $this->world();
        $this->seedShortlist($t, $u, $cm, $cr);
        $res = $this->actingAs($u)->get("/app/campaigns/{$cm->id}/shortlist/proposal");
        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $this->bodyOf($res->baseResponse));
        TenantContext::bypass(true);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $t->id, 'action' => 'export.generated']);
        TenantContext::reset();
    }

    public function test_shortlist_proposal_template_excludes_score_reasons_and_pii(): void
    {
        $html = view('exports.shortlist-proposal', [
            'workspace' => 'وكالة', 'campaign' => 'حملة', 'number' => 'CM-9', 'client' => 'عميل', 'brand' => null,
            'versionNo' => 1,
            'items' => [[
                'creator' => 'مبدع مطابق', 'handle' => 'match', 'platform' => 'instagram',
                'followers' => '250,000', 'backup' => false, 'fee' => '4,000 SAR', 'decision' => 'قيد المراجعة',
            ]],
            'generatedAt' => '2026-08-24 10:00',
        ])->render();
        $this->assertStringContainsString('مبدع مطابق', $html);       // اسم المبدع (آمن)
        $this->assertStringContainsString('4,000 SAR', $html);         // السعر المقترح (آمن)
        $this->assertStringNotContainsString('درجة المطابقة', $html);  // لا درجة مطابقة
        $this->assertStringNotContainsString('سبب-داخلي', $html);      // لا أسباب داخلية
        $this->assertStringNotContainsString('match_score', $html);
    }
}
