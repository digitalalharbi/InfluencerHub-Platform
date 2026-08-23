<?php

namespace App\Domain\Communications\WhatsApp;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * محوّل Meta WhatsApp Cloud API (Graph). يُرسل رسائل قالبية (المسموح للبدء
 * خارج نافذة الجلسة). يستخدم Laravel Http فيُزيَّف في الاختبارات.
 * لا يطبع الرمز ولا يُسجّله؛ الأخطاء تُختصَر بأمان.
 */
class WhatsAppCloudClient
{
    private const GRAPH = 'https://graph.facebook.com/v21.0';

    public function configured(): bool
    {
        return filled(config('channels.whatsapp.phone_number_id'))
            && filled(config('channels.whatsapp.access_token'));
    }

    /**
     * يُرسل رسالة قالبية. يعيد معرّف الرسالة من المزوّد.
     *
     * @param  array<int,array<string,mixed>>  $components  مكوّنات القالب (body params…)
     */
    public function sendTemplate(string $to, string $template, string $lang = 'ar', array $components = []): string
    {
        $phoneId = config('channels.whatsapp.phone_number_id');
        $token = config('channels.whatsapp.access_token');

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => array_filter([
                'name' => $template,
                'language' => ['code' => $lang],
                'components' => $components ?: null,
            ]),
        ];

        $res = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post(self::GRAPH . '/' . $phoneId . '/messages', $payload);

        if (! $res->successful()) {
            // رمز خطأ المزوّد دون تفاصيل حسّاسة
            $code = $res->json('error.code') ?? $res->status();
            throw new RuntimeException('whatsapp_send_failed:' . $code);
        }

        $id = $res->json('messages.0.id');
        if (! $id) {
            throw new RuntimeException('whatsapp_no_message_id');
        }

        return (string) $id;
    }
}
