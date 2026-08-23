<?php

namespace App\Domain\Integrations\Adapters;

/** نتيجة تشغيلة مزامنة من محوّل مزوّد — عدّادات ومؤشّر وحدّ معدّل وخطأ آمن. */
final class SyncResult
{
    public function __construct(
        public readonly int $fetched = 0,
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
        public readonly ?string $cursor = null,
        public readonly ?int $rateLimitRemaining = null,
        public readonly bool $partial = false,
    ) {}
}
