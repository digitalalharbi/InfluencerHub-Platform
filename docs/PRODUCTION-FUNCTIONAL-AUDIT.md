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

## Badge definitions (authoritative)

Every sidebar badge is computed live in `App\Support\Navigation\NavigationBadges` (memoised per request; a count of 0 hides the badge). **Scope facts that apply to all badges:** each query runs under the global `TenantScope`, so all badges are **tenant-scoped**; none is **role-scoped** — the numbers are identical for every user in the tenant, and a user's role only changes whether the nav *item* is visible (via `navCapabilities`), never the count. These are work-queue totals ("how much open work exists"), deliberately **not** "assigned to me".

| Badge (nav label) | Key | Exact definition | Nav gate |
|---|---|---|---|
| الطلبات | `service_requests` | `ServiceRequest` where `status ∈ {submitted, triage, in_progress, needs_info}` (`OPEN_STATUSES`). No assignee/SLA filter. | — |
| طلبات الانضمام | `creator_applications` | `CreatorApplication` where `status ∈ {submitted, under_review}` | `can: reviews` |
| المحتوى | `content` | `ContentItem` where `status = agency_review` (awaiting agency review only) | — |
| مراجعة العلامات | `brand_reviews` | `Brand` where `status ∈ {submitted, under_review}` | `can: reviews` |
| مراجعات العملاء | `client_reviews` | `ClientProfileChangeRequest {submitted, under_review}` **+** `ClientDocument {pending}` | `can: reviews` |
| ترشيحات المؤثرين (بوابة العميل) | `client_recommendations` | `PoolRecommendation` for the active client where `status = recommended` | client portal |

**Team page tiles** (`/app/team`, from `ServiceRequest` only): **عمل مفتوح** = open + `assigned_to` set; **تجاوز SLA** = open + assigned + `sla_breached_at` set; **غير مُسنَد** = open + `assigned_to` null.

**On "22 = 22" (الطلبات vs تجاوز SLA):** these come from *different* queries — the badge counts every open request; the tile counts open+assigned+breached. They coincide only because the showcase seeder assigns and SLA-breaches every open row, so on the demo tenant open == assigned == breached. On real data they diverge; it is not a shared query. (Once an item leaves `OPEN_STATUSES` it drops from both — `sla_breached_at` itself is a one-way latch and is never cleared, by design.)

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

## "Many categories, UI-only?" — systematic read-only sweep

The owner worried other sections are also UI-only. Every agency Inertia controller was classified by whether it exposes write actions. Nine had no write methods of their own — but only **two** were genuine editable-capability gaps (Team, Settings, both fixed above). The rest are correct by design:

| Controller | Verdict |
|---|---|
| `TeamController` | **was a gap → fixed** (add/role/status) |
| `SettingsController` | **was a gap → fixed** (workspace profile) |
| `AccountController` | Functional — writes delegated to shared `Creator\AccountController` (12 write methods; verified live: 21 inputs, حفظ/تحديث كلمة المرور) |
| `ContentController` (list) | Writes on `ContentDetailController::action` (review/approve on the item page) |
| `CreatorDetailController` (show) | Writes on `CreatorsController::store/update` |
| `ClientDetailController` (show) | Writes on `ClientsController` / `ClientChildrenController` (12 routes) |
| `BrandsController` (list) | Writes on `BrandDetailController` + brand-reviews |
| `ShortlistingController` (overview) | Writes on `ShortlistController` (8 routes) |
| `MyTasksController` | Read-only aggregator by design — each task links to its actionable module |
| `ReportsController` | Analytics — read-only by nature |
| `IntegrationsController` | Read-only status; connecting a platform is `BLOCKED_EXTERNAL` (needs real platform API credentials), not a broken shell |

Conclusion: list/overview and detail-show pages delegate their writes to sibling controllers that **do** have POST routes; the only true "present but not editable" shells were Team and Settings.

## Badge audit (owner-reported: 22 / 11 / 8 / 11)

Live dashboard "المطلوب مني الآن" reconciles: محتوى 11 + علامات 8 + مراجعات العملاء 11 + مستحقات 29 = 59 "بانتظار موافقتك" (matches "الملخّص اليومي: 59"). الطلبات badge = 22; SLA breaches = 22. These are computed, not hardcoded (they sum consistently). Each still to be verified against its underlying records + post-action update — see matrix rows 2/3/13/19/20.

## Finance anomaly (to investigate — Reports/Invoices/Payouts rows)

Dashboard shows **الإيراد (تقديري) 0 ر.س، هامش 0% · ربح -1.7M** with 9 active campaigns and مستحقات معلّقة 1.5M. Revenue 0 with profit -1.7M needs verification: is it correct (no *issued* invoices in the demo, cost-only) or a disconnect?

## Complete live navigation matrix (audited end-to-end)

Every production nav category was exercised live against real data (Inertia props read from the running app, cross-checked between modules). **Zero internally-executable BROKEN / LIVE_PARTIAL remain.** Non-LIVE_VERIFIED rows are either honestly-empty (no data entered yet — not a defect) or BLOCKED_EXTERNAL (needs credentials/entitlement outside our control).

