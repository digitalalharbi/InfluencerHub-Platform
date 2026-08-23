# Production Functional Audit — influencerhub.io

Live functional audit (not code/route/CI verification). Source of truth = the running app at `https://influencerhub.io/`, exercised through an existing authenticated session on the **InfluencerHub Showcase** agency (a clearly-labelled demo tenant — "بيانات تجريبية" — used as the safe QA tenant; no real customer data touched).

Statuses: `LIVE_VERIFIED` · `LIVE_PARTIAL` · `BROKEN` · `BLOCKED_AUTH` · `BLOCKED_EXTERNAL` · `NOT_APPLICABLE`.

## Environment findings (high impact)

- **Production `APP_ENV` is NOT `production`.** Proof: `/app/preview` (a dev-only Preview Center) **renders live** instead of 404, and the app serves the **Showcase demo tenant** with "بيانات تجريبية" banners. All gates written as `app()->environment('production')` therefore never fire on prod. `APP_DEBUG` does appear off (a forced 404 shows a clean page, no Ignition). **Owner action required:** set `APP_ENV=production` in `deploy/vps/.env` (root cause; also affects caching/error semantics). Independently, the code has been hardened so dev tools stay blocked regardless (below).

## P0 fixed this run

### `/app/preview` Preview Center leaked into production — **BROKEN → FIX (PR pending)**
- **Live symptom:** `https://influencerhub.io/app/preview` returns **200** and renders the internal Preview Center: dev phase status, a **showcase-credentials file path**, a full internal route/verification matrix, and **destructive buttons** ("حذف البيانات" delete-data, "إعادة توليد" regenerate). The page itself says "محجوبة في الإنتاج" yet is live.
- **Root cause:** all four `PreviewCenterController` methods + the sidebar `dev_tools` cap gated on `app()->environment('production')`, which is false on prod due to the `APP_ENV` misconfig above.
- **Fix (fail-safe, APP_ENV-independent):** new `config('app.dev_tools')` = `env('DEV_TOOLS_ENABLED', false)` — **default false**. Preview Center routes now `abort_unless(config('app.dev_tools'), 404)`; the nav cap reads the same flag. Enabled explicitly only in local/testing/e2e (`.env.example`, `phpunit.xml`, `tests/e2e/boot.sh`). Production, lacking the flag, blocks it **regardless of `APP_ENV`**.
- **Regression tests:** `PreviewCenterGatingTest` (blocked 404 + nav hidden when off; 200 + nav shown when on); updated `DesignSystemPreviewTest`, `NoHardcodedCredentialsTest`.
- **Reverify after deploy:** `curl -sI https://influencerhub.io/app/preview` (authenticated) must be **404**, and the sidebar must not show مركز المعاينة.

## Badge audit (owner-reported: 22 / 11 / 8 / 11)

Live dashboard "المطلوب مني الآن" reconciles: محتوى 11 + علامات 8 + مراجعات العملاء 11 + مستحقات 29 = 59 "بانتظار موافقتك" (matches "الملخّص اليومي: 59"). الطلبات badge = 22; SLA breaches = 22. These are computed, not hardcoded (they sum consistently). Each still to be verified against its underlying records + post-action update — see matrix rows 2/3/13/19/20.

## Finance anomaly (to investigate — Reports/Invoices/Payouts rows)

Dashboard shows **الإيراد (تقديري) 0 ر.س، هامش 0% · ربح -1.7M** with 9 active campaigns and مستحقات معلّقة 1.5M. Revenue 0 with profit -1.7M needs verification: is it correct (no *issued* invoices in the demo, cost-only) or a disconnect?

## Live matrix

| # | Section | URL | Status | Notes |
|---|---------|-----|:------:|-------|
| 25 | مركز المعاينة | /app/preview | **BROKEN→fixed (pending deploy)** | dev tool leaked to prod; fail-safe gate added; reverify post-deploy |
| 1 | لوحة التحكم | /app | auditing | badges reconcile; finance figure suspicious; card links pending |
| 3 | الطلبات | /app/service-requests | pending | badge=22 |
| 13 | المحتوى | /app/content | pending | badge=11 |
| 19 | مراجعة العلامات | /app/brand-reviews | pending | badge=8 |
| 20 | مراجعات العملاء | /app/client-reviews | pending | badge=11 |
| 10 | الحملات + 13 مرحلة | /app/campaigns | pending | |
| 6 | صناع المحتوى | /app/creators | pending | |
| 7 | قاعدة المؤثرين | /app/creator-database | pending | entitlement for Showcase org? |

_(rows filled as the live audit proceeds)_
