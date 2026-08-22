<?php

namespace App\Domain\AdminPool\Assistant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * محوّل OpenAI — يستخرج معايير الترشيح من الطلب النصّي عبر Chat Completions (وضع JSON).
 *
 * لا يُدّعى ذكاء لا نملكه:
 *  - بلا مفتاح: available()=false، فيرتدّ النظام إلى المساعد القائم على القواعد.
 *  - عند أي تعذّر (شبكة/حالة خطأ/JSON غير صالح): يرتدّ إلى القواعد ويُوسم السائق
 *    بصدق («rule (openai تعذّر)») ويُسجَّل الخطأ — لا نُرجع نتيجة مُلفَّقة أبدًا.
 *  - عند النجاح لكن دون استخراج مفيد: نستعين بالقواعد كشبكة أمان.
 *
 * شكل المعايير مطابق للمساعد القائم على القواعد كي لا يتغيّر المتحكّم ولا المُطابِق:
 * platform ∈ {snapchat,tiktok,linkedin,x}, categories[], min_followers، budget_riyals.
 */
class OpenAiAssistant implements ShortlistAssistant
{
    /** المنصّات المسموحة وتسمياتها للعرض في «فهمتُ». */
    private const PLATFORM_LABELS = [
        'snapchat' => 'Snap', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'x' => 'X',
    ];

    /** قائمة مجالات معروفة نوجّه بها النموذج (يبقى حرًّا في إضافة غيرها). */
    private const KNOWN_CATEGORIES = [
        'عناية', 'مكياج', 'عطور', 'رياضة', 'صحة', 'تغذية', 'اسلوب حياة', 'يوميات',
        'مطاعم', 'قهوة', 'سفر', 'سياحة', 'عائلة', 'كوميدي', 'اخبار', 'اعلامي', 'تقنية',
        'ازياء', 'موضة', 'ديكور', 'تصوير', 'تغطيات', 'مراجعات', 'اطفال',
    ];

    public function __construct(
        private ?string $apiKey,
        private ShortlistAssistant $fallback,
        private string $model = 'gpt-4o-mini',
        private string $baseUrl = 'https://api.openai.com/v1',
        private int $timeout = 12,
    ) {
    }

    public function interpret(string $query): array
    {
        if (! $this->available()) {
            // بلا مفتاح: نرتدّ إلى القواعد بصدق، ونوسم السائق
            $r = $this->fallback->interpret($query);
            $r['driver'] = 'rule (openai غير مربوط)';

            return $r;
        }

        try {
            $criteria = $this->extract($query);
        } catch (\Throwable $e) {
            // تعذّر الاتصال أو الاستجابة غير صالحة → ارتداد صادق، لا تلفيق
            Log::warning('OpenAiAssistant تعذّر، ارتداد إلى القواعد.', ['error' => $e->getMessage()]);
            $r = $this->fallback->interpret($query);
            $r['driver'] = 'rule (openai تعذّر)';

            return $r;
        }

        // شبكة أمان: إن لم يستخرج النموذج شيئًا مفيدًا، لا نكون أسوأ من القواعد
        if ($criteria === []) {
            $r = $this->fallback->interpret($query);
            $r['driver'] = 'openai + rule';

            return $r;
        }

        return ['criteria' => $criteria, 'understood' => $this->describe($criteria), 'driver' => 'openai'];
    }

