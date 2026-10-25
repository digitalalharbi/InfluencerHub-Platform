<?php

namespace App\Domain\Integrations\Google;

/**
 * عقد عميل Google محايد عن النقل — كل استدعاءات Google REST تمرّ عبره. التنفيذ الحقيقي
 * (HttpGoogleClient) يحتاج بيانات اعتماد؛ الاختبار/التجريب يستعمل FakeGoogleClient حتميًّا.
 * لا يلمس قاعدة البيانات ولا TenantContext — مجرّد ناقل REST. الدوالّ تُعيد مصفوفات خامّة
 * كما يُرجعها Google (مع تطبيع طفيف)، وترمي عند الفشل.
 */
interface GoogleClient
{
    // ——— OAuth ———

    /** يبادل رمز التفويض برموز وصول. @return array{access_token:string,refresh_token?:string,expires_in?:int,scope?:string,id_token?:string} */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): array;

    /** يحدّث رمز الوصول عبر refresh_token. @return array{access_token:string,expires_in?:int,scope?:string} */
    public function refreshToken(string $refreshToken): array;

    /** يُبطل رمزًا لدى Google. */
    public function revokeToken(string $token): bool;

    /** معلومات الحساب المتّصل (البريد/المعرّف) من رمز وصول. @return array{sub?:string,email?:string,name?:string} */
    public function userInfo(string $accessToken): array;

    // ——— Drive ———

    /** @return array{files:array<int,array<string,mixed>>,nextPageToken?:?string} */
    public function driveListFiles(string $accessToken, ?string $folderId = null, ?string $query = null, ?string $pageToken = null): array;

    /** @return array<string,mixed> */
    public function driveFileMetadata(string $accessToken, string $fileId): array;

    /** @return array<string,mixed> الملف المُنشأ (id/name/webViewLink) */
    public function driveUploadFile(string $accessToken, string $name, string $mimeType, string $contents, ?string $folderId = null): array;

    public function driveDownloadFile(string $accessToken, string $fileId): string;

    /** @return array<int,array<string,mixed>> */
    public function driveListPermissions(string $accessToken, string $fileId): array;

    /** @return array<string,mixed> */
    public function driveCreatePermission(string $accessToken, string $fileId, string $role, string $type, ?string $emailAddress = null): array;

    // ——— Sheets ———

    /** @return array<int,array<int,string>> صفوف القيم (A1 range) */
    public function sheetsGetValues(string $accessToken, string $spreadsheetId, string $range): array;

    /** @return array<string,mixed> معرّفات الجدول المُنشأ */
    public function sheetsCreate(string $accessToken, string $title): array;

    /** @param array<int,array<int,string|int|float|null>> $values */
    public function sheetsAppendValues(string $accessToken, string $spreadsheetId, string $range, array $values): array;

    /** @param array<int,array<int,string|int|float|null>> $values */
    public function sheetsUpdateValues(string $accessToken, string $spreadsheetId, string $range, array $values): array;

    // ——— Gmail ———

    /** @return array{threads:array<int,array<string,mixed>>,nextPageToken?:?string} */
    public function gmailListThreads(string $accessToken, ?string $query = null, ?string $pageToken = null): array;

    /** @return array<string,mixed> */
    public function gmailGetThread(string $accessToken, string $threadId): array;

    /** @return array<string,mixed> */
    public function gmailGetMessage(string $accessToken, string $messageId): array;

    /** @return array<string,mixed> الرسالة المُرسَلة (id/threadId) */
    public function gmailSend(string $accessToken, string $rawMime): array;

    /** @return array{history:array<int,array<string,mixed>>,historyId?:?string,nextPageToken?:?string} */
    public function gmailListHistory(string $accessToken, string $startHistoryId, ?string $pageToken = null): array;

    /** يفعّل مراقبة Push عبر Pub/Sub. @return array{historyId?:?string,expiration?:?string} */
    public function gmailWatch(string $accessToken, string $topicName): array;

    public function gmailStopWatch(string $accessToken): bool;

    /** @return string محتوى المرفق (base64url يُفكّ من قِبَل العميل) */
    public function gmailGetAttachment(string $accessToken, string $messageId, string $attachmentId): string;

    // ——— Calendar ———

    /** @return array<int,array<string,mixed>> */
    public function calendarListCalendars(string $accessToken): array;

    /** @return array{events:array<int,array<string,mixed>>,nextSyncToken?:?string,nextPageToken?:?string} */
    public function calendarListEvents(string $accessToken, string $calendarId, ?string $timeMin = null, ?string $timeMax = null, ?string $syncToken = null, ?string $pageToken = null): array;

    /** @param array<string,mixed> $event @return array<string,mixed> */
    public function calendarInsertEvent(string $accessToken, string $calendarId, array $event): array;

    /** @param array<string,mixed> $event @return array<string,mixed> */
    public function calendarUpdateEvent(string $accessToken, string $calendarId, string $eventId, array $event): array;

    public function calendarDeleteEvent(string $accessToken, string $calendarId, string $eventId): bool;

    /**
     * استعلام الإتاحة (free/busy) لتقويمات ضمن مدى زمني.
     *
     * @param  array<int,string>  $calendarIds
     * @return array<string,array<int,array{start:string,end:string}>> busy لكل تقويم
     */
    public function calendarFreeBusy(string $accessToken, array $calendarIds, string $timeMin, string $timeMax): array;
}
