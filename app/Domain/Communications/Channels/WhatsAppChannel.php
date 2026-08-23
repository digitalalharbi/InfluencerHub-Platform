<?php

namespace App\Domain\Communications\Channels;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Support\DeliveryOutcome;
use App\Domain\Communications\WhatsApp\WhatsAppCloudClient;
use App\Domain\Communications\WhatsApp\WhatsAppNumber;
use App\Domain\Identity\Models\User;

/**
 * قناة واتساب (Meta Cloud API). ترسل رسالة قالبية عبر WhatsAppCloudClient.
 * متاحة فقط حين تُفعَّل ووُجدت بيانات اعتماد Cloud API. الرقم يُطبَّع إلى صيغة
 * Cloud API؛ الرقم غير الصالح يُتخطّى بدل إرسال خاطئ.
 */
class WhatsAppChannel implements DeliveryChannel
{
    public function __construct(private WhatsAppCloudClient $client) {}

    public function key(): string { return 'whatsapp'; }

    public function provider(): string { return 'whatsapp_cloud'; }

    public function available(): bool
    {
        return (bool) config('channels.whatsapp.enabled') && $this->client->configured();
    }

    public function recipientFor(User $user): ?string
    {
        return WhatsAppNumber::normalize($user->phone);
    }

    public function send(Notification $notification, User $user, string $recipient): DeliveryOutcome
    {
        $template = config('channels.whatsapp.templates.' . $notification->type)
            ?? config('channels.whatsapp.default_template', 'notification');
        $lang = (string) config('channels.whatsapp.lang', 'ar');

        // مكوّن جسم القالب: العنوان + ملخّص. البنية النهائية تتبع القالب المعتمد.
        $components = [[
            'type' => 'body',
            'parameters' => array_values(array_filter([
                ['type' => 'text', 'text' => \Illuminate\Support\Str::limit($notification->title, 90)],
                $notification->body ? ['type' => 'text', 'text' => \Illuminate\Support\Str::limit($notification->body, 200)] : null,
            ])),
        ]];

        $messageId = $this->client->sendTemplate($recipient, $template, $lang, $components);

        return DeliveryOutcome::queued($messageId, 'قالب: ' . $template);
    }
}
