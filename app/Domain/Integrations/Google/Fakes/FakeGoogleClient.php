<?php

namespace App\Domain\Integrations\Google\Fakes;

use App\Domain\Integrations\Google\GoogleClient;

/**
 * عميل Google حتميّ في الذاكرة — للبيئة التجريبية (sandbox) والاختبارات. ينفّذ كامل العقد
 * بلا شبكة ولا بيانات اعتماد: تبادل/تحديث/إبطال الرموز، Drive/Sheets/Gmail/Calendar.
 * يحتفظ بحالة قابلة للفحص (ملفّات/جداول/رسائل/أحداث) كي تختبر الخدمات منطقها الحقيقي.
 * ليس تكاملًا حيًّا — يُعلَن صراحةً أنه تجريبيّ؛ لا يُوهم بأنه Google الحقيقي.
 */
class FakeGoogleClient implements GoogleClient
{
    public string $connectedEmail = 'owner@example.test';

    public string $connectedSub = 'google-sub-1';

    /** @var array<string,array<string,mixed>> */
    public array $files = [];

    /** @var array<string,array<string,array<int,array<int,string>>>> spreadsheetId => range => rows */
    public array $sheets = [];

    /** @var array<string,array<string,mixed>> */
    public array $threads = [];

    /** @var array<int,array<string,mixed>> */
    public array $sentMessages = [];

    /** @var array<string,array<int,array<string,mixed>>> calendarId => events */
    public array $events = [];

    /** @var array<int,array<string,mixed>> */
    public array $calendars = [['id' => 'primary', 'summary' => 'Primary']];

    /** @var array<string,array<int,array{start:string,end:string}>> */
    public array $busy = [];

    public ?string $watchTopic = null;

    private int $seq = 0;

    private function id(string $prefix): string
    {
        return $prefix.'-'.(++$this->seq);
    }

