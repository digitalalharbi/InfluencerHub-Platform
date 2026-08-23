<?php

namespace App\Domain\Communications\Channels;

/**
 * سجلّ قنوات التسليم — الترتيب مقصود (in_app أوّلًا). يُحقَن من مزوّد الخدمة،
 * فيسهُل استبدال قناة بأخرى (مثلًا مزيّفة متاحة) في الاختبارات.
 */
class ChannelRegistry
{
    /** @var array<string,DeliveryChannel> */
    private array $byKey = [];

    /** @param  iterable<DeliveryChannel>  $channels */
    public function __construct(iterable $channels)
    {
        foreach ($channels as $c) {
            $this->byKey[$c->key()] = $c;
        }
    }

    public function get(string $key): ?DeliveryChannel
    {
        return $this->byKey[$key] ?? null;
    }

    /** @return array<string,DeliveryChannel> */
    public function all(): array
    {
        return $this->byKey;
    }

    /** المفاتيح غير الداخلية (email/whatsapp/sms) — القنوات ذات المزوّد الخارجي. */
    public function externalKeys(): array
    {
        return array_values(array_filter(array_keys($this->byKey), fn ($k) => $k !== 'in_app'));
    }
}
