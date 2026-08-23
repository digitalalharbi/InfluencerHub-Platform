<?php

namespace App\Domain\Integrations\Adapters;

use App\Domain\Integrations\Models\{IntegrationConnection, IntegrationSyncRun};

/**
 * محوّل مزوّد تكامل. كل مزوّد حقيقي (TikTok/Meta/Salla/…) ينفّذ هذا؛ الإطار
 * محايد للمزوّد. المحوّلات الحقيقية محجوبة خارجيًا حتى تتوفّر بيانات الاعتماد،
 * لكن الإطار (تشغيلات/خرائط/جدولة/معالجة أخطاء) مُختبَر بمحوّل مزيّف.
 */
interface IntegrationAdapter
{
    public function provider(): string;

    /** @return array<int,string> القدرات المدعومة (creator_profile|content|metrics|orders|...) */
    public function capabilities(): array;

    /** ينفّذ مزامنة قدرة واحدة. يرمي عند فشل قابل لإعادة المحاولة. */
    public function sync(IntegrationConnection $connection, IntegrationSyncRun $run): SyncResult;
}
