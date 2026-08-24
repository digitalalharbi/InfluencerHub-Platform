<?php

namespace App\Domain\Exports\Writers;

use App\Domain\Exports\TabularData;
use App\Support\Brand;
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

        $this->meta($mpdf, $data->title);
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

    /** يولّد PDF عربي RTL من قالب Blade مخصّص (فاتورة/تقرير حملة/ترشيح…). */
    public function fromView(string $view, array $data, string $orientation = 'P', ?string $title = null): string
    {
        $html = view($view, $data)->render();
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'orientation' => $orientation,
            'directionality' => 'rtl', 'default_font' => 'dejavusans',
            'margin_top' => 14, 'margin_bottom' => 16, 'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $this->meta($mpdf, $title ?? ($data['title'] ?? Brand::name() . ' — مستند'));
        $mpdf->SetHTMLFooter($this->footer());
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    public function downloadView(string $view, array $data, string $filename, string $orientation = 'P'): Response
    {
        return new Response($this->fromView($view, $data, $orientation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
    }

    private function header(TabularData $data): string
    {
        $ws = e($data->workspace ?? Brand::name());
        $title = e($data->title);

        return '<table width="100%" style="border-bottom:1px solid #ddd;font-family:dejavusans;font-size:9pt;color:#555;">
            <tr>
              <td style="text-align:right;font-weight:bold;color:#6252e5;">' . $ws . '</td>
              <td style="text-align:left;">' . $title . '</td>
            </tr></table>';
    }

    private function footer(): string
    {
        // هوية المنصّة في التذييل (المستأجر يبقى صاحب المستند في الترويسة/المتن).
        return '<table width="100%" style="border-top:1px solid #ddd;font-family:dejavusans;font-size:8pt;color:#888;">
            <tr>
              <td style="text-align:right;">' . e(Brand::documentFooter()) . '</td>
              <td style="text-align:left;">صفحة {PAGENO} من {nbpg}</td>
            </tr></table>';
    }

    /** بيانات PDF الوصفية — هوية InfluencerHub لا اسم إطار العمل/المكتبة. */
    private function meta(Mpdf $mpdf, string $title): void
    {
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor(Brand::name());
        $mpdf->SetCreator(Brand::name());
        $mpdf->SetSubject('مستند ' . Brand::name() . ' · ' . Brand::domain());
    }
}
