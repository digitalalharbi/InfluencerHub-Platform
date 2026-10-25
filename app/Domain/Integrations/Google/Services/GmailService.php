<?php

namespace App\Domain\Integrations\Google\Services;

use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Integrations\Adapters\SyncResult;
use App\Domain\Integrations\Google\GoogleClient;
use App\Domain\Integrations\Google\GoogleScopes;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\IntegrationExternalEvent;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * Gmail (GOOGLE-01): خيوط/رسائل/إرسال/ردّ/مرفقات/مزامنة History، ومطابقة المراسل بمبدع/عميل،
 * وتغذية الخطّ الزمني بأحداث حقيقيّة (IntegrationExternalEvent). لا يدّعي مطابقةً بلا بريد
 * مطابِق فعليّ. المراقبة (watch/Pub-Push) تُخزَّن حالتها في meta الاتّصال.
 */
class GmailService
{
    public function __construct(
        private GoogleClient $client,
        private GoogleConnectionService $connections,
    ) {}

    /** @return array{threads:array<int,array<string,mixed>>,nextPageToken:?string} */
    public function listThreads(IntegrationConnection $conn, ?string $query = null, ?string $pageToken = null): array
    {
        $conn = $this->ready($conn);
        $res = $this->client->gmailListThreads($conn->access_token, $query, $pageToken);

        return ['threads' => $res['threads'] ?? [], 'nextPageToken' => $res['nextPageToken'] ?? null];
    }

    /** @return array<string,mixed> */
    public function getThread(IntegrationConnection $conn, string $threadId): array
    {
        $conn = $this->ready($conn);

        return $this->client->gmailGetThread($conn->access_token, $threadId);
    }

    /** @return array<string,mixed> */
    public function getMessage(IntegrationConnection $conn, string $messageId): array
    {
        $conn = $this->ready($conn);

        return $this->client->gmailGetMessage($conn->access_token, $messageId);
    }

    public function getAttachment(IntegrationConnection $conn, string $messageId, string $attachmentId): string
    {
        $conn = $this->ready($conn);

        return $this->client->gmailGetAttachment($conn->access_token, $messageId, $attachmentId);
    }

    /**
     * يرسل رسالة (أو ردًّا ضمن خيط). يبني MIME بسيطًا، ويسجّل حدثًا خارجًا مربوطًا بالمتلقّي
     * حين يُطابَق. @return array{id:string,threadId:?string}
     */
    public function send(IntegrationConnection $conn, string $to, string $subject, string $body, ?string $threadId = null, ?string $inReplyTo = null): array
    {
        $conn = $this->ready($conn);
        $from = $conn->external_account_name ?: 'me';
        $mime = $this->buildMime($from, $to, $subject, $body, $inReplyTo);
        $sent = $this->client->gmailSend($conn->access_token, $mime);

        $this->recordEvent($conn, (string) $sent['id'], 'email_out', now()->toIso8601String(), $subject, $body, $to, 'outbound');

        return ['id' => (string) $sent['id'], 'threadId' => $sent['threadId'] ?? $threadId];
    }

    public function reply(IntegrationConnection $conn, string $threadId, string $to, string $subject, string $body, ?string $inReplyTo = null): array
    {
        return $this->send($conn, $to, str_starts_with(mb_strtolower($subject), 're:') ? $subject : "Re: {$subject}", $body, $threadId, $inReplyTo);
    }

    /** يفعّل مراقبة Push عبر موضوع Pub/Sub ويخزّن الحالة في meta. */
    public function watch(IntegrationConnection $conn, string $topicName): IntegrationConnection
    {
        $conn = $this->ready($conn);
        $res = $this->client->gmailWatch($conn->access_token, $topicName);
        $this->mergeMeta($conn, [
            'gmail_watch_topic' => $topicName,
            'gmail_history_id' => $res['historyId'] ?? ($conn->meta['gmail_history_id'] ?? null),
            'gmail_watch_expiration' => $res['expiration'] ?? null,
        ]);

        return $conn->fresh();
    }

