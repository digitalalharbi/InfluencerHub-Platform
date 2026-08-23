<?php

namespace App\Domain\Integrations\Adapters;

use RuntimeException;

/**
 * سجلّ محوّلات المزوّدين — يُحقَن من مزوّد الخدمة. المزوّدون الحقيقيون يُسجَّلون
 * حين تتوفّر بيانات اعتمادهم؛ ما لا محوّل له لا يُزامَن (لا ادّعاء).
 */
class AdapterRegistry
{
    /** @var array<string,IntegrationAdapter> */
    private array $byProvider = [];

    /** @param  iterable<IntegrationAdapter>  $adapters */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $a) {
            $this->byProvider[$a->provider()] = $a;
        }
    }

    public function register(IntegrationAdapter $adapter): void
    {
        $this->byProvider[$adapter->provider()] = $adapter;
    }

    public function has(string $provider): bool
    {
        return isset($this->byProvider[$provider]);
    }

    public function get(string $provider): IntegrationAdapter
    {
        return $this->byProvider[$provider]
            ?? throw new RuntimeException("لا محوّل مزوّد مسجّل: {$provider}");
    }

    /** @return string[] */
    public function providers(): array
    {
        return array_keys($this->byProvider);
    }
}
