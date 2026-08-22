<?php

namespace App\Domain\AdminPool\Assistant;

/**
 * مساعد قائم على القواعد — يعمل اليوم بلا مصدر خارجي.
 *
 * يفهم العربية المحكيّة في طلبات الترشيح: المنصّة، المجال، المتابعين، الميزانية،
 * المنطقة، الجنس. ليس ذكاءً اصطناعيًّا ولا يُدّعى ذلك — قواعد شفّافة تُظهر ما
 * فهمته كي يصحّحه المستخدم.
 */
class RuleBasedAssistant implements ShortlistAssistant
{
    private const PLATFORMS = [
        'snapchat' => ['سناب', 'سنابشات', 'snap'],
        'tiktok' => ['تيك توك', 'تيكتوك', 'تك توك', 'tiktok'],
        'linkedin' => ['لينكد', 'linkedin'],
        'x' => ['تويتر', 'إكس', ' اكس', 'twitter'],
    ];

    private const AR_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public function interpret(string $query): array
    {
        $q = $this->normalize($query);
        $criteria = [];
        $understood = [];

        // المنصّة
        foreach (self::PLATFORMS as $key => $words) {
            foreach ($words as $w) {
                if (str_contains($q, $w)) {
                    $criteria['platform'] = $key;
                    $understood[] = "المنصّة: {$w}";
                    break 2;
                }
            }
        }

        // الميزانية: رقم قرب «ريال/ميزانية/سعر/تكلفة/أقل من»
        if (preg_match('/(?:ميزانية|ريال|سعر|تكلفة|اقل من|أقل من|حدود|بحدود)\s*([\d\.]+)\s*(الف|ألف|k|مليون|m)?/u', $q, $mm)
            || preg_match('/([\d\.]+)\s*(الف|ألف|k|مليون|m)?\s*(?:ريال|ر\.?س)/u', $q, $mm)) {
            $criteria['budget_riyals'] = (int) $this->scale($mm[1], $mm[2] ?? '');
            $understood[] = 'الميزانية: ' . number_format($criteria['budget_riyals']) . ' ر.س';
        }

        // المتابعون: رقم قرب «متابع/فولوور/وصول/فوق/أكثر من»
        if (preg_match('/(?:متابع\S*|فولوور|وصول|فوق|أكثر من|اكثر من|جمهور)\s*([\d\.]+)\s*(الف|ألف|k|مليون|m)?/u', $q, $fm)
            || preg_match('/([\d\.]+)\s*(الف|ألف|k|مليون|m)\s*متابع/u', $q, $fm)) {
            $criteria['min_followers'] = (int) $this->scale($fm[1], $fm[2] ?? '');
            $understood[] = 'المتابعون: ≥ ' . number_format($criteria['min_followers']);
        }

        // المجالات: كلمات معروفة في الطلب
        $cats = $this->matchCategories($q);
        if ($cats) {
            $criteria['categories'] = $cats;
            $understood[] = 'المجالات: ' . implode('، ', $cats);
        }

        return ['criteria' => $criteria, 'understood' => $understood, 'driver' => 'rule'];
    }

    public function available(): bool
    {
        return true;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, self::AR_DIGITS);

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    private function scale(string $num, string $unit): float
    {
        $n = (float) $num;
        $unit = mb_strtolower($unit);
        if (in_array($unit, ['الف', 'ألف', 'k'], true)) return $n * 1000;
        if (in_array($unit, ['مليون', 'm'], true)) return $n * 1_000_000;

        return $n;
    }

    /** @return array<int,string> */
    private function matchCategories(string $q): array
    {
        $known = ['عناية', 'مكياج', 'عطور', 'رياضة', 'صحة', 'تغذية', 'اسلوب حياة', 'يوميات',
            'مطاعم', 'قهوة', 'سفر', 'سياحة', 'عائلة', 'كوميدي', 'اخبار', 'اعلامي', 'تقنية',
            'ازياء', 'موضة', 'ديكور', 'تصوير', 'تغطيات', 'مراجعات', 'اطفال'];
        $out = [];
        foreach ($known as $c) {
            if (str_contains($q, $c) && ! in_array($c, $out, true)) {
                $out[] = $c;
            }
        }

        return $out;
    }
}
