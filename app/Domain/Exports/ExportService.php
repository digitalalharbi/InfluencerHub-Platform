<?php

namespace App\Domain\Exports;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Exports\Writers\{CsvWriter, PdfWriter, XlsxWriter};
use Symfony\Component\HttpFoundation\Response;

/**
 * منسّق التصدير — يختار الكاتب حسب الصيغة، يبثّ الملف بترخيص (لا رابط عام)،
 * ويُدقّق العملية (من صدّر، أيّ نوع، كم صفًّا) — مهمّ لتصدير جهات الاتصال بكثافة.
 */
class ExportService
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function __construct(
        private CsvWriter $csv,
        private XlsxWriter $xlsx,
        private PdfWriter $pdf,
    ) {}

    /**
     * يُنشئ استجابة تنزيل للصيغة المطلوبة.
     *
     * @param  string  $format  csv|xlsx|pdf
     * @param  string  $exportType  وسم للتدقيق (clients|creators|campaign_report…)
     * @param  int  $rowCount  للتدقيق (قد تكون الصفوف مولّدًا لا يُعدّ)
     */
    public function download(TabularData $data, string $format, string $filename, string $exportType, int $rowCount, ?int $tenantId = null, ?int $userId = null): Response
    {
        $format = in_array($format, self::FORMATS, true) ? $format : 'csv';

        AuditLogger::log('export.generated', null, [
            'type' => $exportType, 'format' => $format, 'rows' => $rowCount, 'title' => $data->title,
        ], $tenantId, $userId);

        return match ($format) {
            'xlsx' => $this->xlsx->stream($data, $filename),
            'pdf' => $this->pdf->download($data, $filename),
            default => $this->csv->stream($data, $filename),
        };
    }
}
