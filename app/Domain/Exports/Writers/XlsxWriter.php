<?php

namespace App\Domain\Exports\Writers;

use App\Domain\Exports\TabularData;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * كاتب XLSX حقيقي عبر PhpSpreadsheet — ورقة RTL، رأس غامق بخلفية، عرض أعمدة
 * تلقائي، تجميد الرأس، ومرشِّح تلقائي. يُبثّ من ملف مؤقّت (لا يُبقي كلّ شيء
 * في الذاكرة). ليس CSV مُعاد التسمية.
 */
class XlsxWriter
{
    public function stream(TabularData $data, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->setTitle(mb_substr($data->title, 0, 28) ?: 'ورقة');

        $headings = $data->headings();
        $colCount = count($headings);

        // صفّ الرأس
        $sheet->fromArray($headings, null, 'A1');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF6252E5');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // الصفوف
        $r = 2;
        foreach ($data->rows as $row) {
            $sheet->fromArray($data->rowValues($row), null, 'A' . $r);
            $r++;
        }

        // عرض تلقائي + تجميد الرأس + مرشّح
        for ($c = 1; $c <= $colCount; $c++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        if ($r > 2) {
            $sheet->setAutoFilter("A1:{$lastCol}" . ($r - 1));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        return $this->downloadTemp($tmp, $filename . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** يبني كامل XLSX في ذاكرة نصّية (للاختبار/التوليد بلا HTTP). */
    public function toString(TabularData $data): string
    {
        $res = $this->stream($data, 'x');
        ob_start();
        $res->sendContent();
        return (string) ob_get_clean();
    }

    private function downloadTemp(string $path, string $filename, string $mime): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($path) {
            readfile($path);
            @unlink($path);
        });
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