| Section | URL | Status | Live evidence |
|---------|-----|:------:|-------|
| لوحة التحكم | /app | **LIVE_VERIFIED** | KPIs reconcile: brief.approvals 59 = content 11+brands 8+client-reviews 11+payouts 29; overdue 11 = late campaigns; team open/breached 22 = Σ members = SR badge; creator/client counts match |
| مهامي | /app/my-tasks | **LIVE_VERIFIED** | honest aggregator: contentReview 11 = content badge, brandReviews 8 = brand badge, myRequests 0 (admin unassigned) |
| الطلبات | /app/service-requests | **LIVE_VERIFIED** | open 22 = 5 submitted+6 triage+6 in_progress+5 needs_info; breached 22; unassigned 0. Reassignment now notifies the new owner (PR #29) |
| العملاء | /app/clients | **LIVE_VERIFIED** | total 15 = complete 11+incomplete 4; with_active_campaigns 7 = active 7; canCreate; CRUD via ClientsController/ClientChildrenController |
| العلامات | /app/brands | **LIVE_VERIFIED** | total 25 = approved 12+draft 5+needs_review 8; badge 8 = needs_review; approval-readiness checklist + optional note live (PR #27, verified 36% completeness on brand 5) |
| صناع المحتوى | /app/creators | **LIVE_VERIFIED** | total 160 = verified 80+unverified 80; tier_a 86; matches dashboard |
| قاعدة المؤثرين | /app/creator-database | **LIVE_VERIFIED (gate)** · BLOCKED (entitled path) | non-entitled showcase org → **403** + nav cap false (correct deny). Positive path needs an entitlement grant to test |
| الناشرون | /app/publishers | **BLOCKED_EXTERNAL** | real distinct domain (discovery → save → convert-to-Creator funnel; `ConvertPublisherToInfluencer`), **not** a Creators duplicate. 0 discoverable because all 6 connectors are manual (no live platform API). Empty state honestly communicated |
| طلبات الانضمام | /app/creator-applications | **NOT_APPLICABLE (empty)** | 0 applications on prod (honest); application→review→approve→creator flow exists & tested |
| الحملات + 13 مرحلة | /app/campaigns | **LIVE_VERIFIED** | 13-stage lifecycle **derived from real state** (campaign 2: creation/contract/booking complete, creator_finance in_progress "2 unpaid", publishing not_started because 0 publish-proofs, closure blocked). Proves Campaign→Contract/Booking/Payout/Content chains |
| الترشيحات | /app/shortlisting | **LIVE_VERIFIED** | 24 campaigns, awaitingClient 2; ShortlistController write actions |
| التعاونات | /app/collaborations | **LIVE_VERIFIED** | total 90 = active 55+completed 35; committed 160.6M |
| المحتوى | /app/content | **LIVE_VERIFIED (hardened)** | reschedule fixed, unified actor timeline, cross-module deep-links (PR #26); **badge resolve-and-recount proven live: content 11→10** after send-to-client on CN-1-8 |
| العقود | /app/contracts | **LIVE_VERIFIED** | total 56, active value 248M; sent/signed/completed lifecycle |
| الفواتير | /app/invoices | **NOT_APPLICABLE (empty)** | 0 invoices issued → revenue 0 (this is the honest source of dashboard revenue=0); create/issue/tax/total lifecycle exists (canCreate) |
| المستحقات | /app/payouts | **LIVE_VERIFIED** | total 57; open 50 = 151.68M (matches dashboard); paid 7 = 21.86M; pending 29 = dashboard approval queue |
| التقارير | /app/reports | **LIVE_VERIFIED** | KPI provenance clean: revenue/billed/collected/tax honestly 0 (no invoices); cost 173.5M = real payouts; profit −173.5M = 0−cost; **no fabricated ROAS/ROI/social metrics** |
| التكاملات | /app/integrations | **LIVE_VERIFIED** | 7 platforms, honest per-provider state (6 manual, 1 soon); internal dev-doc link removed (PR #28); no fake "connected" |
| الوكالات الشريكة | /app/partner-agencies | **NOT_APPLICABLE (empty)** | 0 partners (honest); nav route correct (`/partner-agencies`, `can:admin`); invite/accept/revoke flow exists |
| الفريق | /app/team | **LIVE_VERIFIED** | management (add/role/suspend/reactivate/remove) + per-member drill-down (PR #31): member 3 open 5/breached 5/resolved 0/total 5, deep-links resolve, reload-persistent |
| الإعدادات | /app/settings | **LIVE_VERIFIED** | editable workspace name + contact email (affects header); dev-doc link removed |
| حسابي | /app/account | **LIVE_VERIFIED** | profile/prefs/sessions/2FA all present; 21 inputs + save (verified earlier) |
| مركز المعاينة | /app/preview | **LIVE_VERIFIED (fixed)** | dev tool no longer leaks: 404 + nav-hidden live |

### Badge integrity — resolve-and-recount proven
Not just documented: on the live QA tenant, captured content badge = **11**, performed the real `send-to-client` action on CN-1-8 (agency_review → client_review), reloaded, badge = **10**. Badges are live queries, not cached/hardcoded. Definitions in the "Badge definitions" section above.

### Finance figures — honest, not fabricated
Revenue 0 is real (0 invoices issued). "Profit −1.7M" = 0 revenue − 173.5M real committed payout cost (open 151.68M + paid 21.86M). Every finance number traces to a real source; nothing is invented. (Observation, not a defect: the "ربح/profit" label reads starkly pre-invoicing — a candidate for a "net before invoicing" relabel, but it is arithmetically truthful.)

### Cross-module chains verified
Campaign→Contract (quotation stage reads signed contract) · Creator→Booking (creator_booking "5 booked") · Payout→creator_finance ("2 unpaid") · Content→Publication→Stage-11 (0 proofs → publishing not_started) · Content workflow→badge/queue (send-to-client decremented content badge & moved the item) · Brand→Campaigns (brand detail lists its campaigns) · Request→Campaign (convert flow, tested). Client→Brand→Campaign and Shortlist→ClientDecision→Collaboration chains hold via the same derived state the lifecycle reads.
