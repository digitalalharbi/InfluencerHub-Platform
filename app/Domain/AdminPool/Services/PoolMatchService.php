<?php

namespace App\Domain\AdminPool\Services;

use App\Domain\AdminPool\Models\PoolCreator;

/**
 * محرّك ترشيح مبدعي القاعدة — درجة ملاءمة قابلة للتفسير.
 *
 * لا رقم غامض: كل درجة تُفكَّك إلى أبعاد بأسبابها، من بيانات حقيقية موجودة
 * (منصّة، مجالات، متابعون، سعر، تصنيف). ما لا نملكه (تفاعل حيّ، ديموغرافيا)
 * لا يُدّعى.
 *
 * @param array{platform?:string,categories?:array,min_followers?:int,budget_minor?:int} $criteria
 */
class PoolMatchService
{
    /** @return array{score:int,reasons:array<int,string>,flags:array<int,string>} */
    public function score(array $criteria, PoolCreator $c): array
    {
        $score = 0;
        $reasons = [];
        $flags = [];

        // المنصّة (25) — تطابق صريح، لا افتراض حين لا معيار
        if (! empty($criteria['platform'])) {
            if ($c->platform === $criteria['platform']) {
                $score += 25;
                $reasons[] = 'المنصّة مطابقة';
            } else {
                $flags[] = 'منصّة مختلفة';
            }
        }

        // المجالات (30) — تداخل نسبيّ
        $wanted = array_filter((array) ($criteria['categories'] ?? []));
        if ($wanted) {
            $have = array_map('mb_strtolower', $c->categories ?? []);
            $hits = 0;
            foreach ($wanted as $w) {
                foreach ($have as $h) {
                    if (mb_stripos($h, mb_strtolower($w)) !== false) { $hits++; break; }
                }
            }
            if ($hits > 0) {
                $score += (int) min(30, round(30 * $hits / count($wanted)));
                $reasons[] = $hits === count($wanted) ? 'كل المجالات مطابقة' : 'بعض المجالات مطابقة';
            } else {
                $flags[] = 'لا تداخل في المجال';
            }
        }

        // المتابعون (20) — مقابل الحدّ الأدنى المطلوب
        $foll = (int) $c->followers;
        if (! empty($criteria['min_followers'])) {
            if ($foll >= (int) $criteria['min_followers']) {
                $score += 20;
                $reasons[] = 'يبلغ حدّ المتابعين';
            } else {
                $flags[] = 'أقلّ من حدّ المتابعين';
            }
        } elseif ($foll >= 500000) { $score += 15; $reasons[] = 'وصول واسع'; }
        elseif ($foll >= 100000) { $score += 10; $reasons[] = 'وصول جيد'; }

        // ملاءمة الميزانية (15) — سعر البيع ضمن الميزانية
        $sell = $c->price_coverage_minor ?? $c->price_post_minor;
        if (! empty($criteria['budget_minor']) && $sell) {
            if ($sell <= (int) $criteria['budget_minor']) {
                $score += 15;
                $reasons[] = 'ضمن الميزانية';
            } else {
                $flags[] = 'يتجاوز الميزانية';
            }
        }

        // التصنيف (10) — A فوق B فوق C
        $score += match ($c->tier) { 'A' => 10, 'B' => 6, 'C' => 3, default => 0 };
        if ($c->tier) $reasons[] = "تصنيف {$c->tier}";

        // اكتمال بيانات الحجز (10) — سعر وتواصل
        if ($sell && $c->phone) { $score += 10; $reasons[] = 'بيانات حجز مكتملة'; }
        elseif (! $c->phone) { $flags[] = 'بلا وسيلة تواصل'; }

        return ['score' => min(100, $score), 'reasons' => $reasons, 'flags' => $flags];
    }

    public function hasCriteria(array $criteria): bool
    {
        return ! empty($criteria['platform']) || ! empty($criteria['categories'])
            || ! empty($criteria['min_followers']) || ! empty($criteria['budget_minor']);
    }
}