    // ——— OAuth ———

    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        return ['access_token' => 'fake-at-'.$code, 'refresh_token' => 'fake-rt-1', 'expires_in' => 3600,
            'scope' => 'openid email', 'id_token' => 'fake-id-token'];
    }

    public function refreshToken(string $refreshToken): array
    {
        return ['access_token' => 'fake-at-refreshed', 'expires_in' => 3600, 'scope' => 'openid email'];
    }

    public function revokeToken(string $token): bool
    {
        return true;
    }

    public function userInfo(string $accessToken): array
    {
        return ['sub' => $this->connectedSub, 'email' => $this->connectedEmail, 'name' => 'Fake Owner'];
    }

    // ——— Drive ———

    public function driveListFiles(string $accessToken, ?string $folderId = null, ?string $query = null, ?string $pageToken = null): array
    {
        $files = array_values(array_filter($this->files, function ($f) use ($folderId) {
            return $folderId === null || in_array($folderId, $f['parents'] ?? [], true);
        }));

        return ['files' => $files, 'nextPageToken' => null];
    }

    public function driveFileMetadata(string $accessToken, string $fileId): array
    {
        return $this->files[$fileId] ?? throw new \RuntimeException("fake_drive_file_missing:{$fileId}");
    }

    public function driveUploadFile(string $accessToken, string $name, string $mimeType, string $contents, ?string $folderId = null): array
    {
        $id = $this->id('file');
        $this->files[$id] = ['id' => $id, 'name' => $name, 'mimeType' => $mimeType, '_contents' => $contents,
            'parents' => $folderId ? [$folderId] : [], 'webViewLink' => "https://drive.google.test/file/{$id}",
            'modifiedTime' => now()->toIso8601String(), 'size' => (string) strlen($contents),
            'permissions' => []];

        return ['id' => $id, 'name' => $name, 'mimeType' => $mimeType, 'webViewLink' => "https://drive.google.test/file/{$id}"];
    }

    public function driveDownloadFile(string $accessToken, string $fileId): string
    {
        return $this->files[$fileId]['_contents'] ?? throw new \RuntimeException("fake_drive_file_missing:{$fileId}");
    }

    public function driveListPermissions(string $accessToken, string $fileId): array
    {
        return $this->files[$fileId]['permissions'] ?? [];
    }

    public function driveCreatePermission(string $accessToken, string $fileId, string $role, string $type, ?string $emailAddress = null): array
    {
        $perm = ['id' => $this->id('perm'), 'role' => $role, 'type' => $type, 'emailAddress' => $emailAddress];
        $this->files[$fileId]['permissions'][] = $perm;

        return $perm;
    }

    // ——— Sheets ———

    public function sheetsGetValues(string $accessToken, string $spreadsheetId, string $range): array
    {
        return $this->sheets[$spreadsheetId][$range] ?? $this->sheets[$spreadsheetId]['Sheet1'] ?? [];
    }

    public function sheetsCreate(string $accessToken, string $title): array
    {
        $id = $this->id('sheet');
        $this->sheets[$id] = [];

        return ['spreadsheetId' => $id, 'properties' => ['title' => $title],
            'spreadsheetUrl' => "https://docs.google.test/spreadsheets/{$id}"];
    }

    public function sheetsAppendValues(string $accessToken, string $spreadsheetId, string $range, array $values): array
    {
        $this->sheets[$spreadsheetId][$range] = array_merge($this->sheets[$spreadsheetId][$range] ?? [], $values);

        return ['updates' => ['updatedRows' => count($values)]];
    }

    public function sheetsUpdateValues(string $accessToken, string $spreadsheetId, string $range, array $values): array
    {
        $this->sheets[$spreadsheetId][$range] = $values;

        return ['updatedRows' => count($values)];
    }

    // ——— Gmail ———

    public function gmailListThreads(string $accessToken, ?string $query = null, ?string $pageToken = null): array
    {
        return ['threads' => array_values($this->threads), 'nextPageToken' => null];
    }

    public function gmailGetThread(string $accessToken, string $threadId): array
    {
        return $this->threads[$threadId] ?? throw new \RuntimeException("fake_thread_missing:{$threadId}");
    }

    public function gmailGetMessage(string $accessToken, string $messageId): array
    {
        foreach ($this->threads as $thread) {
            foreach ($thread['messages'] ?? [] as $m) {
                if (($m['id'] ?? null) === $messageId) {
                    return $m;
                }
            }
        }
        throw new \RuntimeException("fake_message_missing:{$messageId}");
    }

    public function gmailSend(string $accessToken, string $rawMime): array
    {
        $id = $this->id('msg');
        $this->sentMessages[] = ['id' => $id, 'raw' => $rawMime];

        return ['id' => $id, 'threadId' => $this->id('thread')];
    }

    public function gmailListHistory(string $accessToken, string $startHistoryId, ?string $pageToken = null): array
    {
        return ['history' => [], 'historyId' => (string) ((int) $startHistoryId + 1), 'nextPageToken' => null];
    }

    public function gmailWatch(string $accessToken, string $topicName): array
    {
        $this->watchTopic = $topicName;

        return ['historyId' => '1000', 'expiration' => (string) (now()->addDays(7)->getTimestampMs())];
    }

    public function gmailStopWatch(string $accessToken): bool
    {
        $this->watchTopic = null;

        return true;
    }

    public function gmailGetAttachment(string $accessToken, string $messageId, string $attachmentId): string
    {
        return "fake-attachment-{$messageId}-{$attachmentId}";
    }

    // ——— Calendar ———

    public function calendarListCalendars(string $accessToken): array
    {
        return $this->calendars;
    }

    public function calendarListEvents(string $accessToken, string $calendarId, ?string $timeMin = null, ?string $timeMax = null, ?string $syncToken = null, ?string $pageToken = null): array
    {
        return ['events' => $this->events[$calendarId] ?? [], 'nextSyncToken' => 'synctoken-1', 'nextPageToken' => null];
    }

    public function calendarInsertEvent(string $accessToken, string $calendarId, array $event): array
    {
        $id = $this->id('event');
        $event['id'] = $id;
        $event['htmlLink'] = "https://calendar.google.test/event/{$id}";
        $this->events[$calendarId][] = $event;

        return $event;
    }

    public function calendarUpdateEvent(string $accessToken, string $calendarId, string $eventId, array $event): array
    {
        foreach ($this->events[$calendarId] ?? [] as $i => $e) {
            if (($e['id'] ?? null) === $eventId) {
                $this->events[$calendarId][$i] = array_merge($e, $event, ['id' => $eventId]);

                return $this->events[$calendarId][$i];
            }
        }
        throw new \RuntimeException("fake_event_missing:{$eventId}");
    }

    public function calendarDeleteEvent(string $accessToken, string $calendarId, string $eventId): bool
    {
        foreach ($this->events[$calendarId] ?? [] as $i => $e) {
            if (($e['id'] ?? null) === $eventId) {
                unset($this->events[$calendarId][$i]);
                $this->events[$calendarId] = array_values($this->events[$calendarId]);

                return true;
            }
        }

        return false;
    }

    public function calendarFreeBusy(string $accessToken, array $calendarIds, string $timeMin, string $timeMax): array
    {
        $out = [];
        foreach ($calendarIds as $id) {
            $out[$id] = $this->busy[$id] ?? [];
        }

        return $out;
    }
}