    public function available(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * استدعاء فعليّ لـOpenAI واستخراج معايير مُتحقَّقة.
     *
     * @return array<string,mixed>
     *
     * @throws \RuntimeException عند فشل الطلب أو تعذّر قراءة JSON
     */
    private function extract(string $query): array
    {
        $resp = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $query],
                ],
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException("OpenAI HTTP {$resp->status()}: " . mb_substr($resp->body(), 0, 300));
        }

        $content = data_get($resp->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenAI أرجع محتوى فارغًا.');
        }

        // احتياط: أزل أسوار الشيفرة إن وُجدت
        $content = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($content)) ?? $content);
        $parsed = json_decode($content, true);
        if (! is_array($parsed)) {
            throw new \RuntimeException('OpenAI لم يُرجِع JSON صالحًا.');
        }

        return $this->sanitize($parsed);
    }

    /**
     * تنقية وتحقّق: نقبل فقط ما نفهمه ونستخدمه فعلًا في المُطابِق.
     *
     * @param  array<string,mixed>  $p
     * @return array<string,mixed>
     */
    private function sanitize(array $p): array
    {
        $criteria = [];

        $platform = is_string($p['platform'] ?? null) ? strtolower(trim($p['platform'])) : null;
        if ($platform && isset(self::PLATFORM_LABELS[$platform])) {
            $criteria['platform'] = $platform;
        }

        $cats = [];
        foreach ((array) ($p['categories'] ?? []) as $c) {
            $c = is_string($c) ? trim($c) : '';
            if ($c !== '' && ! in_array($c, $cats, true)) {
                $cats[] = $c;
            }
        }
        if ($cats !== []) {
            $criteria['categories'] = $cats;
        }

        $minFollowers = $this->positiveInt($p['min_followers'] ?? null);
        if ($minFollowers !== null) {
            $criteria['min_followers'] = $minFollowers;
        }

        $budget = $this->positiveInt($p['budget_riyals'] ?? null);
        if ($budget !== null) {
            $criteria['budget_riyals'] = $budget;
        }

        return $criteria;
    }

    private function positiveInt(mixed $v): ?int
    {
        if (is_numeric($v) && (int) $v > 0) {
            return (int) $v;
        }

        return null;
    }

    /**
     * وصف عربيّ لِما فُهم — شفافية قابلة للتصحيح، مطابق لأسلوب القواعد.
     *
     * @param  array<string,mixed>  $c
     * @return array<int,string>
     */
    private function describe(array $c): array
    {
        $out = [];
        if (isset($c['platform'])) {
            $out[] = 'المنصّة: ' . (self::PLATFORM_LABELS[$c['platform']] ?? $c['platform']);
        }
        if (! empty($c['budget_riyals'])) {
            $out[] = 'الميزانية: ' . number_format((int) $c['budget_riyals']) . ' ر.س';
        }
        if (! empty($c['min_followers'])) {
            $out[] = 'المتابعون: ≥ ' . number_format((int) $c['min_followers']);
        }
        if (! empty($c['categories'])) {
            $out[] = 'المجالات: ' . implode('، ', $c['categories']);
        }

        return $out;
    }

    private function systemPrompt(): string
    {
        $cats = implode('، ', self::KNOWN_CATEGORIES);

        return <<<PROMPT
            أنت محلّل طلبات لترشيح المؤثرين في السوق السعودي/الخليجي. مهمّتك تحويل طلب
            المستخدم (عربي محكيّ غالبًا) إلى معايير بحث مُهيكلة. أعِد JSON فقط بهذه المفاتيح:

            - "platform": واحدة من [snapchat, tiktok, linkedin, x] أو null إن لم تُذكر منصّة.
              (سناب/سنابشات=snapchat، تيك توك/تيكتوك=tiktok، تويتر/إكس=x، لينكدإن=linkedin)
            - "categories": مصفوفة مجالات بالعربية. استخدم من هذه القائمة عند التطابق: {$cats}.
              أضِف مجالًا آخر بالعربية إن كان واضحًا. [] إن لا مجال.
            - "min_followers": عدد صحيح للحدّ الأدنى للمتابعين، أو null. وسّع الوحدات:
              «ألف»=×1000، «مليون»=×1000000. «فوق 500 ألف» → 500000.
            - "budget_riyals": عدد صحيح للميزانية بالريال، أو null. «أقل من 20 ألف» → 20000.

            قواعد صارمة: أعِد JSON خالصًا بلا أي شرح أو نصّ إضافي. لا تخترع قيمًا غير مذكورة —
            استخدم null و[] لِما لم يُذكر. الأرقام أعداد صحيحة بلا فواصل.
            PROMPT;
    }
}
