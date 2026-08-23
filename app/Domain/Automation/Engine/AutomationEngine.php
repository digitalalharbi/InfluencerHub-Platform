<?php

namespace App\Domain\Automation\Engine;

use App\Domain\Automation\Models\{AutomationRule, AutomationRun};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Log;

/**
 * محرّك الأتمتة الحدثي: يُطلَق محفّز بسياق، فتُطابَق القواعد المُفعّلة (بالأولوية)،
 * تُقيَّم شروطها، وتُنفَّذ إجراءاتها. كل تشغيلة تُسجَّل. فشل إجراء لا يُفشِل البقية
 * ولا سير العمل الأصلي.
 */
class AutomationEngine
{
    public function __construct(
        private ConditionEvaluator $evaluator,
        private ActionRegistry $actions,
    ) {}

    /** يُطلق محفّزًا. يعيد عدد القواعد المُنفَّذة. آمن للاستدعاء من أيّ سير عمل. */
    public function fire(string $trigger, array $context, int $tenantId): int
    {
        return TenantContext::withTenant($tenantId, function () use ($trigger, $context, $tenantId) {
            $rules = AutomationRule::where('trigger', $trigger)->where('enabled', true)
                ->orderBy('priority')->get();

            $executed = 0;
            foreach ($rules as $rule) {
                if (! $this->evaluator->passes($rule->conditions, $context)) {
                    $this->record($tenantId, $rule->id, $trigger, 'skipped', $context, null);
                    continue;
                }

                $results = [];
                $failed = false;
                foreach ($rule->actions ?? [] as $action) {
                    $handler = $this->actions->get($action['type'] ?? '');
                    if (! $handler) {
                        $results[] = ['type' => $action['type'] ?? '?', 'error' => 'no handler'];
                        continue;
                    }
                    try {
                        $results[] = ['type' => $handler->type()] + $handler->execute($action, $context, $tenantId);
                    } catch (\Throwable $e) {
                        $failed = true;
                        Log::warning('automation action failed', ['rule' => $rule->id, 'type' => $action['type'] ?? '?', 'error' => $e->getMessage()]);
                        $results[] = ['type' => $action['type'] ?? '?', 'error' => 'exception'];
                    }
                }

                $this->record($tenantId, $rule->id, $trigger, $failed ? 'failed' : 'executed', $context, $results);
                $executed++;
            }

            return $executed;
        });
    }

    private function record(int $tenantId, ?int $ruleId, string $trigger, string $status, array $context, ?array $result): void
    {
        AutomationRun::create([
            'tenant_id' => $tenantId, 'rule_id' => $ruleId, 'trigger' => $trigger,
            'status' => $status, 'context' => $context ?: null, 'result' => $result,
            'created_at' => now(),
        ]);
    }
}
