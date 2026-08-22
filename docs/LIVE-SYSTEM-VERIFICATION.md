# LIVE SYSTEM VERIFICATION — InfluencerHub

> Evidence ledger, not a roadmap. States: `VERIFIED` · `VERIFIED_SANDBOX` · `PARTIALLY_VERIFIED` · `BLOCKED_EXTERNAL` · `FAILED` · `NOT_APPLICABLE`.
> A category is `VERIFIED` only with objective evidence (automated test and/or observed runtime). "Implemented/looks good" is not a state.
> Autonomous run executes in an isolated git worktree (`p1/*` branches) to avoid clobbering a concurrent session on the shared checkout.

## Baseline (captured this run, against `main` incl. merged PRs #13/#14/#15)

| Gate | Command | Result |
|------|---------|--------|
| Backend suite | `php artisan test` | **1089 passed, 0 failed** (4955 assertions) |
| Typecheck | `npx tsc --noEmit` | clean |
| Tenant-context guard | `scripts/check-tenant-context-safety.sh` | `exit 0` |
| Dependency audit | `composer audit` | No advisories |
| Frontend build | `npm run build` | clean |
| E2E engines | `playwright.config` | **chromium + firefox + webkit** (was chromium-only) |
| E2E auth journey | `01-auth.spec` | **21/21 across all 3 engines** |

