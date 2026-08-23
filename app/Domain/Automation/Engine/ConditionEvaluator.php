<?php

namespace App\Domain\Automation\Engine;

/**
 * مُقيّم شروط الأتمتة — منطق حتمي (لا ذكاء اصطناعي في قرار التنفيذ).
 * شرط = {field, op, value}. تُقيَّم كلها بـAND. الحقول من سياق المحفّز.
 */
class ConditionEvaluator
{
    /** @param  array<int,array{field:string,op:string,value:mixed}>|null  $conditions */
    public function passes(?array $conditions, array $context): bool
    {
        foreach ($conditions ?? [] as $c) {
            if (! $this->one($context[$c['field']] ?? null, $c['op'] ?? 'eq', $c['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function one(mixed $actual, string $op, mixed $expected): bool
    {
        return match ($op) {
            'eq' => $actual == $expected,
            'neq' => $actual != $expected,
            'gt' => is_numeric($actual) && $actual > $expected,
            'gte' => is_numeric($actual) && $actual >= $expected,
            'lt' => is_numeric($actual) && $actual < $expected,
            'lte' => is_numeric($actual) && $actual <= $expected,
            'in' => is_array($expected) && in_array($actual, $expected, false),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, false),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'present' => $actual !== null && $actual !== '',
            'absent' => $actual === null || $actual === '',
            default => false,
        };
    }
}
