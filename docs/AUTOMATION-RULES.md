# Automation Rules

Event-driven automation. A workflow `fire($trigger, $context, $tenantId)` matches enabled `AutomationRule`s (by priority), evaluates their **deterministic** conditions, and runs their actions. Every run is recorded (`automation_runs`). Automation is intelligence-through-workflow; **no LLM decides execution, and no action approves money or contracts**.

## Model
- `AutomationRule` (tenant, key, name, `trigger`, `conditions` JSON, `actions` JSON, enabled, priority, is_system).
- `AutomationRun` (rule, trigger, status `matched|skipped|executed|failed`, context, result, error).

## Engine (`app/Domain/Automation/Engine`)
- `ConditionEvaluator` — ops: `eq neq gt gte lt lte in not_in contains present absent`; all conditions AND’d; empty = pass.
- `ActionRegistry` — action type → handler (container singleton).
- `AutomationEngine::fire()` — tenant-scoped; per matched rule runs each action; an action exception is logged and recorded, **never** breaking sibling actions, other rules, or the originating workflow.

## Actions (extensible)
| Type | Handler | Effect |
|---|---|---|
| `notify` | `NotifyAction` | in-app + (enabled) email/WhatsApp via the delivery layer; title/body/action_url templated from context `{{key}}` |
| _(follow-up)_ `create_task`, `escalate`, `reminder` | — | create ServiceRequest / notify managers / schedule reminder |

## Triggers (vocabulary — wire from workflows as follow-up)
`request_created` · `request_overdue` · `campaign_stage_changed` · `campaign_blocked` · `client_decision_pending` · `creator_invited` · `creator_declined` · `content_submitted` · `content_revision_requested` · `content_approved` · `publication_due` · `publication_overdue` · `invoice_issued` · `invoice_overdue` · `payment_received` · `payout_pending` · `integration_degraded` · `sync_failed`.

## Verification
`AutomationEngineTest` (6): evaluator operators; matching rule executes the notify action (templated title/url, run `executed`); unmet condition skipped + recorded; disabled rule doesn't fire; a bad action doesn't break the valid one; tenant isolation. **INTERNAL_VERIFIED.**

Default professional rules (request→assign/notify, shortlist→client reminder, content submitted→reviewer, publish reminders, invoice-overdue) are seeded/wired as a follow-up unit that connects each real workflow to `fire()`.
