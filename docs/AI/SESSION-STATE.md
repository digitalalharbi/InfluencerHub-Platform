# SESSION-STATE — Continuity Index

> Not a source of truth above Git. A resumable index — reconcile with Git on every resume.
> Do not commit secrets. Canonical plan: `docs/MASTER_COMPLETION_ROADMAP.md`; full state:
> `docs/RESUME_STATE.md`.

_Last updated: 2026-10-25._

## Repository

- **Canonical repo (only write target):** `digitalalharbi/InfluencerHub-Platform`
- **main = `0aa0020`** — "Merge pull request #194" (GOOGLE-01). Deployed to VPS; prod smoke
  `/up` 200 + `/` 200; migration `google_workspace_layer` applied. Production: https://influencerhub.io/
- **Working clone:** `~/Desktop/Project/InfluencerHub/InfluencerHub-Platform`.
- **Firewall:** CampaignsHub + digitalSmart are read-only pattern benchmarks.
  `CampaignsHub touched = 0 · digitalSmart touched = 0`.

## Current program

Executing `docs/MASTER_COMPLETION_ROADMAP.md` in order, one bounded PR per unit
(audit → implement → tests → PR → CI → merge → deploy → smoke → docs → refresh → next).

- **GATE-00 ✅** — #189 merged; main CI/CD + VPS deploy; production smoke; docs refreshed
  (this unit). Design system canonical = **IDENTITY-03**. PERF-01 preserved.
- **CORE-01 ✅** — `QueryBudgetTest` (locks PERF-01 vs N+1) + `PermissionEnforcementSweepTest`
  (NavigationBadges 30s cache proven tenant-scoped, guest redirect, cross-portal block). Audit:
  cross-tenant/IDOR/cross-portal already covered by existing suites — no gap found.
- **CREATOR-01 ✅** — Creator 360 relationship layer (stage/health/owner/source/channel/follow-up +
  `creator_notes`/`creator_tasks`) + canonical `CreatorTimeline` (real events only) + "العلاقة" tab;
  8 tests (`CreatorRelationshipTest`); browser-verified. Social metrics deferred to SOCIAL-01/02.
- **INTEGRATIONS-01 ✅ VERIFIED** — Foundation V2 reconcile + gap-fill (§5): readiness ladder
  (`IntegrationConnection::readiness()`) + OAuth lifecycle (`SupportsOAuth` + `OAuthFlowService`:
  state/PKCE/exchange/refresh/revoke). #193 merged (`c645f17`) → main CI green → VPS deploy → prod
  smoke `/up`+`/` 200 (Control Center served; token≠Connected invariant deployed). 7 tests.
- **GOOGLE-01 ✅ VERIFIED** — #194 merged (`0aa0020`) → main CI green → VPS deploy → prod smoke
  `/up`+`/` 200 (migration applied). Google Workspace on the foundation (§8), extending
  `IntegrationConnection` (no parallel table): `GoogleWorkspaceAdapter` (provider `google`,
  incremental consent, `SupportsScopedAuthorization`), swappable `GoogleClient`
  (`HttpGoogleClient` real / `FakeGoogleClient` sandbox), Drive/Sheets/Gmail/Calendar services,
  `IntegrationExternalEvent` feeding the creator timeline, connect/callback routes. 18 tests
  (`GoogleWorkspaceTest`). Live = `BLOCKED_EXTERNAL_CREDENTIALS` (`docs/EXTERNAL_BLOCKERS.md`).
- **COMMS-01 ✅ (PR pending)** — two-way messaging layer on the foundation. Existing comms was
  outbound-only (notifications + status webhook); COMMS-01 adds inbound WhatsApp ingestion (idempotent
  via `integration_webhook_events`), sender→creator/client matching (`ContactMatcher`, phone
  digit-normalized, cross-tenant resolve), `message_in`/`message_out` as `IntegrationExternalEvent`
  feeding `CreatorTimeline`, free-form `sendText` + template send (`ConversationService`), and
  WhatsApp/Email/SMS on the readiness ladder (`CommsConnectionService`, status from real config).
  Inbound wired into `WhatsAppWebhookController`. 7 tests (`CommunicationsInboxTest`). Live =
  `BLOCKED_EXTERNAL_CREDENTIALS`/`BLOCKED_PROVIDER_APPROVAL` (`docs/EXTERNAL_BLOCKERS.md`).
- **SOCIAL-01 (NEXT)** — social account connect + profile/metrics ingestion (Instagram/TikTok/…).
- Then: SOCIAL-02 → CASTING-01 →
  CAMPAIGN-01 → RIGHTS-01 → PROOF-01 → TRACK-01/02/03 → ADS-01/02/03 → COMMERCE-01 →
  REPORT-01 → SHARE-01 → FINANCE-01 → AUTOMATION-01 → AI-01 → QA-01 → CLOSE-01.

## Invariants (every unit)

Real persisted data only (unknown ≠ zero); no fake KPIs/revenue/ROAS/Connected/payment
without evidence; fail-closed multi-tenancy + server-side RBAC; client ⊬ cost/margin/payout/
bank/sourcing; mutating provider/automation/payment = propose→approve→execute→audit→read-back;
IDENTITY-03 (one token system, green = success only) + PERF-01 + mobile/PWA preserved;
external integration without creds → build internal stack, mark `BLOCKED_EXTERNAL_CREDENTIALS`
in `docs/EXTERNAL_BLOCKERS.md`, continue.

## Tests / gates

- **main `c645f17` (INTEGRATIONS-01):** backend + `tsc` + build + Playwright cross-browser all green
  in CI; VPS deploy + prod smoke `/up`+`/` 200.
- **COMMS-01 branch (local):** full backend `php artisan test` **1490 passed** (+7 COMMS-01);
  `CommunicationsInboxTest` 7/7, `GoogleWorkspaceTest` 18/18. Backend-only unit → no frontend change;
  PR CI is the authoritative gate. `routes/*` NOT Pint-formatted (it rewrites inline FQCNs and can
  repoint colliding controllers); only new/changed app+test files were formatted.

## QA/test accounts (E2E seed — non-production)

`owner@platform.test` (Platform Owner), `admin@a.test` (agency admin, tenant A), `viewer@a.test`,
`finance@a.test`, `admin@b.test` (tenant B), `client@a.test`, `creator@a.test`, `partner@a.test`
— all with `E2E_PASSWORD`. Seed via `php artisan e2e:seed` (DB `influencerhub_e2e`).

## Next exact step

**SOCIAL-01** from a fresh branch off main (after the COMMS-01 PR merges + deploys + smokes) —
social account connect + profile/metrics ingestion (Instagram/TikTok/YouTube/Snapchat/X/LinkedIn)
on the Integration Foundation; internal build + fake/sandbox tests, then record
`BLOCKED_EXTERNAL_CREDENTIALS`; full cycle. COMMS-01 PR is pending merge → deploy → smoke first.

## Historical (in Git history)

Influencer Nomination N1–N8 (PRs #83–#91), notifications/email hardening, Platform Owner
preview (#77–#82), Creator Database, THEME→IDENTITY-02→**IDENTITY-03** evolution (#183/#184/#188
closed as superseded; #185/#187/#189 merged). See `git log` and `docs/AI/PERF-01-IDENTITY-03.md`.