    public function stopWatch(IntegrationConnection $conn): bool
    {
        $conn = $this->ready($conn);
        $ok = $this->client->gmailStopWatch($conn->access_token);
        $this->mergeMeta($conn, ['gmail_watch_topic' => null, 'gmail_watch_expiration' => null]);

        return $ok;
    }

    /**
     * مزامنة تدريجيّة من آخر historyId مخزَّن. تُستدعى من المحوّل/الطابور. تحدّث historyId
     * وتعيد عدّادات حقيقيّة. بلا historyId سابق ⇒ لا شيء لمزامنته بعد (تُضبط عبر watch).
     */
    public function syncIncremental(IntegrationConnection $conn): SyncResult
    {
        $conn = $this->ready($conn);
        $start = $conn->meta['gmail_history_id'] ?? null;
        if (! $start) {
            return new SyncResult(fetched: 0);
        }
        $res = $this->client->gmailListHistory($conn->access_token, (string) $start);
        $fetched = 0;
        foreach ($res['history'] ?? [] as $h) {
            foreach ($h['messagesAdded'] ?? [] as $added) {
                $msg = $added['message'] ?? [];
                if (! empty($msg['id'])) {
                    $fetched++;
                }
            }
        }
        if (! empty($res['historyId'])) {
            $this->mergeMeta($conn, ['gmail_history_id' => (string) $res['historyId']]);
        }

        return new SyncResult(fetched: $fetched);
    }

    /** @return array{creator_id:?int,client_id:?int} */
    public function matchContact(int $tenantId, string $email): array
    {
        return TenantContext::withTenant($tenantId, function () use ($tenantId, $email) {
            $e = mb_strtolower(trim($email));
            $creator = Creator::where('tenant_id', $tenantId)->whereRaw('lower(email) = ?', [$e])->first();
            $client = Client::where('tenant_id', $tenantId)->whereRaw('lower(email) = ?', [$e])->first();

            return ['creator_id' => $creator?->id, 'client_id' => $client?->id];
        });
    }

    private function recordEvent(IntegrationConnection $conn, string $externalId, string $kind, string $occurredAt, string $title, string $summary, string $counterpartyEmail, string $direction): void
    {
        $match = $this->matchContact($conn->tenant_id, $counterpartyEmail);
        TenantContext::withTenant($conn->tenant_id, fn () => IntegrationExternalEvent::updateOrCreate(
            ['tenant_id' => $conn->tenant_id, 'provider' => 'google', 'external_id' => $externalId],
            [
                'capability' => GoogleScopes::CAP_GMAIL, 'kind' => $kind,
                'creator_id' => $match['creator_id'], 'client_id' => $match['client_id'],
                'occurred_at' => $occurredAt, 'title' => mb_substr($title, 0, 300),
                'summary' => mb_substr($summary, 0, 500), 'direction' => $direction,
                'meta' => ['email' => $counterpartyEmail],
            ],
        ));
    }

    private function buildMime(string $from, string $to, string $subject, string $body, ?string $inReplyTo): string
    {
        $headers = [
            "From: {$from}",
            "To: {$to}",
            'Subject: '.$this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($inReplyTo) {
            $headers[] = "In-Reply-To: {$inReplyTo}";
            $headers[] = "References: {$inReplyTo}";
        }

        return implode("\r\n", $headers)."\r\n\r\n".$body;
    }

    private function encodeHeader(string $s): string
    {
        return preg_match('/[\x80-\xff]/', $s) ? '=?UTF-8?B?'.base64_encode($s).'?=' : $s;
    }

    /** @param array<string,mixed> $patch */
    private function mergeMeta(IntegrationConnection $conn, array $patch): void
    {
        TenantContext::withTenant($conn->tenant_id, function () use ($conn, $patch) {
            $meta = $conn->meta ?? [];
            foreach ($patch as $k => $v) {
                if ($v === null) {
                    unset($meta[$k]);
                } else {
                    $meta[$k] = $v;
                }
            }
            $conn->update(['meta' => $meta]);
        });
    }

    private function ready(IntegrationConnection $conn): IntegrationConnection
    {
        $conn = $this->connections->ensureFresh($conn);
        $this->connections->assertCapability($conn, GoogleScopes::CAP_GMAIL);

        return $conn;
    }
}