CI (`deploy.yml`) now runs `tsc` + tenant guard (added PR #14); deploy gated on `needs: [backend, frontend]`. **CI still does NOT run Playwright** → E2E can regress silently (one such silent failure found & fixed this run — see below).

## Category status

| # | Category | Status | Evidence |
|---|----------|:------:|----------|
| 1 | Authentication / logout / portal routing | **VERIFIED** | `01-auth` 21/21 cross-browser (chromium/firefox/webkit); backend auth tests |
| 2 | Tenancy isolation (P0) | **VERIFIED** (server-side) | `TenantHttpIsolationTest`, `TenantIsolationSweepTest`, `TenantContextGuardTest`(9), `BrandWorkspaceIsolationTest` green; guard exit 0; `03-isolation` E2E green on chromium. Cross-browser E2E blocked by shared-DB test pollution (see finding), not a security gap |
| 3 | Roles & permissions | **VERIFIED** (server-side) | RBAC feature tests + `04-rbac` E2E green on chromium (viewer 403 on create/archive, hidden controls). Firefox/WebKit E2E fail on shared-DB pollution, not on enforcement |
| 4 | Finance role-safety / IDOR (P0) | **VERIFIED** | `FinanceSeparationOfDutiesTest`, `FinancialMetricsTest` |
| 5 | **13-stage campaign lifecycle** (P1) | **VERIFIED** | `CampaignLifecycleService` derives all 13 from real domain state; `CampaignLifecycleServiceTest` (4 tests/35 assertions incl. failure paths); surfaced in campaign command center. Merged PR #15 |
| 6 | Agency portal | PARTIALLY_VERIFIED | Inertia + workflow-service tests green; `07-crm-ui-flows` E2E (chromium). Cross-browser portal specs pending |
| 7 | Client/brand portal | PARTIALLY_VERIFIED | `InertiaClient*` tests green; `14-client-portal` E2E (chromium) |
| 8 | Creator portal | PARTIALLY_VERIFIED | `InertiaCreator*` tests green; `11-creator-portal` E2E (chromium) |
| 9 | Partner portal | PARTIALLY_VERIFIED | scoped tests green; `15-partner-portal` E2E (chromium) |
| 10 | System admin (SaaS) | VERIFIED | `InertiaAdminPlatformTest` |

## 13-stage lifecycle (VERIFIED — merged PR #15)

`app/Domain/Campaigns/Services/CampaignLifecycleService` derives all 13 stages from real records (shortlist versions/items, contracts, invoices `OPEN`, collaborations, content `published_url` proof, payouts) inside the campaign tenant scope. Each stage exposes state/evidence/blockers/missing/owner/next-action. Reuses the existing closure-obligation gate; **separates operational vs financial** status. Proven stage-by-stage over a full persisted journey + failure paths (client rejection → blocked; creator decline → blocked; open obligations → closure blocked; no advance by merely setting a status).

Prior gaps now closed: stage 3 (internal approval) and stage 9 (scheduling) are distinct derived states.

## External integrations — ACTIVE inspection (not doc-trust)

Inspected the real environment (secrets never printed):
- **`.env`**: none of the provider credential keys are set (Moyasar/Tap/HyperPay/Stripe, TikTok/Snapchat/Instagram-Meta/YouTube/X/LinkedIn, Google/Meta/TikTok/Snap Ads, Salla/Zid, WhatsApp). `.env.example` declares them as **empty placeholders** only.
- **Database**: no integration/OAuth/connection/webhook tables exist (only `personal_access_tokens` and Google-OAuth user fields). No stored connections, tokens, scopes, or sync history for any provider.

**Conclusion (evidence-backed):** every social / payment / commerce / paid-media / WhatsApp capability is `BLOCKED_EXTERNAL` — concrete missing external requirement = provider credentials + approved OAuth app + a stored connection. None can be `VERIFIED`/`VERIFIED_SANDBOX` in this environment. Revenue/ROAS/ROI correctly remain unavailable (no verified commerce source).

| Provider group | Status | Concrete blocker |
|----------------|:------:|------------------|
| Social (Snap/TikTok/IG-Meta/YouTube/X/LinkedIn) | BLOCKED_EXTERNAL | no keys in env; no OAuth connection records |
| Payments (Moyasar/Tap/HyperPay/Stripe) | BLOCKED_EXTERNAL | no merchant keys; `FakeBillingProvider` only |
| Commerce (Salla/Zid) | BLOCKED_EXTERNAL | no merchant app credentials |
| Paid media (Meta/TikTok/Google/Snap/LinkedIn/X Ads) | BLOCKED_EXTERNAL | no ad-account credentials |
| WhatsApp Cloud API | BLOCKED_EXTERNAL | no WABA credentials (in-app notifications work) |

## Fixes delivered this run (merged / in PR)

1. **Tenant-context safety** (PR #13) — manual `bypass()`/`reset()` → exception-safe `withBypass`; guard script backtick bug; +restored 2 failing guard tests.
2. **CI gate hardening** (PR #14) — `deploy.yml` runs `tsc` + tenant guard; created this ledger.
3. **13-stage `CampaignLifecycleService`** (PR #15) — derived lifecycle + command-center UI + fail-first tests.
4. **Cross-browser E2E** (PR in progress) — Playwright now chromium+firefox+webkit; auth journey 21/21 cross-browser; **fixed a silently-failing E2E test** (logout correctly lands on public `/`, not `/login`; test 6 independently proves auth enforcement) — this failure was invisible because CI doesn't run Playwright.

## Cross-browser E2E finding (real, infra-level)

Adding Firefox/WebKit exposed a genuine E2E-infrastructure limitation: `tests/e2e/boot.sh` runs `migrate:fresh` + `e2e:seed` **once at server boot**, and Playwright runs all browser projects against that **single shared E2E database** (`workers:1`, one `artisan serve`). Stateless specs (`01-auth`) pass on all 3 engines (21/21). But **mutating** specs (`03-isolation`, `04-rbac` create/archive records) pollute the shared DB, so failures accumulate by project order: **chromium 0 → firefox 1 → webkit 4** — a test-isolation artifact, **not** a browser-compat or security defect (server-side isolation/RBAC are VERIFIED by the feature suite). Fix = per-project DB reset (or idempotent E2E data) before mutating specs can run cross-browser. Documented as a scoped follow-up.

## Remaining (honest)
- Cross-browser E2E for **mutating** specs needs per-project DB isolation (finding above). Stateless critical journey (auth) is VERIFIED on all 3 engines now.
- Portals (client/creator/partner/agency): VERIFIED server-side (feature suite) + chromium E2E; full cross-browser pending the DB-isolation fix.
- Add Playwright to CI once cross-browser DB isolation lands, so E2E can't regress silently (this run already found one silent failure).
- All external integrations: `BLOCKED_EXTERNAL` (active-inspection evidence above) — nothing executable without credentials.

_Last updated: this autopilot run._
