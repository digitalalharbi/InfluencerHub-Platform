<?php

namespace App\Domain\Collaborations\Services;

use App\Domain\Campaigns\Models\CampaignDeliverable;
use App\Domain\Nomination\Services\NominationMatchService;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Collection;

/**
 * اقتراح المبدعين لمخرَج حملة — يفوّض للمُطابِق القانونيّ الموحّد
 * ({@see NominationMatchService}) فلا يبقى محرّك تسجيل
 * موازٍ. شفّاف (score + reasons + flags من بيانات حقيقيّة)، ليس صندوقًا أسود.
 */
class CreatorMatchingService
{
    public function __construct(private NominationMatchService $matcher) {}

    /** يعيد أفضل المبدعين المطابقين لمخرَج حملة مع درجة وأسباب (بمنصّة المخرَج تحديدًا). */
    public function suggestForDeliverable(CampaignDeliverable $d, int $limit = 10): Collection
    {
        return TenantContext::withTenant($d->tenant_id, function () use ($d, $limit) {
            $campaign = $d->campaign;
            if (! $campaign) {
                return collect();
            }

            return $this->matcher->rankActiveForCampaign($campaign, $d->platform, $limit);
        });
    }
}
