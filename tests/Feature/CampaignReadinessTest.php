<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Analytics\CampaignAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * جاهزية التنفيذ — كل معيار حالةٌ صادقة (جاهز/يحتاج انتباه/محظور/لا ينطبق) مع سبب
 * ودليل وإجراء. تجاوز الميزانية = محظور مع دليل رقمي وإجراء. لا مفهوم «شطب».
 */
class CampaignReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ready(Campaign $c): array
    {
        return TenantContext::withBypass(fn () => CampaignAnalytics::readiness(
            $c->fresh()->load('client', 'brand', 'deliverables', 'contentItems'), []
        ));
    }

    private function mkCampaign(int $budget, int $feeMinor, int $qty): Campaign
    {
        $t = Tenant::create(['name' => 'و', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t, $budget, $feeMinor, $qty) {
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . Str::random(4), 'display_name' => 'عميل', 'status' => 'active']);
            $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . Str::random(4), 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active']);
            $c = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . Str::random(5), 'client_id' => $cl->id,
                'name' => 'حملة', 'status' => 'active', 'budget_minor' => $budget, 'currency' => 'SAR']);
            CampaignDeliverable::create(['tenant_id' => $t->id, 'campaign_id' => $c->id, 'creator_id' => $cr->id,
                'platform' => 'instagram', 'type' => 'post', 'quantity' => $qty, 'fee_minor' => $feeMinor, 'currency' => 'SAR', 'status' => 'planned']);
            return $c;
        });
    }

    private function item(array $r, string $label): array
    {
        return collect($r['items'])->firstWhere('label', $label);
    }

    public function test_over_budget_criterion_is_blocked_with_numeric_evidence_and_action(): void
    {
        // ميزانية 50,000 والتزامات 2×40,000 = 80,000 → تجاوز
        $c = $this->mkCampaign(budget: 5000000, feeMinor: 4000000, qty: 2);
        $r = $this->ready($c);

        $within = $this->item($r, 'ضمن الميزانية');
        $this->assertSame('blocked', $within['state']);
        $this->assertStringContainsString('80,000', $within['evidence']);   // الالتزامات
        $this->assertStringContainsString('50,000', $within['evidence']);   // الميزانية
        $this->assertStringContainsString('ر.س', $within['evidence']);      // ريال عربي لا SAR
        $this->assertStringNotContainsString('SAR', $within['evidence']);
        $this->assertNotNull($within['action']);                             // إجراء عامل
        $this->assertGreaterThanOrEqual(1, $r['blocked']);
        $this->assertSame('SAR', $r['budget']['currency']);
        $this->assertTrue($r['budget']['overBudget']);
        $this->assertSame(-3000000, $r['budget']['remainingMinor']);        // تجاوز 30,000
        $this->assertSame(60, $r['budget']['variancePct']);                 // 30,000/50,000
    }

    public function test_completed_criterion_is_ready_not_struck_out(): void
    {
        // ضمن الميزانية: 50,000 والتزامات 2×20,000 = 40,000
        $c = $this->mkCampaign(budget: 5000000, feeMinor: 2000000, qty: 2);
        $r = $this->ready($c);

        $within = $this->item($r, 'ضمن الميزانية');
        $this->assertSame('ready', $within['state']);                       // مكتمل = جاهز لا ملغى
        $this->assertArrayNotHasKey('done', $within);                       // لا مفهوم شطب ثنائي
        $this->assertNull($within['action']);                               // لا إجراء للجاهز
        $this->assertNotNull($within['reason']);                            // سبب دائمًا

        $assigned = $this->item($r, 'كل مخرج مُسنَد لمبدع');
        $this->assertSame('ready', $assigned['state']);                     // مُسنَد فعلًا
        $this->assertFalse($r['budget']['overBudget']);
    }

    public function test_no_budget_makes_budget_criteria_not_applicable(): void
    {
        $c = $this->mkCampaign(budget: 0, feeMinor: 2000000, qty: 1);
        $r = $this->ready($c);
        $this->assertSame('not_applicable', $this->item($r, 'ضمن الميزانية')['state']);
        $this->assertSame('attention', $this->item($r, 'الميزانية محدّدة')['state']);
    }
}
