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

CI (`deploy.yml`) now runs `tsc` + tenant guard (PR #14) **and the full Playwright suite across chromium/firefox/webkit** (new `e2e` job, this run); `deploy-vps` gated on `needs: [backend, frontend, e2e]` → a failed E2E now blocks deploy. This closed the silent-regression gap that had hidden a real login defect (see finding below).

## Category status

| # | Category | Status | Evidence |
|---|----------|:------:|----------|
| 1 | Authentication / logout / portal routing | **VERIFIED** | `01-auth` 21/21 cross-browser (chromium/firefox/webkit); backend auth tests |
| 2 | Tenancy isolation (P0) | **VERIFIED** | `TenantHttpIsolationTest`, `TenantIsolationSweepTest`, `TenantContextGuardTest`(9), `BrandWorkspaceIsolationTest` green; guard exit 0; `03-isolation` E2E green cross-browser (chromium/firefox/webkit) |
| 3 | Roles & permissions | **VERIFIED** | RBAC feature tests + `04-rbac` E2E **12/12 cross-browser** (viewer sees list, 403 on create/archive, archive control hidden). Cross-browser failure previously charged to "shared-DB pollution" was a **product defect** (viewer login) — root-caused & fixed this run (see finding) |
| 4 | Finance role-safety / IDOR (P0) | **VERIFIED** | `FinanceSeparationOfDutiesTest`, `FinancialMetricsTest` |
| 5 | Plan entitlements (quota enforcement) | **VERIFIED** | `05-entitlement` E2E **6/6 cross-browser** (tenant at `customers.max=1` rejected on a *counting* status; original client intact). Confirmed the quota model: only `qualified`/`active` consume `customers.max` — leads are free by design (`ClientStatus::countingValues`). Two fixes this run: create-modal now **surfaces** the rejection (was silently swallowed); stale test used non-counting `lead` |
| 6 | Core CRUD + design/responsive (React) | **VERIFIED** | `02-clients` **30/30** + `06-ui` **12/12** cross-browser after repairing stale Alpine-era specs to the real React UI (search/filter/create-modal/tabs; brand-token & mobile-card assertions). Surfaced & fixed a product gap: **client archive** had backend support (`ArchiveClient`) but **no UI control** — added a policy-gated أرشفة button |
| 7 | **13-stage campaign lifecycle** (P1) | **VERIFIED** | `CampaignLifecycleService` derives all 13 from real domain state; `CampaignLifecycleServiceTest` (4 tests/35 assertions incl. failure paths); surfaced in campaign command center. Merged PR #15 |
| 8 | Agency portal | PARTIALLY_VERIFIED | Inertia + workflow-service tests green server-side; `07-crm-ui-flows` E2E stale (Alpine-era) — cross-browser repair in progress |
| 9 | Client/brand portal | PARTIALLY_VERIFIED | `InertiaClient*` tests green server-side; `14-client-portal` E2E stale — repair in progress |
| 10 | Creator portal | PARTIALLY_VERIFIED | `InertiaCreator*` tests green server-side; `11-creator-portal` E2E stale — repair in progress |
| 11 | Partner portal | PARTIALLY_VERIFIED | scoped tests green server-side; `15-partner-portal` E2E stale — repair in progress |
| 12 | System admin (SaaS) | VERIFIED | `InertiaAdminPlatformTest` |

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
4. **Cross-browser E2E** (PR #16, merged) — Playwright now chromium+firefox+webkit; auth journey 21/21 cross-browser; **fixed a silently-failing E2E test** (logout correctly lands on public `/`, not `/login`; test 6 independently proves auth enforcement).
5. **Viewer-login product defect** (this run) — `Role::agencyPortalRoles()` single source of truth incl. `Viewer`; `LoginController` + `GoogleAuthController` consume it; `LoginPortalAccessTest` locks real-`POST /login` behavior per role; `04-rbac` now 12/12 cross-browser. (Root-cause detail below.)
6. **Playwright in CI** (this run) — new `e2e` job (Postgres service, `npm run build`, `playwright install --with-deps`, critical suite × 3 engines); `deploy-vps` now `needs: [backend, frontend, e2e]` so failed E2E blocks deploy.
7. **Critical E2E made cross-browser-green** (this run) — repaired stale Alpine-era specs `02-clients` (30/30), `05-entitlement` (6/6), `06-ui` (12/12) to the real React UI; `03-isolation` verified cross-browser. Product gaps fixed: client-archive UI control (policy-gated), create-modal error feedback.

## Cross-browser E2E finding — root-caused to a PRODUCT defect (fixed this run)

The earlier hypothesis (mutating specs polluting a shared E2E DB) was **disproven** by direct evidence and is retracted. Adding Firefox/WebKit surfaced `04-rbac` failures; a network trace showed the viewer login as `POST /login → 302 → GET /login` (bounced back), on **every** engine, not accumulating by project order. `04-rbac` does not actually mutate — tests 22/23 assert the write returns **403** (no state change) — so pollution could not be the cause.

**Root cause (product defect):** `LoginController::login()` (and `GoogleAuthController::canUseAgencyPortal()`) gated the agency portal to a hard-coded role list that **omitted `Role::Viewer`**. But `viewer` (`مُطّلع`) is a legitimate read-only agency role — granted VIEW abilities across CRM, Finance, Creators, and the operational dashboard, and listed as a team role. A viewer could therefore never authenticate into any portal (viewer has no other portal), while every RBAC E2E spec logs in as `viewer@a.test`. Invisible because Playwright was not in CI.

**Classification:** product defect (not test, not test-infra). **Repair (correct layer):**
- Added a single source of truth `Role::agencyPortalRoles()` / `agencyPortalRoleValues()` including `Viewer`; both `LoginController` and `GoogleAuthController` now consume it (removes the duplicated drift-prone lists).
- Added `tests/Feature/LoginPortalAccessTest` — exercises the **real** `POST /login` per role (not `actingAs`): every agency-portal role (incl. viewer) reaches `/app`, a non-agency role is rejected. This is the product-level lock the `actingAs`-based suite structurally could not provide.
- Verified: `04-rbac` now **12/12 across chromium/firefox/webkit**; backend `LoginPortalAccessTest` green.

No per-project DB isolation was needed because there was no real pollution — the failing specs were read-only and blocked solely by the login defect.

## E2E suite was substantially stale against the React migration (finding + repair plan)

Running the **full** suite cross-browser for the first time (nothing had, because Playwright was never in CI) surfaced a large, even-across-engines failure set (≈37 per engine) — plus a 56-minute wall-clock under a single dev-server that amplified stale-selector timeouts into cascading failures. Root cause: many specs were written against the **legacy Alpine UI** and were never updated when the product moved to **React/Inertia** (`input[name="q"]`+a «بحث» button vs a live-debounced input; `.modal input[name=…]` vs unnamed React inputs; `.badge-active` classes vs inline-styled `.badge`; a `table` on mobile vs responsive cards). On a **quiet** machine each real failure is fast (~4s) and deterministic — confirming environment amplification, not flakiness.

Classification is per-test, and this run has already resolved the **critical** tier — every failure repaired at the correct layer, never by weakening an assertion:
- **Test defects** (stale selectors → real React UI, assertions preserved): `02-clients` 9–15, `05-entitlement` 25 (also wrong quota premise), `06-ui` 27/29/30.
- **Product gaps** (additive fixes wiring existing backend capability): client **archive** button (backend `ArchiveClient` had no UI control); create-modal **error feedback** (validation/entitlement rejections were silently swallowed).

**CI gate scope (honest):** the new `e2e` job gates deploy on the **critical, cross-browser-green** specs only — `01-auth 02-clients 03-isolation 04-rbac 05-entitlement 06-ui`. Gating on the not-yet-repaired portal specs would block every deploy on known-stale tests. The list is a literal in `deploy.yml` and **expands as each portal spec is repaired** — the full suite still exists and is run locally. This is scoping the *gate* to what is deterministic, not reducing coverage.

**Remaining repairable (portal/UI, PARTIALLY_VERIFIED):** `07-crm-ui-flows`, `08-creators`, `10-application-review`, `11-creator-portal`, `12-application-files-wizard`, `13-portal-crud`, `14-client-portal`, `15-partner-portal`, `17-command-palette` — same stale-selector class; each to be repaired to the real React UI and added to the CI gate. All server-side behavior for these is already VERIFIED by the backend feature suite.

## Integrations-absent behavior — verified truthful (this run)
Active code inspection (not doc-trust):
- **No fake "Connected".** `IntegrationsController` reflects a static `PlatformRegistry`; every platform is declared `available_manual` (manual entry, no auto-fetch) or `draft` ("coming soon"). The `connected`/`available_api` statuses exist in the enum but are **assigned to no platform**. UI shows "يدوي — متاح", never a live-sync claim.
- **No fabricated paid-media metrics.** Zero `ROAS`/`ad_spend`/`CPC`/`CPM`/attributed-revenue references exist in app or frontend code.
- **Revenue is real internal billing only.** `FinancialMetrics` derives `revenue_minor` strictly from issued `Invoice` rows (net of tax) and cost from committed `Payout` rows — no external commerce/ad source, so revenue/ROI correctly stay within the agency's own ledger.

## Remaining (honest)
- **Portals** (client/creator/partner/agency): VERIFIED server-side (feature suite). Full cross-browser E2E status finalized by the `e2e` job / this run's full-suite sweep.
- All external integrations: `BLOCKED_EXTERNAL` (active-inspection evidence above) — nothing executable without provider credentials + approved OAuth app + a stored connection.

_Last updated: this autopilot run (viewer-login fix + Playwright-in-CI + integrations-truthfulness verification)._
