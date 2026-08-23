<?php

namespace App\Domain\Exports\Writers;

use App\Domain\Exports\TabularData;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * كاتب CSV مُتدفّق (لا يبني كامل الملف في الذاكرة — مناسب للبيانات الكبيرة).
 * يبدأ بـ BOM UTF-8 حتى يقرأ Excel العربية صحيحًا.
 */
class CsvWriter
{
    public function stream(TabularData $data, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM لعربية Excel
            fputcsv($out, $data->headings());
            foreach ($data->rows as $row) {
                fputcsv($out, $data->rowValues($row));
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');

        return $response;
    }
}
