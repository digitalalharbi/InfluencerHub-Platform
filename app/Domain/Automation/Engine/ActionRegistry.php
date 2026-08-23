<?php

namespace App\Domain\Automation\Engine;

use App\Domain\Automation\Actions\AutomationAction;

/** سجلّ إجراءات الأتمتة — نوع → منفّذ. يُحقَن من مزوّد الخدمة. */
class ActionRegistry
{
    /** @var array<string,AutomationAction> */
    private array $byType = [];

    /** @param  iterable<AutomationAction>  $actions */
    public function __construct(iterable $actions = [])
    {
        foreach ($actions as $a) {
            $this->byType[$a->type()] = $a;
        }
    }

    public function get(string $type): ?AutomationAction
    {
        return $this->byType[$type] ?? null;
    }
}
