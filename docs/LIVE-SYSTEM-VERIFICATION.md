# LIVE SYSTEM VERIFICATION — InfluencerHub

> Evidence ledger, not a roadmap. States: `VERIFIED` · `VERIFIED_SANDBOX` · `PARTIALLY_VERIFIED` · `BLOCKED_EXTERNAL` · `FAILED` · `NOT_APPLICABLE`.
> A category is `VERIFIED` only with objective evidence (automated test and/or observed runtime). "Implemented/looks good" is not a state.
> This run executes in a **shared working directory** also driven by a concurrent autonomous session; work is isolated in git worktree `autopilot/live-verification` to avoid tree thrash. Multi-file live remediation across the whole product is therefore performed as small, immediately-committed units.

## Baseline (captured this run)

| Gate | Command | Result | Evidence |
|------|---------|--------|----------|
| Branch/base | `git`, `origin/main` | `main @ 91efdc0`, local == origin (0/0) | fetch |
| Backend suite | `php artisan test` | **1047 passed, 0 failed** (4547 assertions), 75s | run on `main` this session |
| Typecheck | `npx tsc --noEmit` | clean (0 errors) | run on `main` |
| Tenant-context guard | `scripts/check-tenant-context-safety.sh` | `exit 0` (5 named exemptions) | run on `main` |
| Dependency audit | `composer audit` | No advisories | run on `main` |
| Frontend build | `npm run build` | clean | run this session |
| E2E | `npx playwright test` | **NOT RUN this session** — config has **chromium only** (`playwright.config.*:21`) | not executed |

