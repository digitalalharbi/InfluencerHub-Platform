<?php

namespace App\Domain\Nomination\Services;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Creators\Models\Creator;
use Illuminate\Support\Collection;

/**
 * المُطابِق القانونيّ الموحّد للترشيح — مصدر تسجيل واحد لملاءمة مبدع (مستأجر) لحملة،
 * يوحّد ما كان مكرّرًا في ShortlistService::matchScore وCreatorMatchingService.
 *
 * كل الإشارات من **بيانات حقيقيّة مُخزَّنة فقط** (لا تفاعل/ديموغرافيا مُختلقة). المخرَج:
 * score (0–100) + reasons[] (تُعرض للمستخدم) + flags[] (تحذيرات صادقة: منصّة مختلفة/لا فئات/
 * سعر غير محدّد). ما لا يوجد بيانٌ عنه لا يُحسب سلبًا ولا يُختلق (unknown ≠ zero).
 */
final class NominationMatchService
{
    /**
     * @return array{score:int,reasons:array<int,string>,flags:array<int,string>}
     */
    public function score(Campaign $campaign, Creator $creator, ?string $platform = null): array
    {
        $reasons = [];
        $flags = [];
        $score = 0;

        // ملاءمة المنصّة — من مخرَج محدّد (override) أو اتّحاد منصّات مخرجات الحملة.
        $platforms = $platform !== null
            ? collect([$platform])
            : $campaign->deliverables->pluck('platform')->filter()->unique();
        if ($platforms->isNotEmpty()) {
            if ($platforms->contains($creator->primary_platform)) {
                $score += 40;
                $reasons[] = 'متوافق مع المنصّة المطلوبة';
            } else {
                $flags[] = 'منصّة مختلفة عن المطلوب';
            }
        }
        // إن لم تُعرف منصّات الحملة بعد: لا نقاط ولا تحذير (غير معروف، لا يُحتسب).

        // ملاءمة الفئة — قطاع العلامة مقابل تصنيفات محتوى المبدع (إن توفّرا).
        $wanted = collect($campaign->brand?->sector ? [$campaign->brand->sector] : []);
        if ($wanted->isNotEmpty()) {
            $overlap = collect($creator->content_categories ?? [])->intersect($wanted);
            if ($overlap->isNotEmpty()) {
                $score += 20;
                $reasons[] = 'مناسب لفئة الحملة';
            } else {
                $flags[] = 'لا تطابق في الفئة';
            }
        }

        // حجم الجمهور (متابعون حقيقيّون — ليس تفاعلًا).
        $followers = (int) ($creator->followers_count ?? 0);
        if ($followers >= 500000) {
            $score += 25;
            $reasons[] = 'وصول واسع';
        } elseif ($followers >= 100000) {
            $score += 15;
            $reasons[] = 'وصول جيّد';
        } elseif ($followers >= 50000) {
            $score += 10;
            $reasons[] = 'وصول متوسّط';
        } else {
            $score += 5;
        }

        // الموثوقيّة (توثيق مُسجَّل).
        if ($creator->mowthooq_status === 'verified') {
            $score += 20;
            $reasons[] = 'حساب موثّق';
        }

        // اكتمال السعر (بيان مُخزَّن) — غيابه تحذير لا خصم مُختلق.
        if (! empty($creator->rate_per_post_minor)) {
            $score += 10;
            $reasons[] = 'سعر محدّد';
        } else {
            $flags[] = 'بيانات السعر غير مكتملة';
        }

        if ($creator->status === 'active') {
            $score += 5;
        }

        return ['score' => min(100, $score), 'reasons' => $reasons, 'flags' => $flags];
    }

    /**
     * أفضل المبدعين النشِطين لحملة/منصّة — بدرجة وأسباب. يُرجِع مجموعة {creator,score,reasons,flags}.
     *
     * @return Collection<int,array{creator:Creator,score:int,reasons:array,flags:array}>
     */
    public function rankActiveForCampaign(Campaign $campaign, ?string $platform = null, int $limit = 10): Collection
    {
        return Creator::where('status', 'active')->get()
            ->map(fn (Creator $cr) => ['creator' => $cr] + $this->score($campaign, $cr, $platform))
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }
}
