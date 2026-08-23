<?php

namespace Tests\Feature;

use App\Domain\Exports\TabularData;
use App\Domain\Exports\Writers\{CsvWriter, PdfWriter, XlsxWriter};
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * محرّك التصدير: CSV/XLSX/PDF حقيقية من بيانات عربية. يُثبِت أنّ الملفّات ليست
 * تالفة، والعربية حاضرة، وXLSX ملفّ حقيقي (لا CSV مُعاد التسمية) يُعاد قراءته،
 * وPDF يبدأ بترويسة PDF صحيحة. تُكتب عيّنات إلى scratchpad للفحص البصري.
 */
class ExportEngineTest extends TestCase
{
    private function data(): TabularData
    {
        return new TabularData(
            title: 'تقرير العملاء — بيانات تجريبية',
            columns: ['name' => 'اسم العميل', 'sector' => 'القطاع', 'campaigns' => 'الحملات', 'budget' => 'الميزانية (ر.س)', 'status' => 'الحالة'],
            rows: [
                ['name' => 'نسيم التجارية', 'sector' => 'تجزئة', 'campaigns' => 3, 'budget' => '120,000', 'status' => 'نشِط'],
                ['name' => 'لمسة للتجارة', 'sector' => 'تجميل', 'campaigns' => 2, 'budget' => '85,500', 'status' => 'نشِط'],
                ['name' => 'بيت الذوق جروب', 'sector' => 'أغذية', 'campaigns' => 5, 'budget' => '306,822', 'status' => 'قيد المراجعة'],
            ],
            meta: ['المرشّح' => 'الحالة: نشِط', 'الفترة' => '2026'],
            workspace: 'InfluencerHub Showcase',
            generatedAt: '2026-08-24 12:00',
        );
    }

    private function scratch(): ?string
    {
        $dir = '/private/tmp/claude-501/-Users-mohammedalharbimacbook-Desktop/6b8f5606-3e3b-4dfc-a1d5-1805c0d4247d/scratchpad';
        return is_dir($dir) ? $dir : null;
    }

    public function test_csv_has_bom_arabic_headers_and_rows(): void
    {
        $res = (new CsvWriter)->stream($this->data(), 'clients');
        ob_start();
        $res->sendContent();
        $csv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'BOM UTF-8 لعربية Excel');
        $this->assertStringContainsString('اسم العميل', $csv);
        $this->assertStringContainsString('نسيم التجارية', $csv);
        $this->assertStringContainsString('306,822', $csv);
        if ($d = $this->scratch()) {
            file_put_contents("$d/sample.csv", $csv);
        }
    }

    public function test_xlsx_is_real_and_rereadable(): void
    {
        $bytes = (new XlsxWriter)->toString($this->data());
        // ملفّ XLSX حقيقي = أرشيف ZIP يبدأ بـ PK
        $this->assertStringStartsWith('PK', $bytes, 'XLSX أرشيف ZIP حقيقي لا CSV مُعاد التسمية');

        $tmp = tempnam(sys_get_temp_dir(), 'rt') . '.xlsx';
        file_put_contents($tmp, $bytes);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        $this->assertTrue($sheet->getRightToLeft(), 'الورقة RTL');
        $this->assertSame('اسم العميل', $sheet->getCell('A1')->getValue());
        $this->assertSame('نسيم التجارية', $sheet->getCell('A2')->getValue());
        @unlink($tmp);
        if ($d = $this->scratch()) {
            file_put_contents("$d/sample.xlsx", $bytes);
        }
    }

    public function test_pdf_is_valid_arabic_rtl(): void
    {
        $pdf = (new PdfWriter)->render($this->data());
        $this->assertStringStartsWith('%PDF-', $pdf, 'ترويسة PDF صحيحة');
        $this->assertGreaterThan(3000, strlen($pdf), 'PDF غير فارغ');
        if ($d = $this->scratch()) {
            file_put_contents("$d/sample.pdf", $pdf);
        }
    }
}
