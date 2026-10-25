<?php

namespace App\Domain\Communications\Inbox;

use App\Domain\Communications\WhatsApp\WhatsAppCloudClient;
use App\Domain\Integrations\Models\IntegrationExternalEvent;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * المحادثة الصادرة ثنائيّة الاتّجاه (COMMS-01): إرسال رسالة واتساب (نصّ ضمن نافذة 24 ساعة أو قالب
 * خارجها) وتسجيلها حدثًا حقيقيًّا (message_out) يغذّي الخطّ الزمني، مربوطًا بالمبدع/العميل حين
 * يُطابَق الرقم. عند نجاح أوّل إرسال تُرفع جاهزية قناة واتساب إلى حيّة. لا يُرسِل إن لم تُهيّأ القناة.
 */
class ConversationService
{
    public function __construct(
        private WhatsAppCloudClient $whatsapp,
        private ContactMatcher $matcher,
        private CommsConnectionService $connections,
    ) {}

    /**
     * يرسل رسالة واتساب نصّية حرّة (ضمن نافذة الخدمة) ويسجّلها في الخطّ الزمني.
     *
     * @return array{message_id:string,event_id:?int}
     */
    public function sendWhatsAppText(int $tenantId, string $to, string $body): array
    {
        $messageId = $this->whatsapp->sendText($to, $body);
        $event = $this->record($tenantId, $to, $messageId, $body, ['wa_type' => 'text']);
        $this->connections->markLiveWrite($tenantId, InboundMessageService::PROVIDER);

        return ['message_id' => $messageId, 'event_id' => $event?->id];
    }

    /**
     * يرسل قالب واتساب (للبدء خارج نافذة الجلسة) ويسجّله في الخطّ الزمني.
     *
     * @param  array<int,array<string,mixed>>  $components
     * @return array{message_id:string,event_id:?int}
     */
    public function sendWhatsAppTemplate(int $tenantId, string $to, string $template, string $lang = 'ar', array $components = []): array
    {
        $messageId = $this->whatsapp->sendTemplate($to, $template, $lang, $components);
        $event = $this->record($tenantId, $to, $messageId, "[template:{$template}]", ['wa_type' => 'template', 'template' => $template]);
        $this->connections->markLiveWrite($tenantId, InboundMessageService::PROVIDER);

        return ['message_id' => $messageId, 'event_id' => $event?->id];
    }

    /** @param array<string,mixed> $meta */
    private function record(int $tenantId, string $to, string $messageId, string $body, array $meta): ?IntegrationExternalEvent
    {
        $match = $this->matcher->byPhone($tenantId, $to);

        return TenantContext::withTenant($tenantId, fn () => IntegrationExternalEvent::updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => InboundMessageService::PROVIDER, 'external_id' => $messageId],
            [
                'capability' => InboundMessageService::CAPABILITY, 'kind' => 'message_out', 'direction' => 'outbound',
                'creator_id' => $match['creator_id'], 'client_id' => $match['client_id'],
                'occurred_at' => now(),
                'title' => mb_substr($body, 0, 300), 'summary' => mb_substr($body, 0, 500),
                'meta' => $meta + ['phone' => $to],
            ],
        ));
    }
}
