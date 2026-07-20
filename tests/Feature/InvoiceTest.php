<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Finance\Models\{Invoice, InvoicePayment};
use App\Domain\Finance\Services\InvoiceService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * فواتير العملاء — الطرف المقابل لمستحقات المبدعين.
 *
 * ما يحرسه هذا الملفّ هو الدفتر: أن الحساب صحيح بالأعداد الصحيحة، وأن الحالة
 * تُشتقّ من المدفوعات لا تُضبَط يدويًّا، وأن الوثيقة الصادرة لا تُعدَّل بأثر رجعي.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User,2:Client} */
    private function ctx(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 'و', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id,
            'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . Str::random(4),
            'display_name' => 'عميل', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $u, $c];
    }

    private function draft(Tenant $t, Client $c, User $u, int $unitMinor = 8000000, int $discount = 0): Invoice
    {
        TenantContext::set($t->id);
        $inv = app(InvoiceService::class)->create($t->id, [
            'client_id' => $c->id, 'discount_minor' => $discount,
        ], [['description' => 'إدارة حملة', 'quantity' => 1, 'unit_price_minor' => $unitMinor]], $u->id);
        TenantContext::reset();

        return $inv;
    }

    // ===== الحساب =====

    /** 80,000 + 15٪ = 92,000 — بالأعداد الصحيحة لا بالفاصلة العائمة. */
    public function test_totals_are_computed_from_items_with_vat(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u);

        $this->assertSame(8000000, $inv->subtotal_minor);
        $this->assertSame(1200000, $inv->tax_minor);
        $this->assertSame(9200000, $inv->total_minor);
    }

    /**
     * فاتورة بنسبة غير الافتراضية تُحسب بنسبتها هي.
     *
     * معاينة الإنشاء في الواجهة كانت تضرب في 15٪ ثابتة بينما العمود
     * `tax_rate_bp` لكل فاتورة — فتتّفق الأرقام اليوم وتفترق أوّل ما تختلف
     * النسبة. النسبة تُنشر الآن من `InvoiceService::DEFAULT_TAX_RATE_BP`.
     */
    public function test_an_invoice_uses_its_own_tax_rate_not_a_fixed_fifteen(): void
    {
        [$t, $u, $c] = $this->ctx();
        $svc = app(\App\Domain\Finance\Services\InvoiceService::class);

        TenantContext::set($t->id);
        $inv = $svc->create(
            $t->id,
            ['client_id' => $c->id, 'tax_rate_bp' => 500],   // 5٪ لا 15٪
            [['description' => 'بند', 'quantity' => 1, 'unit_price_minor' => 10000000]],
            $u->id,
        );
        TenantContext::reset();

        $this->assertSame(500, (int) $inv->tax_rate_bp);
        $this->assertSame(500000, (int) $inv->tax_minor, 'حُسبت الضريبة بنسبة ثابتة لا بنسبة الفاتورة');
        $this->assertSame(10500000, (int) $inv->total_minor);
    }

    /** الافتراضي يبقى 15٪ ويُنشر من مصدر واحد تقرؤه الواجهة. */
    public function test_the_default_vat_rate_is_published_from_one_place(): void
    {
        $this->assertSame(1500, \App\Domain\Finance\Services\InvoiceService::DEFAULT_TAX_RATE_BP);
    }

    /** الضريبة تُحسب بعد الخصم — عكس الترتيب يغيّر المبلغ. */
    public function test_tax_is_calculated_after_discount(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u, 10000000, 2000000);

        $this->assertSame(8000000, $inv->subtotal_minor - $inv->discount_minor);
        $this->assertSame(1200000, $inv->tax_minor);
        $this->assertSame(9200000, $inv->total_minor);
    }

    public function test_discount_cannot_exceed_the_subtotal(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u, 1000, 999999);

        $this->assertSame(1000, $inv->discount_minor, 'تجاوز الخصم المجموع');
        $this->assertSame(0, $inv->total_minor);
    }

    // ===== دورة الحياة =====

    public function test_an_empty_invoice_cannot_be_issued(): void
    {
        [$t, $u, $c] = $this->ctx();
        TenantContext::set($t->id);
        $inv = app(InvoiceService::class)->create($t->id, ['client_id' => $c->id], [], $u->id);

        $this->expectExceptionMessage('أضِف بندًا واحدًا على الأقل قبل الإصدار.');
        app(InvoiceService::class)->issue($inv, $u->id);
    }

    /** الوثيقة الصادرة وصلت العميل — تعديلها يجعل نسخته مخالفة لنسختنا. */
    public function test_an_issued_invoice_cannot_be_edited(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u);
        TenantContext::set($t->id);
        app(InvoiceService::class)->issue($inv, $u->id);

        $this->expectException(\RuntimeException::class);
        app(InvoiceService::class)->updateDraft($inv->fresh(), [], [
            ['description' => 'تلاعب', 'quantity' => 1, 'unit_price_minor' => 1],
        ], $u->id);
    }

    public function test_payment_is_refused_before_the_invoice_is_issued(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u);
        TenantContext::set($t->id);

        $this->expectExceptionMessage('لا تُسجَّل دفعة على فاتورة لم تُصدَر بعد.');
        app(InvoiceService::class)->recordPayment($inv, [
            'amount_minor' => 100, 'method' => 'bank_transfer', 'received_at' => now()->toDateString(),
        ], $u->id);
    }

    /** تحصيل أكثر من المستحقّ خطأ إدخال لا واقعة مالية. */
    public function test_payment_cannot_exceed_the_outstanding_balance(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u);
        TenantContext::set($t->id);
        app(InvoiceService::class)->issue($inv, $u->id);

        $this->expectException(\RuntimeException::class);
        app(InvoiceService::class)->recordPayment($inv->fresh(), [
            'amount_minor' => 9200001, 'method' => 'bank_transfer', 'received_at' => now()->toDateString(),
        ], $u->id);
    }

    /** الحالة تُشتقّ من الدفتر: جزئيّ ثم مكتمل بلا ضبط يدوي. */
    public function test_status_follows_the_payments_ledger(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u);
        TenantContext::set($t->id);
        $svc = app(InvoiceService::class);
        $svc->issue($inv, $u->id);

        $svc->recordPayment($inv->fresh(), ['amount_minor' => 4000000, 'method' => 'bank_transfer',
            'received_at' => now()->toDateString()], $u->id);
        $this->assertSame('partially_paid', $inv->fresh()->status);
        $this->assertSame(5200000, $inv->fresh()->balanceMinor());

        $svc->recordPayment($inv->fresh(), ['amount_minor' => 5200000, 'method' => 'bank_transfer',
            'received_at' => now()->toDateString()], $u->id);
        $fresh = $inv->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame(0, $fresh->balanceMinor());
        $this->assertNotNull($fresh->paid_at);
    }

    /** الإلغاء يمحو أثر مبلغ استُلم فعلًا — يُرفض ويُقترح الإشعار الدائن. */
    public function test_an_invoice_with_payments_cannot_be_cancelled(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u);
        TenantContext::set($t->id);
        $svc = app(InvoiceService::class);
        $svc->issue($inv, $u->id);
        $svc->recordPayment($inv->fresh(), ['amount_minor' => 1000, 'method' => 'cash',
            'received_at' => now()->toDateString()], $u->id);

        $this->expectExceptionMessage('استُلمت دفعات على هذه الفاتورة — أصدِر إشعارًا دائنًا بدل الإلغاء.');
        $svc->cancel($inv->fresh(), 'تراجع', $u->id);
    }

    public function test_status_history_records_every_transition(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u);
        TenantContext::set($t->id);
        app(InvoiceService::class)->issue($inv, $u->id);

        $this->assertEqualsCanonicalizing(
            ['draft', 'issued'],
            $inv->fresh()->statusHistory->pluck('to_status')->all(),
        );
    }

    // ===== الصلاحيات والعزل =====

    /** الأفعال المالية ليست لكل من يملك التحرير. */
    public function test_a_campaign_manager_cannot_issue_or_collect(): void
    {
        [$t, $u, $c] = $this->ctx('campaign_manager');
        $inv = $this->draft($t, $c, $u);

        $this->actingAs($u)->get('/app/invoices')->assertOk();      // القراءة مسموحة
        $this->actingAs($u)->post("/app/invoices/{$inv->id}/issue")->assertForbidden();
        $this->actingAs($u)->post("/app/invoices/{$inv->id}/pay", [
            'amount_riyals' => '1', 'method' => 'cash', 'received_at' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_finance_role_can_issue(): void
    {
        [$t, $u, $c] = $this->ctx('finance');
        $inv = $this->draft($t, $c, $u);

        $this->actingAs($u)->post("/app/invoices/{$inv->id}/issue")->assertRedirect();
        $this->assertSame('issued', $inv->fresh()->status);
    }

    public function test_invoice_of_another_tenant_is_not_reachable(): void
    {
        [, $mine] = $this->ctx();
        [$otherT, $otherU, $otherC] = $this->ctx();
        $foreign = $this->draft($otherT, $otherC, $otherU);

        $this->actingAs($mine)->get("/app/invoices/{$foreign->id}")->assertNotFound();
        $this->actingAs($mine)->post("/app/invoices/{$foreign->id}/issue")->assertNotFound();
    }

    public function test_campaign_of_a_different_client_is_rejected(): void
    {
        [$t, $u, $c] = $this->ctx();
        TenantContext::bypass(true);
        $otherClient = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-X',
            'display_name' => 'آخر', 'status' => 'active']);
        $campaign = \App\Domain\Campaigns\Models\Campaign::create(['tenant_id' => $t->id,
            'campaign_number' => 'CM-X', 'client_id' => $otherClient->id, 'name' => 'ح',
            'status' => 'draft', 'budget_minor' => 100, 'currency' => 'SAR']);
        TenantContext::reset();

        $this->actingAs($u)->post('/app/invoices', [
            'client_id' => $c->id, 'campaign_id' => $campaign->id,
            'items' => [['description' => 'بند', 'quantity' => 1, 'unit_price_riyals' => '10']],
        ])->assertStatus(422);
    }

    /** المرجع نفسه من المزوّد نفسه لا يُقيَّد مرّتين. */
    public function test_the_same_provider_reference_cannot_be_recorded_twice(): void
    {
        [$t, $u, $c] = $this->ctx();
        $inv = $this->draft($t, $c, $u);
        TenantContext::set($t->id);
        app(InvoiceService::class)->issue($inv, $u->id);

        $row = ['tenant_id' => $t->id, 'invoice_id' => $inv->id, 'amount_minor' => 1000,
            'method' => 'provider', 'provider' => 'moyasar', 'provider_reference' => 'ref-1',
            'received_at' => now()->toDateString()];
        InvoicePayment::create($row);

        $this->expectException(\Illuminate\Database\QueryException::class);
        InvoicePayment::create($row);
    }
}
