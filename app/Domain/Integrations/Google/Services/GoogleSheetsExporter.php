<?php

namespace App\Domain\Integrations\Google\Services;

use App\Domain\Integrations\Google\GoogleClient;
use App\Domain\Integrations\Google\GoogleScopes;
use App\Domain\Integrations\Models\IntegrationConnection;

/**
 * تصدير بيانات إلى Google Sheets (GOOGLE-01): قائمة مختصرة/حملة/تقرير. ينشئ جدولًا ثمّ
 * يكتب ترويسة + صفوفًا. المُستدعي يمرّر صفوفًا جاهزة (آمنة للجمهور المقصود) — المصدّر لا
 * يقرّر ما يُكشَف؛ يحترم حدود الخصوصيّة المطبَّقة في طبقة النطاق قبله.
 */
class GoogleSheetsExporter
{
    public function __construct(
        private GoogleClient $client,
        private GoogleConnectionService $connections,
    ) {}

    /**
     * ينشئ جدولًا ويكتب الترويسة + الصفوف. يُعيد معرّف الجدول ورابطه.
     *
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string|int|float|null>>  $rows
     * @return array{spreadsheetId:string,url:?string,rows:int}
     */
    public function export(IntegrationConnection $conn, string $title, array $headers, array $rows): array
    {
        $conn = $this->connections->ensureFresh($conn);
        $this->connections->assertCapability($conn, GoogleScopes::CAP_SHEETS);

        $sheet = $this->client->sheetsCreate($conn->access_token, $title);
        $id = $sheet['spreadsheetId'];
        $values = array_merge([$headers], $rows);
        $this->client->sheetsAppendValues($conn->access_token, $id, 'Sheet1', $values);

        return ['spreadsheetId' => $id, 'url' => $sheet['spreadsheetUrl'] ?? null, 'rows' => count($rows)];
    }
}
