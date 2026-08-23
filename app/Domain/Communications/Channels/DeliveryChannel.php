<?php

namespace App\Domain\Communications\Channels;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Support\DeliveryOutcome;
use App\Domain\Identity\Models\User;

/**
 * قناة تسليم محايدة للمزوّد. كل قناة تُعلن مفتاحها ومزوّدها وتوفّرها الحقيقي،
 * وتُحوّل الإشعار إلى تسليم فعلي (أو سبب صادق لعدم التسليم).
 */
interface DeliveryChannel
{
    /** مفتاح القناة: in_app|email|whatsapp|sms. */
    public function key(): string;

    /** اسم المزوّد الفعلي (in_app|smtp|whatsapp_cloud|...) — للتدقيق. */
    public function provider(): string;

    /** هل القناة مهيّأة فعلًا للإرسال الآن؟ (بيانات اعتماد/إعداد حقيقية). */
    public function available(): bool;

    /** يستخرج عنوان المستلِم لهذا المستخدم على هذه القناة (أو null إن غاب). */
    public function recipientFor(User $user): ?string;

    /** ينفّذ التسليم فعليًّا. لا يُستدعى إلا حين available() و recipient موجود. */
    public function send(Notification $notification, User $user, string $recipient): DeliveryOutcome;
}
