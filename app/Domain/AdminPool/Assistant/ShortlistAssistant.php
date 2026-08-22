<?php

namespace App\Domain\AdminPool\Assistant;

/**
 * مساعد ترشيح المؤثرين — يحوّل طلبًا بلغة طبيعية إلى معايير بحث.
 *
 * عقد واحد يفصل الواجهة عن التنفيذ: اليوم قائم على قواعد، وغدًا OpenAI،
 * دون تغيير المتحكّم ولا الصفحة. `available()` يقول بصدق هل السائق جاهز.
 */
interface ShortlistAssistant
{
    /**
     * يحلّل طلبًا نصّيًّا إلى معايير.
     *
     * @return array{criteria: array<string,mixed>, understood: array<int,string>, driver: string}
     */
    public function interpret(string $query): array;

    /** هل السائق جاهز للعمل فعلًا؟ (OpenAI بلا مفتاح = false). */
    public function available(): bool;
}
