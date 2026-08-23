<?php

namespace App\Domain\Exports\Writers;

use App\Domain\Exports\TabularData;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * كاتب PDF عربي RTL عبر mpdf — يدعم العربية والاتجاه من اليمين وجداول وترويسة/تذييل
 * وترقيم صفحات. الخطّ الافتراضي (dejavusans) يغطّي العربية. القالب من Blade
 * فيَسهُل تنسيقه. لا روابط عامة — يُبثّ بترخيص.
 */
class PdfWriter
{
    /** يولّد بايتات PDF (لتوليد/تنزيل/اختبار بلا HTTP). */
    public function render(TabularData $data): string
    {
        $html = view('exports.table', ['data' => $data])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => count($data->columns) > 6 ? 'L' : 'P',
            'directionality' => 'rtl',
            'default_font' => 'dejavusans',
            'margin_top' => 30,
            'margin_bottom' => 20,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $mpdf->SetHTMLHeader($this->header($data));
        $mpdf->SetHTMLFooter($this->footer());
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    public function download(TabularData $data, string $filename): Response
    {
        $pdf = $this->render($data);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
    }

    private function header(TabularData $data): string
    {
        $ws = e($data->workspace ?? 'إنفلونسر هَب');
        $title = e($data->title);

        return '<table width="100%" style="border-bottom:1px solid #ddd;font-family:dejavusans;font-size:9pt;color:#555;">
            <tr>
              <td style="text-align:right;font-weight:bold;color:#6252e5;">' . $ws . '</td>
              <td style="text-align:left;">' . $title . '</td>
            </tr></table>';
    }

    private function footer(): string
    {
        return '<table width="100%" style="border-top:1px solid #ddd;font-family:dejavusans;font-size:8pt;color:#888;">
            <tr>
              <td style="text-align:right;">إنفلونسر هَب — منصّة إدارة عمليات المؤثرين</td>
              <td style="text-align:left;">صفحة {PAGENO} من {nbpg}</td>
            </tr></table>';
    }
}
