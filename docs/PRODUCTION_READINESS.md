# Production Readiness — InfluencerHub

> Updated 2026-10-25 at `main` (`6f02e41`, PR #189 merged). Production: https://influencerhub.io/
> Canonical plan: `docs/MASTER_COMPLETION_ROADMAP.md`. Design system: **IDENTITY-03**.
> `merged ≠ deployed ≠ production-verified` — three independent states.

## CI gates (run on every PR)

| Gate | Job | State |
|------|-----|-------|
| Backend Laravel checks | `php artisan test` | ✅ green on #189 (1446 passed) |
| Frontend React checks | `tsc --noEmit` · build | ✅ green on #189 |
| End-to-end (Playwright, cross-browser) | Chromium + Firefox + WebKit | ✅ green on #189 (209+ passed) |
| Deploy to VPS over SSH | on push to `main` only | runs post-merge (`git reset --hard origin/main`) |

Local gate before "done": `php artisan test` · `npx tsc --noEmit` · `npm run build` · focused E2E.
(`pint`/`phpstan` are not in deploy.yml; run if touching their domains.)

## Production-ready now (VERIFIED, no external blocker)

- Multi-tenant core with fail-closed isolation; six portals with server guards.
- Creator CRM, Discovery/Nomination, Campaign lifecycle, Collaboration/Content/Contracts.
- In-app notifications; automation (notify-only) + SLA engine.
- Finance ledger: invoices, margins, payout lifecycle (manual settlement), separation of duties.
- Reporting exports (CSV/XLSX/PDF) with immutable snapshots.
- Platform Owner control center, entitlements, feature flags, scoped read-only preview.
- **PERF-01:** per-navigation Inertia payload ~90% smaller; domain-scoped translations;
  on-demand notifications; SQL-aggregated dashboard/reports time series; query counts cut.
- **IDENTITY-03:** one canonical token system (Midnight Navy + indigo-violet; green = success
  only); light + dark; mobile/PWA preserved; CSS consolidated (−10% main bundle gzip).

## Blocked on external credentials (build internally, then `BLOCKED_EXTERNAL_CREDENTIALS`)

Tracked per provider in `docs/EXTERNAL_BLOCKERS.md` as integration units land:
Email (SMTP), SMS/OTP, WhatsApp Business (Meta), Google Workspace OAuth, Instagram/TikTok/
YouTube/X/Snapchat/LinkedIn, Meta/TikTok/Snapchat/Google/X/LinkedIn/OpenAI Ads, Salla/Zid,
SaaS billing + payment settlement. Each: build contract/adapter/OAuth/webhook/sync/health/
UI/tests/sandbox first; mark the ladder state honestly; never claim Connected/Live without evidence.

## Remaining program (see `docs/MASTER_COMPLETION_ROADMAP.md` §39)

GATE-00 ✅ → **CORE-01 (next)** → CREATOR-01 → INTEGRATIONS-01 → GOOGLE-01 → COMMS-01 →
SOCIAL-01/02 → CASTING-01 → CAMPAIGN-01 → RIGHTS-01 → PROOF-01 → TRACK-01/02/03 →
ADS-01/02/03 → COMMERCE-01 → REPORT-01 → SHARE-01 → FINANCE-01 → AUTOMATION-01 → AI-01 →
QA-01 → CLOSE-01.

## Known production-readiness risks

- **Permission enforcement — ✅ CORE-01:** route groups enforce portals via middleware
  (`agency_member`/`creator`/`client_member`/…); cross-tenant/IDOR/cross-portal covered by
  `TenantIsolationSweep`/`TenantHttpIsolation`/`BrandWorkspaceIsolation`/`LoginPortalAccess`/
  `CrmPolicy`/`PlatformOwnerAccess`; CORE-01 added `PermissionEnforcementSweepTest`
  (guest-redirect, cross-portal block, PERF-01 badge cache proven tenant-scoped).
- **Performance/query budgets — ✅ CORE-01:** `QueryBudgetTest` guards Dashboard / Creator
  Database / Creator-360 / Campaigns / Reports / Payouts / Automation against N+1.
- **Publication-proof / results store** missing — required for truthful attribution (PROOF-01).
- Observability/health endpoints + backup verification — QA-01 / §33.
