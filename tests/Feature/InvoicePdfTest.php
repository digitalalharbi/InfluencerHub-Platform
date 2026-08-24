<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Finance\Models\{Invoice, InvoiceItem};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * فاتورة PDF عربية RTL — تُولَّد فعلًا من فاتورة حقيقية ببنودها، وتُثبت أنّها ملفّ
 * PDF صحيح غير تالف. عيّنة تُكتب إلى scratchpad للفحص البصري.
 */
class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function setup_invoice(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t, $role) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة النخبة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'نسيم التجارية', 'type' => 'company', 'status' => 'active']);
            $br = Brand::create(['tenant_id' => $t->id, 'client_id' => $cl->id, 'name' => 'علامة نسيم', 'slug' => Str::random(6), 'status' => 'approved', 'current_version' => 1]);
            $cm = Campaign::create(['tenant_id' => $t->id, 'client_id' => $cl->id, 'brand_id' => $br->id, 'name' => 'حملة الصيف', 'status' => 'active']);
            $inv = Invoice::create([
                'tenant_id' => $t->id, 'invoice_number' => 'INV-1-2026', 'client_id' => $cl->id, 'campaign_id' => $cm->id, 'brand_id' => $br->id,
                'status' => 'issued', 'currency' => 'SAR', 'subtotal_minor' => 5000000, 'discount_minor' => 250000,
                'tax_minor' => 712500, 'total_minor' => 5462500, 'tax_rate_bp' => 1500, 'issue_date' => now(), 'due_date' => now()->addDays(30),
                'notes' => 'يُرجى السداد خلال 30 يومًا.',
            ]);
            InvoiceItem::create(['tenant_id' => $t->id, 'invoice_id' => $inv->id, 'description' => 'إدارة حملة مؤثرين — 5 مبدعين', 'quantity' => 1, 'unit_price_minor' => 3000000, 'line_total_minor' => 3000000, 'sort_order' => 1]);
            InvoiceItem::create(['tenant_id' => $t->id, 'invoice_id' => $inv->id, 'description' => 'إنتاج محتوى UGC', 'quantity' => 4, 'unit_price_minor' => 500000, 'line_total_minor' => 2000000, 'sort_order' => 2]);
            return [$t, $u, $inv];
        });
    }

    public function test_invoice_pdf_downloads_and_is_valid(): void
    {
        [, $u, $inv] = $this->setup_invoice();
        $res = $this->actingAs($u)->get("/app/invoices/{$inv->id}/pdf");
        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');

        ob_start();
        $res->baseResponse->sendContent();
        $pdf = ob_get_clean();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(3000, strlen($pdf));

        $dir = '/private/tmp/claude-501/-Users-mohammedalharbimacbook-Desktop/6b8f5606-3e3b-4dfc-a1d5-1805c0d4247d/scratchpad';
        if (is_dir($dir)) {
            file_put_contents("$dir/sample-invoice.pdf", $pdf);
        }
    }

    public function test_invoice_pdf_is_audit_logged(): void
    {
        [$t, $u, $inv] = $this->setup_invoice();
        $this->actingAs($u)->get("/app/invoices/{$inv->id}/pdf")->assertOk();
        TenantContext::bypass(true);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $t->id, 'action' => 'export.generated', 'auditable_id' => $inv->id]);
        TenantContext::reset();
    }

    public function test_invoice_pdf_guest_redirected(): void
    {
        [, , $inv] = $this->setup_invoice();
        $this->get("/app/invoices/{$inv->id}/pdf")->assertRedirect('/login');
    }

    /** المعاينة والتنزيل يبثّان نفس أثر الفاتورة (sha256 متطابق)، وروابط نسبية للتركيب. */
    public function test_invoice_preview_and_download_same_artifact_bytes(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$t, $u, $inv] = $this->setup_invoice();

        $prev = $this->actingAs($u)->get("/app/invoices/{$inv->id}/pdf/preview");
        $prev->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $prev->headers->get('content-disposition'));
        $down = $this->actingAs($u)->get("/app/invoices/{$inv->id}/pdf/download");
        $down->assertOk();
        $this->assertStringContainsString('attachment', $down->headers->get('content-disposition'));

        $this->assertStringStartsWith('%PDF-', $prev->getContent());
        $this->assertSame(hash('sha256', $prev->getContent()), hash('sha256', $down->getContent()));
        $this->assertSame(1, TenantContext::withBypass(fn () => \App\Domain\Exports\Models\ExportJob::where('type', 'invoice_pdf')->count()));

        $this->actingAs($u)->get("/app/invoices/{$inv->id}")
            ->assertInertia(fn ($p) => $p
                ->where('documents.pdf.previewUrl', "/invoices/{$inv->id}/pdf/preview")
                ->where('documents.pdf.hasArtifact', true)->etc());
    }
}
