<?php

namespace App\Domain\AdminPool\Support;

/**
 * تطبيع بيانات المبدعين المستوردة إلى قاعدة المؤثرين — مصدر الحقيقة الوحيد.
 *
 * حتميّ وقابل للاختبار: الهاتف يبقى نصًّا (لا float)، والمتابعون/اللايكات تُفسَّر
 * من صيغ فعلية (K/M/الف/مليون + أرقام عربية + Notation علمي من Excel)، والمنصّة
 * تُكتشَف من الرابط لا من اسم الورقة. عند الغموض تُترَك القيمة null بدل التخمين.
 *
 * لا يمرّ عبر هذا المطبِّع أيّ حقل مصدر/بنكي/موظّف/شحنات — تلك تُستبعَد قبل الوصول
 * (allow-list في مستخرِج البيانات وحارس الاستيراد).
 */
final class CreatorNormalizer
{
    private const AR_DIGITS = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];

    /** أرقام عربية → لاتينية (يُطبَّق أولًا في كل مسار عددي). */
    public static function latinDigits(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }

        return strtr($s, self::AR_DIGITS);
    }

    /**
     * هاتف سعودي إلى صيغة قانونية «9665XXXXXXXX» (12 رقمًا)، أو null إن لم يكن
     * جوّالًا سعوديًّا صالحًا. لا يخمّن الدولي المشوَّه.
     *
     * يعالج: Notation علمي من Excel (5.32781865E8)، +966 / 966 / 05 / 5،
     * فواصل ومسافات، وأصفارًا بادئة. القيمة تبقى نصًّا دائمًا.
     */
    public static function phone(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $s = self::latinDigits(trim((string) $raw));

        // Notation علمي (Excel حوّل الرقم إلى float): 5.32781865E8 → 532781865
        if (preg_match('/^\d(?:\.\d+)?[eE]\+?\d+$/', $s)) {
            $s = self::expandScientific($s);
        }

        // أرقام فقط
        $d = preg_replace('/\D+/', '', $s);
        if ($d === '' || $d === null) {
            return null;
        }

        // توحيد إلى «5XXXXXXXX» (9 أرقام، تبدأ بـ5)
        if (str_starts_with($d, '00966')) {
            $d = substr($d, 5);
        } elseif (str_starts_with($d, '966')) {
            $d = substr($d, 3);
        } elseif (str_starts_with($d, '05')) {
            $d = substr($d, 1);
        }
        $d = ltrim($d, '0'); // «05XXXXXXXX» بعد إزالة 0 و«0X» احتياطًا

        // جوّال سعودي: 9 أرقام تبدأ بـ5
        if (strlen($d) === 9 && $d[0] === '5') {
            return '966' . $d;
        }

        return null; // ليس جوّالًا سعوديًّا صالحًا — لا نخمّن
    }

    /** عرض بشري للهاتف القانوني: 9665XXXXXXXX → +966 5X XXX XXXX. */
    public static function phoneDisplay(?string $canonical): ?string
    {
        if ($canonical === null || ! preg_match('/^9665\d{8}$/', $canonical)) {
            return $canonical;
        }
        $n = substr($canonical, 3); // 5XXXXXXXX

        return '+966 ' . substr($n, 0, 2) . ' ' . substr($n, 2, 3) . ' ' . substr($n, 5);
    }

    private static function expandScientific(string $s): string
    {
        // 5.32781865E8 → «532781865» بلا فقدان دلالة الأرقام
        [$mant, $exp] = preg_split('/[eE]\+?/', $s);
        $exp = (int) $exp;
        $neg = str_starts_with($mant, '-');
        $mant = ltrim($mant, '+-');
        $dot = strpos($mant, '.');
        $intp = $dot === false ? $mant : substr($mant, 0, $dot);
        $frac = $dot === false ? '' : substr($mant, $dot + 1);
        $digits = $intp . $frac;
        $point = strlen($intp) + $exp;
        if ($point >= strlen($digits)) {
            $out = str_pad($digits, $point, '0');
        } elseif ($point <= 0) {
            $out = '0.' . str_repeat('0', -$point) . $digits;
        } else {
            $out = substr($digits, 0, $point) . '.' . substr($digits, $point);
        }

        // إزالة الأصفار الزائدة بعد الفاصلة فقط (لا من عدد صحيح مثل 501480400)
        if (str_contains($out, '.')) {
            $out = rtrim(rtrim($out, '0'), '.');
        }

        return ($neg ? '-' : '') . $out;
    }

    /**
     * متابعون/لايكات: «2.1M» «113K» «10.3الف» «١٨٢الف» «728K» «3735.0» «44».
     * يُرجع int أو null عند الغموض (لا يُفبرِك رقمًا).
     */
    public static function count(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw)) {
            return $raw >= 0 ? $raw : null;
        }
        if (is_float($raw)) {
            return $raw >= 0 ? (int) round($raw) : null;
        }

        $s = mb_strtolower(trim(self::latinDigits((string) $raw)));
        if ($s === '' || in_array($s, ['followers', 'likes', 'المتابعين', 'اللايكات', '-', '—'], true)) {
            return null;
        }

        if (! preg_match('/^([\d\.,]+)\s*(k|m|الف|ألف|مليون|million|thousand)?/u', $s, $mm)) {
            return null;
        }
        $num = str_replace(',', '', $mm[1]);
        if ($num === '' || ! is_numeric($num)) {
            return null;
        }
        $n = (float) $num;
        $unit = $mm[2] ?? '';
        $mult = match ($unit) {
            'k', 'الف', 'ألف', 'thousand' => 1_000,
            'm', 'مليون', 'million' => 1_000_000,
            default => 1,
        };

        $val = (int) round($n * $mult);

        return $val >= 0 ? $val : null;
    }

    /** يكتشف المنصّة من الرابط؛ يرجع للـhint عند الفشل. المنصّات: snapchat|tiktok|linkedin|x|instagram. */
    public static function platform(?string $url, ?string $hint = null): ?string
    {
        $u = mb_strtolower((string) $url);
        $map = [
            'snapchat.com' => 'snapchat', 'snapchat' => 'snapchat',
            'tiktok.com' => 'tiktok', 'tiktok' => 'tiktok',
            'linkedin.com' => 'linkedin', 'lnkd.in' => 'linkedin',
            'instagram.com' => 'instagram', 'instagr.am' => 'instagram',
            'x.com' => 'x', 'twitter.com' => 'x',
        ];
        foreach ($map as $needle => $platform) {
            if (str_contains($u, $needle)) {
                return $platform;
            }
        }

        $h = mb_strtolower((string) $hint);
        // hint «ugc» ليس منصّة اجتماعية؛ إن لم يُكتشف من الرابط نتركه للمستدعي
        return match ($h) {
            'snapchat', 'tiktok', 'linkedin', 'x', 'instagram' => $h,
            default => null,
        };
    }

    /**
     * يستخرج رابط حساب نظيفًا من نصّ قد يكون مبعثرًا مثل «(14) Muath (@handle)».
     * يزيل معاملات التتبّع غير الضرورية. يرجع null إن لم يجد رابطًا صالحًا.
     */
    public static function accountUrl(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim($raw);
        if ($s === '') {
            return null;
        }

        if (preg_match('~https?://[^\s\)]+~i', $s, $m)) {
            return self::stripTracking($m[0]);
        }
        // «www.tiktok.com/...» بلا بروتوكول
        if (preg_match('~(?:^|\s)((?:www\.)?(?:snapchat|tiktok|instagram|linkedin|x|twitter)\.com/[^\s\)]+)~i', $s, $m)) {
            return self::stripTracking('https://' . ltrim($m[1], '/'));
        }

        return null;
    }

    private static function stripTracking(string $url): string
    {
        $url = rtrim($url, ").,؛; ");
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }
        $q = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $q);
            foreach (array_keys($q) as $k) {
                if (preg_match('/^(utm_|fbclid|gclid|igshid|si|_r|_t|share_|source|ref)/i', $k)) {
                    unset($q[$k]);
                }
            }
        }
        $scheme = $parts['scheme'] ?? 'https';
        $out = $scheme . '://' . strtolower($parts['host']) . ($parts['path'] ?? '');
        if ($q) {
            $out .= '?' . http_build_query($q);
        }

        return rtrim($out, '/');
    }

    /** مفتاح هوية قانوني للمنصّة+الحساب (للمطابقة/إزالة التكرار). */
    public static function identityKey(?string $platform, ?string $accountUrl): ?string
    {
        $p = $platform ? mb_strtolower(trim($platform)) : null;
        $a = $accountUrl ? mb_strtolower(rtrim(trim($accountUrl), '/')) : null;
        if (! $p || ! $a) {
            return null;
        }

        return $p . '|' . $a;
    }

    public static function gender(?string $raw): ?string
    {
        $s = trim(self::latinDigits((string) $raw));
        if (in_array($s, ['ذكر', 'رجل', 'male', 'm'], true)) {
            return 'male';
        }
        if (in_array($s, ['انثى', 'أنثى', 'امرأة', 'female', 'f'], true)) {
            return 'female';
        }

        return null;
    }

    public static function showsFace(?string $raw): ?bool
    {
        $s = trim((string) $raw);
        if (in_array($s, ['نعم', 'yes', 'true', '1', 'y'], true)) {
            return true;
        }
        if (in_array($s, ['لا', 'no', 'false', '0', 'n'], true)) {
            return false;
        }

        return null;
    }

    /** الفئة «A|B|C» — من «تصنيف المشهور». null إن لم تكن حرفًا صالحًا. */
    public static function tier(?string $raw): ?string
    {
        $s = mb_strtoupper(trim((string) $raw));

        return in_array($s, ['A', 'B', 'C'], true) ? $s : null;
    }

    /** تقييم نصّي مطبّع: ممتاز|جيد|سيئ. */
    public static function rating(?string $raw): ?string
    {
        $s = trim((string) $raw);

        return match ($s) {
            'ممتاز' => 'ممتاز',
            'جيد' => 'جيد',
            'سئ', 'سيء', 'سيئ' => 'سيئ',
            default => null,
        };
    }

    /**
     * فئات المحتوى: تنظيف، إزالة الفراغات، توحيد تنويعات إملائية شائعة، إزالة
     * التكرار. لا يُخترَع تصنيف غير مدعوم بالنص.
     *
     * @param  array<int,string|null>  $raw
     * @return array<int,string>
     */
    public static function categories(array $raw): array
    {
        $out = [];
        foreach ($raw as $c) {
            $t = trim((string) self::latinDigits($c ?? ''));
            $t = preg_replace('/\s+/u', ' ', $t);
            if ($t === '' || $t === '0' || is_numeric($t)) {
                continue;
            }
            // تنويعات إملائية شائعة بلا فقدان معنى
            $t = strtr($t, ['يوميات ' => 'يوميات', 'اسلوب حياة' => 'أسلوب حياة', 'مطاعم ' => 'مطاعم']);
            if (! in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }
}
