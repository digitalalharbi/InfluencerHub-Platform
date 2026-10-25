<?php

namespace App\Domain\Integrations\Google;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * تنفيذ Google REST الحقيقي عبر Laravel Http. يحتاج بيانات اعتماد (config/services.google):
 * بدونها يرمي GoogleNotConfiguredException فورًا — لا يختلق نجاحًا ولا بيانات. مُسجَّل في الحاوية
 * كتنفيذ افتراضي لـGoogleClient؛ يُستبدَل بـFakeGoogleClient في الاختبار/التجريب. التشغيل الحيّ
 * محجوب حتى تتوفّر الاعتمادات (BLOCKED_EXTERNAL_CREDENTIALS).
 */
class HttpGoogleClient implements GoogleClient
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URI = 'https://oauth2.googleapis.com/revoke';

    private const USERINFO_URI = 'https://openidconnect.googleapis.com/v1/userinfo';

    private const DRIVE = 'https://www.googleapis.com/drive/v3';

    private const DRIVE_UPLOAD = 'https://www.googleapis.com/upload/drive/v3';

    private const SHEETS = 'https://sheets.googleapis.com/v4/spreadsheets';

    private const GMAIL = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const CALENDAR = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private ?string $clientId = null,
        private ?string $clientSecret = null,
    ) {
        $this->clientId ??= config('services.google.client_id');
        $this->clientSecret ??= config('services.google.client_secret');
    }

    public function configured(): bool
    {
        return ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw GoogleNotConfiguredException::make();
        }
    }

    // ——— OAuth ———

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        $this->assertConfigured();

        return $this->postForm(self::TOKEN_URI, [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier,
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        $this->assertConfigured();

        return $this->postForm(self::TOKEN_URI, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);
    }

    public function revokeToken(string $token): bool
    {
        $this->assertConfigured();

        return Http::asForm()->post(self::REVOKE_URI, ['token' => $token])->successful();
    }

    public function userInfo(string $accessToken): array
    {
        $this->assertConfigured();

        return Http::withToken($accessToken)->get(self::USERINFO_URI)->throw()->json();
    }

    // ——— Drive ———

    public function driveListFiles(string $accessToken, ?string $folderId = null, ?string $query = null, ?string $pageToken = null): array
    {
        $q = array_filter([$folderId ? "'{$folderId}' in parents" : null, $query]);

        return Http::withToken($accessToken)->get(self::DRIVE.'/files', array_filter([
            'q' => $q ? implode(' and ', $q) : null,
            'pageToken' => $pageToken,
            'fields' => 'nextPageToken, files(id,name,mimeType,modifiedTime,size,webViewLink,parents)',
            'pageSize' => 100,
        ]))->throw()->json();
    }

    public function driveFileMetadata(string $accessToken, string $fileId): array
    {
        return Http::withToken($accessToken)->get(self::DRIVE."/files/{$fileId}", [
            'fields' => 'id,name,mimeType,modifiedTime,size,webViewLink,parents,owners',
        ])->throw()->json();
    }

    public function driveUploadFile(string $accessToken, string $name, string $mimeType, string $contents, ?string $folderId = null): array
    {
        $meta = array_filter(['name' => $name, 'parents' => $folderId ? [$folderId] : null]);
        $boundary = 'ihub'.bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
            .json_encode($meta)."\r\n--{$boundary}\r\nContent-Type: {$mimeType}\r\n\r\n"
            .$contents."\r\n--{$boundary}--";

        return Http::withToken($accessToken)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post(self::DRIVE_UPLOAD.'/files?uploadType=multipart&fields=id,name,webViewLink,mimeType')
            ->throw()->json();
    }

    public function driveDownloadFile(string $accessToken, string $fileId): string
    {
        return Http::withToken($accessToken)->get(self::DRIVE."/files/{$fileId}", ['alt' => 'media'])->throw()->body();
    }

    public function driveListPermissions(string $accessToken, string $fileId): array
    {
        return Http::withToken($accessToken)->get(self::DRIVE."/files/{$fileId}/permissions", [
            'fields' => 'permissions(id,type,role,emailAddress)',
        ])->throw()->json('permissions', []);
    }

    public function driveCreatePermission(string $accessToken, string $fileId, string $role, string $type, ?string $emailAddress = null): array
    {
        return Http::withToken($accessToken)->post(self::DRIVE."/files/{$fileId}/permissions", array_filter([
            'role' => $role, 'type' => $type, 'emailAddress' => $emailAddress,
        ]))->throw()->json();
    }

    // ——— Sheets ———

    public function sheetsGetValues(string $accessToken, string $spreadsheetId, string $range): array
    {
        return Http::withToken($accessToken)->get(self::SHEETS."/{$spreadsheetId}/values/".rawurlencode($range))
            ->throw()->json('values', []);
    }

    public function sheetsCreate(string $accessToken, string $title): array
    {
        return Http::withToken($accessToken)->post(self::SHEETS, ['properties' => ['title' => $title]])->throw()->json();
    }

    public function sheetsAppendValues(string $accessToken, string $spreadsheetId, string $range, array $values): array
    {
        return Http::withToken($accessToken)
            ->post(self::SHEETS."/{$spreadsheetId}/values/".rawurlencode($range).':append?valueInputOption=RAW', ['values' => $values])
            ->throw()->json();
    }

    public function sheetsUpdateValues(string $accessToken, string $spreadsheetId, string $range, array $values): array
    {
        return Http::withToken($accessToken)
            ->put(self::SHEETS."/{$spreadsheetId}/values/".rawurlencode($range).'?valueInputOption=RAW', ['values' => $values])
            ->throw()->json();
    }

    // ——— Gmail ———

    public function gmailListThreads(string $accessToken, ?string $query = null, ?string $pageToken = null): array
    {
        return Http::withToken($accessToken)->get(self::GMAIL.'/threads', array_filter([
            'q' => $query, 'pageToken' => $pageToken,
        ]))->throw()->json();
    }

    public function gmailGetThread(string $accessToken, string $threadId): array
    {
        return Http::withToken($accessToken)->get(self::GMAIL."/threads/{$threadId}", ['format' => 'full'])->throw()->json();
    }

    public function gmailGetMessage(string $accessToken, string $messageId): array
    {
        return Http::withToken($accessToken)->get(self::GMAIL."/messages/{$messageId}", ['format' => 'full'])->throw()->json();
    }

    public function gmailSend(string $accessToken, string $rawMime): array
    {
        return Http::withToken($accessToken)->post(self::GMAIL.'/messages/send', [
            'raw' => rtrim(strtr(base64_encode($rawMime), '+/', '-_'), '='),
        ])->throw()->json();
    }

    public function gmailListHistory(string $accessToken, string $startHistoryId, ?string $pageToken = null): array
    {
        return Http::withToken($accessToken)->get(self::GMAIL.'/history', array_filter([
            'startHistoryId' => $startHistoryId, 'pageToken' => $pageToken,
        ]))->throw()->json();
    }

    public function gmailWatch(string $accessToken, string $topicName): array
    {
        return Http::withToken($accessToken)->post(self::GMAIL.'/watch', ['topicName' => $topicName])->throw()->json();
    }

    public function gmailStopWatch(string $accessToken): bool
    {
        return Http::withToken($accessToken)->post(self::GMAIL.'/stop')->successful();
    }

    public function gmailGetAttachment(string $accessToken, string $messageId, string $attachmentId): string
    {
        $data = Http::withToken($accessToken)->get(self::GMAIL."/messages/{$messageId}/attachments/{$attachmentId}")
            ->throw()->json('data', '');

        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    // ——— Calendar ———

    public function calendarListCalendars(string $accessToken): array
    {
        return Http::withToken($accessToken)->get(self::CALENDAR.'/users/me/calendarList')->throw()->json('items', []);
    }

    public function calendarListEvents(string $accessToken, string $calendarId, ?string $timeMin = null, ?string $timeMax = null, ?string $syncToken = null, ?string $pageToken = null): array
    {
        $res = Http::withToken($accessToken)->get(self::CALENDAR.'/calendars/'.rawurlencode($calendarId).'/events', array_filter([
            'timeMin' => $timeMin, 'timeMax' => $timeMax, 'syncToken' => $syncToken, 'pageToken' => $pageToken, 'singleEvents' => 'true',
        ]))->throw()->json();

        return ['events' => $res['items'] ?? [], 'nextSyncToken' => $res['nextSyncToken'] ?? null, 'nextPageToken' => $res['nextPageToken'] ?? null];
    }

    public function calendarInsertEvent(string $accessToken, string $calendarId, array $event): array
    {
        return Http::withToken($accessToken)->post(self::CALENDAR.'/calendars/'.rawurlencode($calendarId).'/events', $event)->throw()->json();
    }

    public function calendarUpdateEvent(string $accessToken, string $calendarId, string $eventId, array $event): array
    {
        return Http::withToken($accessToken)->put(self::CALENDAR.'/calendars/'.rawurlencode($calendarId)."/events/{$eventId}", $event)->throw()->json();
    }

    public function calendarDeleteEvent(string $accessToken, string $calendarId, string $eventId): bool
    {
        return Http::withToken($accessToken)->delete(self::CALENDAR.'/calendars/'.rawurlencode($calendarId)."/events/{$eventId}")->successful();
    }

    public function calendarFreeBusy(string $accessToken, array $calendarIds, string $timeMin, string $timeMax): array
    {
        $res = Http::withToken($accessToken)->post(self::CALENDAR.'/freeBusy', [
            'timeMin' => $timeMin, 'timeMax' => $timeMax,
            'items' => array_map(fn ($id) => ['id' => $id], $calendarIds),
        ])->throw()->json('calendars', []);

        $out = [];
        foreach ($res as $id => $cal) {
            $out[$id] = array_map(fn ($b) => ['start' => $b['start'], 'end' => $b['end']], $cal['busy'] ?? []);
        }

        return $out;
    }

    /** @param array<string,mixed> $form @return array<string,mixed> */
    private function postForm(string $uri, array $form): array
    {
        $res = Http::asForm()->post($uri, $form);
        if (! $res->successful()) {
            throw new RuntimeException('google_token_error: '.$res->status());
        }

        return $res->json();
    }
}
