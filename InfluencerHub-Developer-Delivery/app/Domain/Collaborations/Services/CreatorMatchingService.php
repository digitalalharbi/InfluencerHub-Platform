<?php
namespace App\Domain\Collaborations\Services;
use App\Domain\Campaigns\Models\CampaignDeliverable;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Collection;
/**
 * مطابقة بسيطة وشفّافة: ترشّح المبدعين النشِطين وتُرتّبهم بمعيار قابل للتفسير
 * (تطابق المنصّة + تقاطع الفئات + المتابعون). ليست صندوقًا أسود.
 */
class CreatorMatchingService {
    /** يعيد أفضل المبدعين المطابقين لمخرَج حملة مع درجة وأسباب. */
    public function suggestForDeliverable(CampaignDeliverable $d, int $limit = 10): Collection {
        return TenantContext::withTenant($d->tenant_id, function () use ($d, $limit) {
            $brandCategories = $this->brandCategories($d);
            $creators = Creator::where('status', 'active')->get();
            return $creators->map(function (Creator $cr) use ($d, $brandCategories) {
                $reasons = []; $score = 0;
                if ($d->platform && $cr->primary_platform === $d->platform) { $score += 50; $reasons[] = 'المنصّة مطابقة'; }
                $cats = collect($cr->content_categories ?? []);
                $overlap = $cats->intersect($brandCategories);
                if ($overlap->isNotEmpty()) { $score += 30 * min($overlap->count(), 2); $reasons[] = 'فئات مشتركة: ' . $overlap->implode('، '); }
                if (($cr->followers_count ?? 0) >= 50000) { $score += 10; $reasons[] = 'وصول واسع'; }
                return ['creator' => $cr, 'score' => $score, 'reasons' => $reasons];
            })->sortByDesc('score')->take($limit)->values();
        });
    }
    private function brandCategories(CampaignDeliverable $d): Collection {
        $campaign = $d->campaign; // ضمن السياق المضبوط
        $brand = $campaign?->brand;
        // نستخدم قطاع العلامة/سياستها كفئات تقريبية إن توفّرت، وإلا فارغ
        return collect($brand?->sector ? [$brand->sector] : []);
    }
}
