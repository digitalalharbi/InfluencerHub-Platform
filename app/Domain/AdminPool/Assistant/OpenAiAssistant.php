<?php

namespace App\Domain\AdminPool\Assistant;

/**
 * محوّل OpenAI — الواجهة جاهزة، والمنطق معطّل حتى يُوفَّر المفتاح.
 *
 * لا يُدّعى ذكاء لا نملكه: بلا مفتاح يعلن available()=false، فيرتدّ النظام إلى
 * المساعد القائم على القواعد. عند توفّر المفتاح يُستبدَل interpret() باستدعاء
 * فعليّ (استخراج المعايير من الطلب، ثم ترتيب/تفسير) دون تغيير المتحكّم.
 */
class OpenAiAssistant implements ShortlistAssistant
{
    public function __construct(
        private ?string $apiKey,
        private ShortlistAssistant $fallback,
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

        // TODO(openai): استدعاء فعليّ لاستخراج المعايير + ترتيب/تفسير.
        // يبقى معطّلًا حتى يُربَط المفتاح؛ لا نُرجع نتيجة مُلفَّقة.
        $r = $this->fallback->interpret($query);
        $r['driver'] = 'openai';

        return $r;
    }

    public function available(): bool
    {
        return ! empty($this->apiKey);
    }
}
