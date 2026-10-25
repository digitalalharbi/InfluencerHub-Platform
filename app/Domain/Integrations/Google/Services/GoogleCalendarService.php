<?php

namespace App\Domain\Integrations\Google\Services;

use App\Domain\Integrations\Adapters\SyncResult;
use App\Domain\Integrations\Google\GoogleClient;
use App\Domain\Integrations\Google\GoogleScopes;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\IntegrationExternalEvent;
use App\Domain\Tenancy\Support\TenantContext;
use InvalidArgumentException;

/**
 * Google Calendar (GOOGLE-01): تقاويم، إتاحة (free/busy)، إنشاء/تحديث/حذف أحداث بأنواع
 * معياريّة (اجتماع/إحاطة/تصوير/موعد تسليم/نشر/انتهاء حقوق/استحقاق دفعة)، وتغذية الخطّ الزمني
 * للمبدع بأحداث حقيقيّة. المزامنة التدريجيّة عبر رموز مزامنة Google (syncToken) في meta.
 */
class GoogleCalendarService
{
    public function __construct(
        private GoogleClient $client,
        private GoogleConnectionService $connections,
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function listCalendars(IntegrationConnection $conn): array
    {
        $conn = $this->ready($conn);

        return $this->client->calendarListCalendars($conn->access_token);
    }

    /**
     * إتاحة التقاويم (الأوقات المشغولة) ضمن مدى — أساس اقتراح مواعيد المبدع.
     *
     * @param  array<int,string>  $calendarIds
     * @return array<string,array<int,array{start:string,end:string}>>
     */
    public function availability(IntegrationConnection $conn, array $calendarIds, string $timeMin, string $timeMax): array
    {
        $conn = $this->ready($conn);

        return $this->client->calendarFreeBusy($conn->access_token, $calendarIds, $timeMin, $timeMax);
    }

    /**
     * ينشئ حدثًا بنوع معياريّ ويسجّله في الخطّ الزمني (مربوطًا بمبدع/عميل حين يُمرَّر).
     *
     * @param  array{description?:string,attendees?:array<int,string>,creator_id?:int,client_id?:int,location?:string}  $opts
     * @return array<string,mixed> الحدث المُنشأ (مع id وhtmlLink)
     */
    public function createEvent(IntegrationConnection $conn, string $calendarId, string $kind, string $summary, string $startIso, string $endIso, array $opts = []): array
    {
        $this->assertKind($kind);
        $conn = $this->ready($conn);

        $payload = array_filter([
            'summary' => $summary,
            'description' => $opts['description'] ?? null,
            'location' => $opts['location'] ?? null,
            'start' => ['dateTime' => $startIso],
            'end' => ['dateTime' => $endIso],
            'attendees' => isset($opts['attendees']) ? array_map(fn ($e) => ['email' => $e], $opts['attendees']) : null,
            'extendedProperties' => ['private' => ['ihub_kind' => $kind]],
        ], fn ($v) => $v !== null);

        $event = $this->client->calendarInsertEvent($conn->access_token, $calendarId, $payload);
        $this->recordEvent($conn, (string) $event['id'], $kind, $startIso, $summary, $opts['description'] ?? '', $event['htmlLink'] ?? null, $opts['creator_id'] ?? null, $opts['client_id'] ?? null);

        return $event;
    }

    /** @param array<string,mixed> $patch @return array<string,mixed> */
    public function updateEvent(IntegrationConnection $conn, string $calendarId, string $eventId, array $patch): array
    {
        $conn = $this->ready($conn);
        $event = $this->client->calendarUpdateEvent($conn->access_token, $calendarId, $eventId, $patch);

        // يحدِّث سجلّ الخطّ الزمني إن وُجد (عنوان/وقت).
        TenantContext::withTenant($conn->tenant_id, function () use ($conn, $eventId, $patch, $event) {
            $row = IntegrationExternalEvent::where('tenant_id', $conn->tenant_id)->where('provider', 'google')->where('external_id', $eventId)->first();
            if ($row) {
                $row->update(array_filter([
                    'title' => $patch['summary'] ?? ($event['summary'] ?? null),
                    'occurred_at' => $patch['start']['dateTime'] ?? ($event['start']['dateTime'] ?? null),
                ], fn ($v) => $v !== null));
            }
        });

        return $event;
    }

    public function deleteEvent(IntegrationConnection $conn, string $calendarId, string $eventId): bool
    {
        $conn = $this->ready($conn);
        $ok = $this->client->calendarDeleteEvent($conn->access_token, $calendarId, $eventId);
        if ($ok) {
            TenantContext::withTenant($conn->tenant_id, fn () => IntegrationExternalEvent::where('tenant_id', $conn->tenant_id)
                ->where('provider', 'google')->where('external_id', $eventId)->delete());
        }

        return $ok;
    }

    /** مزامنة تدريجيّة لأحداث التقويم الأساسيّ عبر syncToken المخزَّن في meta. */
    public function syncIncremental(IntegrationConnection $conn, string $calendarId = 'primary'): SyncResult
    {
        $conn = $this->ready($conn);
        $token = $conn->meta['calendar_sync_token'] ?? null;
        $res = $this->client->calendarListEvents($conn->access_token, $calendarId, null, null, $token);
        $fetched = 0;
        foreach ($res['events'] ?? [] as $e) {
            if (empty($e['id'])) {
                continue;
            }
            $kind = $e['extendedProperties']['private']['ihub_kind'] ?? 'event';
            if (! in_array($kind, IntegrationExternalEvent::CALENDAR_KINDS, true)) {
                $kind = 'event';
            }
            $this->recordEvent($conn, (string) $e['id'], $kind, $e['start']['dateTime'] ?? ($e['start']['date'] ?? now()->toIso8601String()),
                $e['summary'] ?? '', $e['description'] ?? '', $e['htmlLink'] ?? null, null, null);
            $fetched++;
        }
        if (! empty($res['nextSyncToken'])) {
            $meta = $conn->meta ?? [];
            $meta['calendar_sync_token'] = $res['nextSyncToken'];
            TenantContext::withTenant($conn->tenant_id, fn () => $conn->update(['meta' => $meta]));
        }

        return new SyncResult(fetched: $fetched);
    }

    private function recordEvent(IntegrationConnection $conn, string $externalId, string $kind, string $occurredAt, string $title, string $summary, ?string $link, ?int $creatorId, ?int $clientId): void
    {
        TenantContext::withTenant($conn->tenant_id, fn () => IntegrationExternalEvent::updateOrCreate(
            ['tenant_id' => $conn->tenant_id, 'provider' => 'google', 'external_id' => $externalId],
            [
                'capability' => GoogleScopes::CAP_CALENDAR, 'kind' => $kind,
                'creator_id' => $creatorId, 'client_id' => $clientId,
                'occurred_at' => $occurredAt, 'title' => mb_substr($title, 0, 300),
                'summary' => mb_substr($summary, 0, 500), 'link' => $link ? mb_substr($link, 0, 500) : null,
            ],
        ));
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, IntegrationExternalEvent::CALENDAR_KINDS, true)) {
            throw new InvalidArgumentException("calendar_invalid_kind:{$kind}");
        }
    }

    private function ready(IntegrationConnection $conn): IntegrationConnection
    {
        $conn = $this->connections->ensureFresh($conn);
        $this->connections->assertCapability($conn, GoogleScopes::CAP_CALENDAR);

        return $conn;
    }
}
