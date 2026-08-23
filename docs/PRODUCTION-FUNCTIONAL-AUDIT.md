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

## P1 fixed this run (owner-reported: "الإعدادات والفريق واجهة UI بدون تعديل")

The owner reported that **Settings and Team look present but can't be edited** — "many categories with the same idea, UI without edit ability". Live inspection confirmed it at the **backend** level (not a render glitch): agency `TeamController` and `SettingsController` each had **only `index()`** — pure read-only shells. The Team page even told the user "الأدوار تُدار من إعدادات المؤسسة" while Settings had no editable field, a closed dead-end.

### الفريق `/app/team` — read-only shell → **FIXED**
- **Was:** 0 forms / 0 inputs live; no invite, no role change, no deactivate. Backend had no write methods.
- **Now:** real management, server-enforced per request (not just hidden buttons):
  - **Add member** — add an existing platform user by email with a chosen role (honest: no fake one-time token with no acceptance route; unknown email returns a clear message to have them sign up first; a suspended member is reactivated).
  - **Change role** — inline role select per member.
  - **Suspend / Reactivate / Remove** — status controls; remove confirms first.
  - **Guards:** admin-only (`super_admin`/`agency_admin`/`operations_manager`); `super_admin` not assignable from the workspace UI; **last active owner protected** from demotion/suspension/removal (no lock-out); every action audit-logged.
- **Tests:** `AgencyTeamManagementTest` (10) — add/duplicate/unknown-email, role change + last-owner guard, suspend→reactivate→remove, permission gate, super_admin block.

### الإعدادات `/app/settings` — read-only shell → **FIXED**
- **Was:** subscription + entitlements + team preview, all read-only; nothing to save.
- **Now:** editable **"ملف مساحة العمل"** — workspace name + contact email (real `organizations` columns shown across the app), admin-gated, validated, persisted, audit-logged. Subscription/entitlements remain honestly read-only (billing-managed) with the existing "تُدار من فريق الحساب" note, and the team card links to the now-functional Team page.
- **Tests:** `AgencySettingsUpdateTest` (4) — save name/email, name required, invalid-email rejected, non-admin forbidden.

### Pagination raw i18n keys — **FIXED**
- Every paginated list (الحملات/العملاء/المحتوى…) showed raw `pagination.previous` / `pagination.next` because `lang/{ar,en}/pagination.php` were missing. Added both. Test: `PaginationLangTest`.

All above: 1144 backend tests green (incl. 18 new). Reverify live after deploy.

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
| 23 | الفريق | /app/team | **BROKEN→fixed (pending deploy)** | was read-only shell; add/role/suspend/reactivate/remove added, admin-gated, last-owner guard, audited |
| 24 | الإعدادات | /app/settings | **BROKEN→fixed (pending deploy)** | was read-only shell; editable workspace name + contact email, admin-gated, validated, audited |

_(rows filled as the live audit proceeds)_