Baseline is **green**. No regression on `main`. (A separate branch — PR #13 — carries additional admin-pool work; a tenant-context-safety violation introduced there was found by the guard and fixed, restoring that branch to green.)

## CI/CD reality (`.github/workflows/deploy.yml`)

- Jobs: `backend` (migrate + `npm run build` + `php artisan test`), `frontend` (lint + build), `deploy-vps` (`needs: [backend, frontend]`, deploy gated by `vars.ENABLE_VPS_DEPLOY == 'true'`).
- **Deploy IS gated on backend tests + frontend build.** Good.
- **Gap (fixed in this branch):** CI did **not** run `npx tsc --noEmit` nor the tenant-context safety guard — two checks used to claim verification locally. Added to the pipeline so the gates that assert safety actually protect deploys.

## Category status

Legend for evidence column: `A`=automated test in suite · `R`=runtime/browser observed this session · `X`=external provider.

| # | Category | Status | Evidence | Notes |
|---|----------|:------:|:--------:|-------|
| 1 | Authentication / onboarding | PARTIALLY_VERIFIED | A | Auth/registration/portal-routing tests pass in suite; not re-driven in browser this session. Google login = config-dependent. |
| 2 | **Tenancy isolation** (P0) | VERIFIED | A | `TenantHttpIsolationTest`, `TenantIsolationSweepTest`, `TenantContextGuardTest`(9), `TenantContextSafetyTest`, `BrandWorkspaceIsolationTest`, `TenantResolutionTest` — all green. Guard forbids manual context calls in prod code (exit 0). |
| 3 | Roles & permissions | VERIFIED | A | Allowed/forbidden asserted across suites (e.g. `viewer cannot create/archive`, admin gates). |
| 4 | **Finance role-safety / IDOR** (P0) | VERIFIED | A | `FinanceSeparationOfDutiesTest`, `FinancialMetricsTest` — creator/client cannot see cost/sell/margin; separation of duties on payouts. |
| 5 | Agency portal (CRM/campaigns/…) | PARTIALLY_VERIFIED | A | Inertia controller + workflow-service tests green; per-page browser pass documented in PHASE gates, not re-run this session. |
| 6 | Client/brand portal | PARTIALLY_VERIFIED | A | `InertiaClientCampaignTest`, `InertiaClientContentTest`, `InertiaClientContractTest` green; mobile/browser not re-driven. |
| 7 | Creator portal | PARTIALLY_VERIFIED | A | `InertiaCreatorCollaborationTest`, `InertiaCreatorContentTest`, `InertiaCreatorContractPayoutTest` green. |
| 8 | Partner portal | PARTIALLY_VERIFIED | A | Scoped-access tests green. |
| 9 | System admin (SaaS) | VERIFIED | A | `InertiaAdminPlatformTest` — read-only oversight, `is_system_admin` gate, cross-tenant stats. |

## 13-stage campaign lifecycle — evidence mapping

The 13 canonical stages are **derivable from real domain evidence today** (no fabricated progress bar). There is **no first-class `CampaignLifecycleService`**; derivation logic exists as `app/Support/Analytics/CampaignAnalytics::commandCenter()` (a 7-stage journey) + `::readiness()` + `app/Support/Workflow/WaitingOn` + `CampaignWorkflowService::openObligations()` (closure gate).

| Stage | Evidence model · status source | Status | Tests |
|-------|-------------------------------|:------:|-------|
| 1 Creation | `Campaign.status` (draft…cancelled) | VERIFIED(A) | `CampaignTest`, `InertiaCampaignsCrudTest` |
| 2 Nomination | `CampaignShortlist{,Version,Item}`; cost/sell/margin server-side | VERIFIED(A) | `ShortlistTest`, `InertiaShortlistTest` |
| 3 Internal approval | shortlist `draft→submitted` (**no distinct `internally_approved` state**) | PARTIALLY_VERIFIED | `ShortlistTest` — gap: stage 3 == stage 4 transition |
| 4 Send to client | version `submitted`+`submitted_at`; `WaitingOn` → client | VERIFIED(A) | `AgencyClientReviewTest` |
| 5 Client decision | `CampaignShortlistItem.client_decision` rolls up version | VERIFIED(A) | `InertiaClientCampaignTest` |
| 6 Quotation & contract | `Contract.status`+signature (client-sell, not cost) | VERIFIED(A) | `ContractTest`, portal contract tests |
| 7 Client collection | `Invoice`+`InvoicePayment` (issued/partially_paid/paid/overdue) | VERIFIED(A) | `InvoiceTest` |
| 8 Creator booking | `Collaboration` accept/decline | VERIFIED(A) | `CollaborationTest`, `InertiaCreatorCollaborationTest` |
| 9 Scheduling | dates on deliverable/collab/content (**no unified schedule status**) | PARTIALLY_VERIFIED | `InertiaCampaignDeliverablesTest` — gap: derive by union |
| 10 Creator finance | `Payout.status` (…waiting_for_provider/paid); segregation of duties | VERIFIED(A) | `PayoutTest`, `FinanceSeparationOfDutiesTest` |
| 11 Publishing & proof | `content_items.published_url/proof_by/proof_at`; `hasPublishProof()` | VERIFIED(A) | `ContentTest` (proof cols exist — not missing) |
| 12 Archive & performance | `content_items` metrics, `results_source(manual\|platform)` | PARTIALLY_VERIFIED | metrics are **manual-entry**; platform sync = external-blocked |
| 13 Closure | `Campaign.status='completed'` gated by `openObligations()` | VERIFIED(A) | `CampaignExecutionJourneyTest`, `StatusReachabilityTest` |

**Confirmed lifecycle gaps (internally executable, non-blocked):**
- Stage 3 lacks a distinct internal-approval state (currently collapses into "send to client").
- Stage 9 has no unified scheduling status (dates scattered across 3 tables; must be unioned).
- No first-class 13-stage orchestrator; existing derivation is 7-stage. A `CampaignLifecycleService` deriving all 13 from the SAME signals (no state duplication) is the next internally-executable unit.

## External integrations — honest per-capability status

All external providers are **credential-blocked** (see `docs/EXTERNAL-BLOCKERS.md`). No social/payment/commerce credentials are present in this environment; OAuth apps are not approved. Neutral provider contracts + fakes exist; **no production or official-sandbox call has been executed**, so none is `VERIFIED`/`VERIFIED_SANDBOX`.

| Provider / capability | Status | Blocker |
|-----------------------|:------:|---------|
| Snapchat / TikTok / Instagram-Meta / YouTube / X / LinkedIn — profile, content, metrics, publishing, discovery | BLOCKED_EXTERNAL | official app registration + OAuth approval + per-account tokens |
| Paid media (Meta/TikTok/Google/Snap/LinkedIn/X Ads) | BLOCKED_EXTERNAL | ad-account credentials + API access |
| Payment provider (Moyasar/Tap/HyperPay) — client collection | BLOCKED_EXTERNAL | merchant account + keys; only `FakeBillingProvider` exists (labelled "بيانات عرض تجريبي") |
| Commerce (Salla / Zid) — orders, revenue, coupons | BLOCKED_EXTERNAL | merchant app credentials |
| WhatsApp Cloud API | BLOCKED_EXTERNAL | WABA credentials (in-app notifications work) |
| AI layer (matching is deterministic today) | BLOCKED_EXTERNAL | model-provider key (current matching is transparent algorithm, works) |

**Revenue/ROAS/ROI:** correctly **not shown** without a verified commerce source (honest "unavailable" state). Not a defect.

## Fixes delivered this run
1. **Tenant-context safety** (PR #13 branch): removed manual `TenantContext::bypass()`/`reset()` from admin-pool/shortlisting controllers → exception-safe `withBypass(closure)`; fixed the guard script's own backtick bug. Restored 2 failing guard tests → green.
2. **CI gate hardening** (this branch): `deploy.yml` now runs `npx tsc --noEmit` and the tenant-context guard, so the checks that assert type-safety and tenant isolation actually gate production deploys.

## Remaining genuine risks / blockers
- All external integrations & real payments: `BLOCKED_EXTERNAL` (no credentials).
- E2E is Chromium-only; multi-engine (Firefox/WebKit) not configured.
- Lifecycle stages 3 & 9 need a unified derivation (`CampaignLifecycleService`) — internally executable, queued next.
- Concurrent session shares the working tree; sustained whole-product live remediation in this session is bounded by that.

_Last updated: this autopilot run, against `main @ 91efdc0`._
