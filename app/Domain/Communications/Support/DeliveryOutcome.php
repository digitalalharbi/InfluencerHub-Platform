<?php

namespace App\Domain\Communications\Support;

/**
 * نتيجة محاولة تسليم عبر قناة — حالة صادقة ومعرّف مزوّد (إن وُجد) وسبب فشل آمن.
 * لا نُخزّن استثناءات خام ولا أسرارًا؛ فقط رمز/رسالة قابلان للتدقيق.
 */
final class DeliveryOutcome
{
    private function __construct(
        public readonly string $status,          // sent|queued|delivered|failed|skipped|waiting_for_credentials
        public readonly ?string $providerMessageId = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $detail = null,
    ) {}

    public static function sent(?string $providerMessageId = null, ?string $detail = null): self
    {
        return new self('sent', $providerMessageId, null, $detail);
    }

    public static function queued(?string $providerMessageId = null, ?string $detail = null): self
    {
        return new self('queued', $providerMessageId, null, $detail);
    }

    public static function skipped(string $detail): self
    {
        return new self('skipped', null, null, $detail);
    }

    public static function waitingForCredentials(string $detail): self
    {
        return new self('waiting_for_credentials', null, null, $detail);
    }

    public static function failed(string $failureCode, ?string $detail = null): self
    {
        return new self('failed', null, $failureCode, $detail);
    }
}
