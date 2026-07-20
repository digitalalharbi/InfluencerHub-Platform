<?php

namespace App\Domain\Brands\Services;

use App\Domain\Brands\Models\BrandSignup;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandSocialAccount;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * هل العلامة المسجِّلة موجودة عندنا أصلًا؟
 *
 * السؤال حسّاس من جهتين متعاكستين:
 *
 * - **الربط الخاطئ كارثة.** ربط تلقائي على تطابق ضعيف يسلّم سجلَّ علامة —
 *   بحملاتها وعقودها وفواتيرها — لمن ليس صاحبها. ولذلك **لا شيء يُربط هنا
 *   تلقائيًّا أبدًا**، مهما بلغت الدرجة. أقصى ما تفعله هذه الخدمة أن تقول:
 *   «يوجد مرشَّح، وهذه درجته» — والقرار لمسار مطالبة يراجعه بشر.
 *
 * - **والكشف كارثة أخرى.** لو ردّ النظام «هذه العلامة مسجّلة» لصار بوّابة
 *   تعداد: يجرّب المهاجم أسماء ونطاقات فيعرف من هم عملاؤنا. ولذلك النتيجة
 *   **لا تُعاد إلى المتصفّح**؛ تُخزَّن في سجلّ التسجيل، ويرى المستخدم رسالة
 *   واحدة لا تتغيّر بتغيّر النتيجة.
 *
 * ## المؤشّرات ودرجاتها
 *
 * الدرجات ليست اعتباطًا — كلٌّ بقدر ما يصعب انتحاله:
 *
 * | المؤشّر | الدرجة | لماذا |
 * |---|---|---|
 * | السجلّ التجاري | 50 | رقم حكومي فريد؛ تطابقه لا يقع صدفةً |
 * | نطاق البريد المؤسسي | 40 | يتطلّب سيطرة على بريد النطاق (وقد تحقّقنا منه) |
 * | نطاق الموقع | 25 | عامّ يعرفه الجميع — يدلّ ولا يُثبت |
 * | الاسم المُطبَّع | 15 | «نايك» و«Nike» و«نايك السعودية» تتشابه كثيرًا |
 * | حساب تواصل | 15 | عامّ كذلك، لكنّ تطابقه مع غيره يقوّي |
 * | الجوال | 10 | يتغيّر ويُعاد تدويره — أضعف المؤشّرات |
 *
 * ## العتبات
 *
 * - **65 فأكثر ⇐ قويّ**: مؤشّر قاطع (سجلّ تجاري أو نطاق بريد) + مؤشّر مساند.
 * - **25 إلى 64 ⇐ محتمَل**: يستحقّ نظر بشر، ولا يكفي وحده.
 * - **أقلّ من 25 ⇐ لا تطابق**: تشابه اسمٍ وحده لا يجعل علامتين واحدة.
 *
 * والاسم وحده **لا يبلغ العتبة أبدًا** (15 < 25) — وهو مقصود: «مطاعم الشرق»
 * اسمٌ قد تحمله عشرات المنشآت غير المرتبطة.
 */
class BrandMatchingService
{
    public const W_COMMERCIAL_REGISTRATION = 50;

    public const W_EMAIL_DOMAIN = 40;

    public const W_WEBSITE_DOMAIN = 25;

    public const W_NAME = 15;

    public const W_SOCIAL = 15;

    public const W_PHONE = 10;

    public const THRESHOLD_STRONG = 65;

    public const THRESHOLD_POSSIBLE = 25;

