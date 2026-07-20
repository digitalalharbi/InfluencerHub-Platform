<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\{Invoice, InvoicePayment, Payout};
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Analytics\{ClientAnalytics, FinancialMetrics};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * صحّة الأرقام المالية.
 *
 * العيب الذي تحرسه: «الإيراد» كان مجموع **ميزانيات** الحملات و«التكلفة» مجموع
 * **أتعاب التعاونات** بما فيها الملغاة. فحملة بميزانية بلا فاتورة تُظهر إيرادًا،
 * وتعاون ملغى يُحمَّل تكلفةً، والربح والهامش يخرجان من طرفين خاطئين معًا.
 */
class FinancialMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Tenant,1:Client,2:Campaign,3:Creator} */
    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'ع', 'status' => 'active']);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $c->id,
            'name' => 'ح', 'status' => 'active', 'budget_minor' => 12000000, 'currency' => 'SAR']);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer',
            'display_name' => 'م', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $c, $cm, $cr];
    }

    private function invoice(Tenant $t, Client $c, Campaign $cm, string $status, int $sub, int $disc = 0, int $taxBp = 1500): Invoice
    {
        $tax = intdiv(($sub - $disc) * $taxBp, 10000);
        TenantContext::bypass(true);
        $i = Invoice::create(['tenant_id' => $t->id, 'invoice_number' => 'INV-' . Str::random(6),
            'client_id' => $c->id, 'campaign_id' => $cm->id, 'status' => $status, 'currency' => 'SAR',
            'subtotal_minor' => $sub, 'discount_minor' => $disc, 'tax_minor' => $tax,
            'total_minor' => $sub - $disc + $tax, 'tax_rate_bp' => $taxBp]);
        TenantContext::reset();

        return $i;
    }

    private function payout(Tenant $t, Campaign $cm, Creator $cr, string $status, int $amount): Payout
    {
        TenantContext::bypass(true);
        $p = Payout::create(['tenant_id' => $t->id, 'payout_number' => 'PY-' . Str::random(6),
            'creator_id' => $cr->id, 'campaign_id' => $cm->id, 'amount_minor' => $amount,
            'currency' => 'SAR', 'status' => $status]);
        TenantContext::reset();

        return $p;
    }

    // ===== المعادلتان الملزِمتان =====

    /** profit = revenue − recognized_costs */
    public function test_profit_equals_revenue_minus_recognized_costs(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'paid', 8000000);
        $this->payout($t, $cm, $cr, 'paid', 3000000);
        $this->payout($t, $cm, $cr, 'approved', 500000);

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(8000000, $m['revenue_minor']);
        $this->assertSame(3500000, $m['cost_minor']);
        $this->assertSame($m['revenue_minor'] - $m['cost_minor'], $m['profit_minor']);
    }

    /** margin = profit / revenue × 100 */
    public function test_margin_equals_profit_over_revenue(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'paid', 8000000);
        $this->payout($t, $cm, $cr, 'paid', 5500000);

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        // 8,000,000 − 5,500,000 = 2,500,000 ⇒ 31.25%
        $this->assertSame(2500000, $m['profit_minor']);
        $this->assertSame(31.3, $m['margin']);
        $this->assertSame(round($m['profit_minor'] / $m['revenue_minor'] * 100, 1), $m['margin']);
    }

    /** إيراد صفر لا يقسم على صفر. */
    public function test_zero_revenue_yields_zero_margin_not_a_division_error(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->payout($t, $cm, $cr, 'paid', 100000);

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(0, $m['revenue_minor']);
        $this->assertSame(0.0, $m['margin']);
        $this->assertSame(-100000, $m['profit_minor'], 'تكلفة بلا إيراد خسارة، لا صفر');
    }

    // ===== ما لا يُحتسب =====

    /** الميزانية خطّة لا إيرادًا: حملة بميزانية بلا فاتورة = إيراد صفر. */
    public function test_a_campaign_budget_alone_is_not_revenue(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();   // الميزانية 120,000 ولا فاتورة

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(0, $m['revenue_minor'], 'الميزانية حُسبت إيرادًا');
    }

    /** المسودة والملغاة لا تُعترف. */
    public function test_draft_and_cancelled_invoices_are_not_revenue(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'draft', 5000000);
        $this->invoice($t, $c, $cm, 'cancelled', 7000000);
        $this->invoice($t, $c, $cm, 'issued', 2000000);

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(2000000, $m['revenue_minor']);
    }

    /** المستحقّ الملغى أو الفاشل ليس تكلفة. */
    public function test_cancelled_and_failed_payouts_are_not_cost(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'paid', 8000000);
        $this->payout($t, $cm, $cr, 'paid', 1000000);
        $this->payout($t, $cm, $cr, 'cancelled', 900000);
        $this->payout($t, $cm, $cr, 'failed', 800000);

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(1000000, $m['cost_minor'], 'مستحقّ ملغى أو فاشل حُمّل تكلفةً');
    }

    // ===== الضريبة والتحصيل =====

    /** الضريبة تُحصَّل لحساب الدولة فلا تدخل الإيراد. */
    public function test_vat_is_excluded_from_revenue_and_reported_separately(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'paid', 8000000);   // ضريبة 1,200,000

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(8000000, $m['revenue_minor'], 'الضريبة ضُخّمت في الإيراد');
        $this->assertSame(1200000, $m['tax_minor']);
        $this->assertSame(9200000, $m['billed_minor']);
    }

    /** الخصم يُنقص الإيراد. */
    public function test_discount_reduces_revenue(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'issued', 8000000, 1000000);

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(7000000, $m['revenue_minor']);
    }

    /** التحصيل مقياس نقدي مستقلّ: فاتورة صادرة بلا دفع = إيراد بلا تحصيل. */
    public function test_collection_is_tracked_apart_from_revenue(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $i = $this->invoice($t, $c, $cm, 'partially_paid', 8000000);
        TenantContext::bypass(true);
        InvoicePayment::create(['tenant_id' => $t->id, 'invoice_id' => $i->id, 'amount_minor' => 4000000,
            'currency' => 'SAR', 'method' => 'bank_transfer', 'received_at' => now()->toDateString()]);
        TenantContext::reset();

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(8000000, $m['revenue_minor']);
        $this->assertSame(4000000, $m['collected_minor']);
        $this->assertSame(9200000 - 4000000, $m['outstanding_minor']);
    }

    // ===== توحيد المصدر =====

    /** الوكالة والعميل والحملة تعطي الرقم نفسه لبيانات واحدة. */
    public function test_agency_client_and_campaign_scopes_agree(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'paid', 8000000);
        $this->payout($t, $cm, $cr, 'paid', 5500000);

        TenantContext::set($t->id);
        $agency = FinancialMetrics::agency();
        $client = FinancialMetrics::client($c->id);
        $campaign = FinancialMetrics::campaign($cm->id);
        $perClient = FinancialMetrics::forClients([$c->id])[$c->id];
        TenantContext::reset();

        foreach (['revenue_minor', 'cost_minor', 'profit_minor'] as $k) {
            $this->assertSame($agency[$k], $client[$k], "اختلف {$k} بين الوكالة والعميل");
            $this->assertSame($agency[$k], $campaign[$k], "اختلف {$k} بين الوكالة والحملة");
            $this->assertSame($agency[$k], $perClient[$k], "اختلف {$k} في تجميع العملاء");
        }
    }

    /** وصفحة العملاء تقرأ من المصدر نفسه لا من تعريف خاصّ بها. */
    public function test_client_analytics_uses_the_same_source(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'paid', 8000000);
        $this->payout($t, $cm, $cr, 'paid', 5500000);

        TenantContext::set($t->id);
        $op = ClientAnalytics::operational();
        $page = ClientAnalytics::forPage(Client::query()->get());
        $expected = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame($expected['revenue_minor'], $op['revenue_minor']);
        $this->assertSame($expected['cost_minor'], $op['cost_minor']);
        $this->assertSame($expected['profit_minor'], $op['profit_minor']);
        $this->assertSame($expected['revenue_minor'], $page[$c->id]['revenue_minor']);
    }

    /** التعاون الملغى لا يظهر تكلفةً — العيب الحيّ الذي كشفه التقرير. */
    public function test_a_cancelled_collaboration_fee_is_not_a_cost(): void
    {
        [$t, $c, $cm, $cr] = $this->ctx();
        $this->invoice($t, $c, $cm, 'paid', 8000000);
        TenantContext::bypass(true);
        Collaboration::create(['tenant_id' => $t->id, 'collaboration_number' => 'CO-' . Str::random(5),
            'creator_id' => $cr->id, 'campaign_id' => $cm->id, 'client_id' => $c->id,
            'title' => 'ملغى', 'status' => 'cancelled', 'fee_minor' => 800000]);
        TenantContext::reset();

        TenantContext::set($t->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(0, $m['cost_minor'], 'أتعاب تعاون ملغى حُسبت تكلفةً');
    }

    /** العزل: أرقام مستأجر لا تتسرّب إلى آخر. */
    public function test_metrics_are_tenant_isolated(): void
    {
        [$t1, $c1, $cm1, $cr1] = $this->ctx();
        $this->invoice($t1, $c1, $cm1, 'paid', 8000000);
        [$t2] = $this->ctx();

        TenantContext::set($t2->id);
        $m = FinancialMetrics::agency();
        TenantContext::reset();

        $this->assertSame(0, $m['revenue_minor'], 'تسرّب إيراد بين المستأجرين');
    }
}
