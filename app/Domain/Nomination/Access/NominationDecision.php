<?php

namespace App\Domain\Nomination\Access;

/**
 * قرار وصول واحد لميزة ترشيح المؤثرين — ناتج المصدر الموحّد {@see NominationAccess}.
 *
 * يجمع الأبعاد الخمسة في كائن واحد: الإتاحة (available) + السياق (context) + الدور
 * (role/abilities) + البوّابة (portal). `allowed` هو محصّلتها. `reason` يشرح سبب المنع
 * لأغراض 403/التتبّع (لا يُعرض للمستخدم النهائي حرفيًّا).
 */
final class NominationDecision
{
    /**
     * @param  array<string,bool>  $abilities
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly bool $available,
        public readonly string $portal,
        public readonly ?string $role,
        public readonly array $abilities = [],
        public readonly ?string $reason = null,
    ) {}

    public function can(string $ability): bool
    {
        return $this->allowed && ($this->abilities[$ability] ?? false);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
