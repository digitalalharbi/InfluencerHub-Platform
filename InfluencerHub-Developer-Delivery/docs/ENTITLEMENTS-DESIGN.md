# Entitlements Design (Phase 2)

`EntitlementService::resolve($org, $featureKey)` → `['allowed'=>bool,'unlimited'=>bool,'limit'=>?int]`.

## ترتيب الحساب
1. **self_hosted**: من `config('influencerhub.self_hosted_entitlements')` (فارغ = unlimited).
2. **saas/dedicated**: من الاشتراك الفعّال (trialing|active). لا اشتراك = لا ميزات مدفوعة (boolean=false، numeric=0).
3. base من `plan_entitlements` للنسخة.
4. **overrides** على الاشتراك (`overrides` JSON): `'unlimited'` | bool | int (enterprise/dedicated).
5. **add-ons** (`organization_add_ons` → `add_ons`): زيادة numeric (`grant_value`×quantity) أو تفعيل boolean.

## واجهة
`allows($org,$feature): bool` (boolean) · `limit($org,$feature): ?int` (numeric، null=unlimited).

## ميزات نموذجية
users.max, creators.max, customers.max, campaigns.active.max, storage.gb, integrations.max,
exports.monthly.max, automation.runs.monthly.max, api.requests.monthly.max,
white_label.enabled, custom_domain.enabled, advanced_analytics.enabled, external_portals.enabled, marketplace.enabled.