    /**
     * نطاقات البريد العامّة — وجودها **يُلغي** المؤشّر لا يقوّيه.
     *
     * بدون هذه القائمة يصير كل من يسجّل ببريد Gmail مطابقًا قويًّا لكل من
     * سجّل ببريد Gmail قبله. وهو أخطر عيب يمكن أن يقع في مطابقة بالنطاق.
     */
    public const PUBLIC_EMAIL_DOMAINS = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        'live.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com',
        'mail.com', 'yandex.com', 'zoho.com', 'gmx.com', 'msn.com',
    ];

    /**
     * يبحث عن أفضل مرشَّح عبر **كل المستأجرين**.
     *
     * التجاوز ضروري ومقصود: العلامة المطلوبة قد تكون في مستأجر وكالة أخرى،
     * وهو بالضبط ما نريد كشفه قبل إنشاء نسخة ثانية منها. والتجاوز مقصور على
     * قراءة المطابقة وحدها، ولا تخرج منه أيّ بيانات إلى المستخدم.
     *
     * @return array{decision:string, score:int, brand:?Brand, signals:array<string,mixed>}
     */
    public function match(array $brandData, array $orgData, string $email, ?string $phone = null): array
    {
        $candidates = $this->candidates($brandData, $orgData, $email, $phone);

        $best = ['decision' => BrandSignup::DECISION_NONE, 'score' => 0, 'brand' => null, 'signals' => []];

        foreach ($candidates as $brand) {
            [$score, $signals] = $this->score($brand, $brandData, $orgData, $email, $phone);

            if ($score > $best['score']) {
                $best = ['decision' => $this->decide($score), 'score' => $score, 'brand' => $brand, 'signals' => $signals];
            }
        }

        return $best;
    }

    private function decide(int $score): string
    {
        return match (true) {
            $score >= self::THRESHOLD_STRONG => BrandSignup::DECISION_STRONG,
            $score >= self::THRESHOLD_POSSIBLE => BrandSignup::DECISION_POSSIBLE,
            default => BrandSignup::DECISION_NONE,
        };
    }

    /**
     * المرشَّحون: كل علامة تشترك في **مؤشّر واحد على الأقلّ**.
     *
     * لا نُحضر كل العلامات ثم نُرشّح في PHP — ذلك يقرأ الجدول كاملًا في كل
     * تسجيل. والاستعلام يستعمل الأعمدة المفهرسة المضافة لهذا الغرض.
     *
     * @return Collection<int,Brand>
     */
    private function candidates(array $brandData, array $orgData, string $email, ?string $phone)
    {
        $cr = $this->cleanCr($orgData['commercial_registration'] ?? null);
        $emailDomain = $this->emailDomain($email);
        $siteDomain = $this->domain($brandData['website'] ?? null);
        $name = $this->normalizeName($brandData['name'] ?? '');
        $handles = $this->handles($brandData['social_accounts'] ?? []);

        return TenantContext::withBypass(function () use ($cr, $emailDomain, $siteDomain, $name, $handles) {
            $q = Brand::withoutGlobalScopes()->whereNull('deleted_at');

            $q->where(function ($w) use ($cr, $emailDomain, $siteDomain, $name, $handles) {
                // شرط مستحيل كأساس: بلا مؤشّرات لا مرشَّحين — ولا يُقرأ الجدول كلّه
                $w->whereRaw('1 = 0');

                if ($cr) {
                    $w->orWhere('commercial_registration', $cr);
                }
                if ($emailDomain) {
                    $w->orWhere('email_domain', $emailDomain);
                }
                if ($siteDomain) {
                    $w->orWhere('website_domain', $siteDomain);
                }
                if ($name !== '') {
                    $w->orWhere('normalized_name', $name);
                }

                if ($handles !== []) {
                    $w->orWhereIn('id', BrandSocialAccount::withoutGlobalScopes()
                        ->whereIn('handle', $handles)->select('brand_id'));
                }
            });

            return $q->limit(25)->get();
        });
    }

    /**
     * @return array{0:int, 1:array<string,mixed>}
     */
    private function score(Brand $brand, array $brandData, array $orgData, string $email, ?string $phone): array
    {
        $score = 0;
        $signals = [];

        $cr = $this->cleanCr($orgData['commercial_registration'] ?? null);
        if ($cr && $brand->commercial_registration && $cr === $brand->commercial_registration) {
            $score += self::W_COMMERCIAL_REGISTRATION;
            $signals['commercial_registration'] = true;
        }

        // النطاق العامّ لا يُحتسب — وإلّا صار كل بريد Gmail مطابقًا لغيره
        $emailDomain = $this->emailDomain($email);
        if ($emailDomain && $brand->email_domain && $emailDomain === $brand->email_domain) {
            $score += self::W_EMAIL_DOMAIN;
            $signals['email_domain'] = $emailDomain;
        }

        $siteDomain = $this->domain($brandData['website'] ?? null);
        if ($siteDomain && $brand->website_domain && $siteDomain === $brand->website_domain) {
            $score += self::W_WEBSITE_DOMAIN;
            $signals['website_domain'] = $siteDomain;
        }

        $name = $this->normalizeName($brandData['name'] ?? '');
        if ($name !== '' && $brand->normalized_name && $name === $brand->normalized_name) {
            $score += self::W_NAME;
            $signals['name'] = true;
        }

        $handles = $this->handles($brandData['social_accounts'] ?? []);
        if ($handles !== []) {
            $shared = TenantContext::withBypass(fn () => BrandSocialAccount::withoutGlobalScopes()
                ->where('brand_id', $brand->id)->whereIn('handle', $handles)->pluck('handle')->all());

            if ($shared !== []) {
                $score += self::W_SOCIAL;
                $signals['social'] = array_values($shared);
            }
        }

        // الجوال يُقارَن بما في `contact_information` — ولا عمود مفهرس له،
        // فهو مؤشّر مساند يُحسب على مرشَّح وصل بمؤشّر أقوى.
        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone) {
            $brandPhone = $this->normalizePhone($brand->contact_information['phone'] ?? null);
            if ($brandPhone && $brandPhone === $normalizedPhone) {
                $score += self::W_PHONE;
                $signals['phone'] = true;
            }
        }

        return [$score, $signals];
    }

    // ===== التطبيع — تُستعمل عند الكتابة أيضًا فيتقابل المخزون بالمُقارَن =====

    /** نطاق البريد، أو null إن كان عامًّا (Gmail وأخواته لا تدلّ على مؤسسة). */
    public function emailDomain(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        $domain = Str::lower(trim(Str::afterLast($email, '@')));

        return in_array($domain, self::PUBLIC_EMAIL_DOMAINS, true) ? null : ($domain ?: null);
    }

    /** نطاق الموقع بلا بروتوكول ولا `www` ولا مسار — `https://WWW.Nike.com/ar` ⇐ `nike.com`. */
    public function domain(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $url = trim($url);
        $host = parse_url(str_contains($url, '//') ? $url : "https://{$url}", PHP_URL_HOST) ?: $url;

        // التصغير **قبل** حذف `www.` — وإلّا نجا `WWW.` من التعبير النمطي
        // فصار `WWW.Nike.com` نطاقًا مختلفًا عن `nike.com`، وسقط المؤشّر بصمت.
        $host = preg_replace('/^www\./', '', Str::lower(trim($host, '/')));

        return $host !== '' && str_contains($host, '.') ? $host : null;
    }

    /**
     * الاسم مُطبَّعًا: بلا لواحق شركات، ولا تشكيل، ولا فروق ألف/ياء/تاء مربوطة.
     *
     * بدون تطبيع الحروف العربية تصير «شركة نايك» و«شركه نايك» اسمين مختلفين —
     * والفرق بينهما ضغطة مفتاح لا معنى.
     */
    public function normalizeName(?string $name): string
    {
        if (! $name) {
            return '';
        }

        $s = Str::lower(trim($name));

        // تطبيع الحروف المتقاربة
        $s = preg_replace('/[أإآٱ]/u', 'ا', $s);
        $s = preg_replace('/ى/u', 'ي', $s);
        $s = preg_replace('/ة/u', 'ه', $s);
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);   // تشكيل وتطويل

        // لواحق الكيانات — «نايك» و«شركة نايك المحدودة» علامة واحدة
        $suffixes = ['شركة', 'شركه', 'مؤسسة', 'مؤسسه', 'مجموعة', 'مجموعه', 'المحدودة', 'المحدوده',
            'ذات مسؤولية محدودة', 'للتجارة', 'للتجاره', 'company', 'co', 'corp', 'corporation',
            'inc', 'ltd', 'limited', 'llc', 'group', 'holding', 'trading'];
        foreach ($suffixes as $suffix) {
            $s = preg_replace('/(^|\s)'.preg_quote($suffix, '/').'(\s|$)/u', ' ', $s);
        }

        // ما تبقّى: حروف وأرقام فقط، بلا مسافات
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);

        return $s ?? '';
    }

    /** حسابات التواصل بلا `@` وبحروف صغيرة. */
    private function handles(mixed $accounts): array
    {
        if (! is_array($accounts)) {
            return [];
        }

        return collect($accounts)
            ->map(fn ($a) => is_array($a) ? ($a['handle'] ?? null) : $a)
            ->filter()
            ->map(fn ($h) => Str::lower(ltrim(trim((string) $h), '@')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** الجوال بأرقامه وحدها، بلا صفر بادئ ولا رمز دولة سعودي. */
    public function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $digits = preg_replace('/^(00966|966)/', '', $digits);
        $digits = ltrim($digits, '0');

        return strlen($digits) >= 8 ? $digits : null;
    }

    /** السجلّ التجاري بأرقامه وحدها. */
    public function cleanCr(?string $cr): ?string
    {
        if (! $cr) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cr) ?? '';

        return strlen($digits) >= 6 ? $digits : null;
    }
}
