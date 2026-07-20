# SaaS Billing — InfluencerHub V2 (Phase 2)

Domain: `app/Domain/Billing`. مستقل عن أي مزود دفع.

## الكيانات
- **plans** → **plan_versions** (تُقفل عند الاستخدام: `is_locked`) → **plan_prices** (currency + interval: monthly|yearly|one_time|custom، مبالغ بالوحدة الصغرى `amount_minor`).
- **features** (boolean|numeric) + **plan_entitlements** (value / is_unlimited لكل نسخة).
- **subscriptions** (tenant/org، status، overrides JSON للـenterprise/dedicated) + **subscription_items** + **subscription_events**.
- **usage_aggregates** (تجميع ذرّي مقفول) + **usage_records** (تفصيل + idempotency).
- **coupons** (+redemptions) و **add_ons** (+organization_add_ons).

## قواعد
1. لا تعديل مباشر لخطة مستخدمة تاريخيًا → **Plan Versions** + قفل النسخة عند أول اشتراك (`CreateSubscription`).
2. الاشتراك يحتفظ بمرجع `plan_version_id` الذي اشترك به.
3. العملة والضريبة غير ثابتة في الكود (من الأسعار/الإعداد).
4. لا ربط بمزود واحد: `BillingProvider` contract + `FakeBillingProvider` (اختبارات، `isLive()=false`).
5. لا يُعتبر الدفع Live دون Credentials + Sandbox فعلي (Phase 10).

## الخدمات (المصدر الوحيد)
`EntitlementService` · `UsageMeterService` · `SubscriptionService`. ممنوع توزيع شروط الخطة في Controllers/Views.
