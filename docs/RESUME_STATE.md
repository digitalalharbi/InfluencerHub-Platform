# Resume State — InfluencerHub Completion Program

> Authoritative, resumable pointer. Read this + `CLAUDE.md` +
> `docs/MASTER_COMPLETION_ROADMAP.md` + `docs/AI/SESSION-STATE.md` after any compaction.
> Last updated 2026-10-25.

## Canonical references

- **Roadmap (canonical):** `docs/MASTER_COMPLETION_ROADMAP.md` — the ordered program
  GATE-00 → CORE-01 → … → CLOSE-01. Execute units in that exact order, one bounded PR each.
- **Design system (canonical):** **IDENTITY-03**. See `docs/AI/PERF-01-IDENTITY-03.md`.
- **Canonical repo (only write target):** `digitalalharbi/InfluencerHub-Platform`.
- **Production:** https://influencerhub.io/
- **Benchmarks (read-only patterns, never code):** CampaignsHub, digitalSmart.
  `CampaignsHub touched = 0 · digitalSmart touched = 0`. Firewall absolute (`CLAUDE.md` §0).

## CANONICAL DESIGN SYSTEM — IDENTITY-03

Midnight Navy + cool neutrals + refined indigo-violet. **Green = semantic success only.**
Brand accent used sparingly (~5–8%): primary action, selected state, active nav, one key
chart highlight — never a status, never on every card/chart/badge. One token system only
(`resources/css/tokens.css`), no stacked historical identity layers.

- **Brand:** `#5B57D9` (light, hover `#4D49C8`, soft `#EEEDFC`, strong-text `#3F3B9E`) /
  `#8883EF` (dark, soft `#29264C`, ink `#D7D4FF`). Never saturated-violet browser chrome.
- **Light:** canvas `#F5F6FA` · sidebar `#F0F2F7` · surfaces `#FFFFFF`/`#ECEFF5`/`#E5E8F0` ·
  border `#DDE1EA`/`#CDD2DF` · text `#1A1D29`/`#656B7C`/`#9298A8`.
- **Dark:** canvas `#11131A` · sidebar `#0D0F16` · surfaces `#181B24`/`#202430`/`#282D3A` ·
  border `#2D3241`/`#3A4052` · text `#F3F4F8`/`#B0B5C3`/`#7E8494`. Same brand, not a skin.
- **Semantic:** success `#33966F` · warning `#D5963F` · danger `#CC5C68` · info `#527FBB`.
- **Charts:** indigo `#5B57D9` / blue `#557DB8` / slate `#7B8498` / sand `#C39D6B`; success &
  danger reserved for meaning (green is never a generic highlight).
- **theme-color:** light `#F5F6FA` · dark `#11131A`.
- Legacy `--ih-lime*` aliases removed; emerald (`#3FAE86/#4BB890`) and old indigo
  (`#5B45E0/#6252E5`) / cyan (`#22D3EE`) are **0** in built assets.

## Current repository truth (2026-10-25)

- **main:** `6f02e41` — "Merge pull request #189" (PERF-01 + IDENTITY-03 + CSS consolidation).
  Contains the exact #189 head `a005705`.
- **PR #189: MERGED.** PRs #183/#184/#188 closed (superseded). #181/#182/#185/#187 merged.
- **Local clone:** `~/Desktop/Project/InfluencerHub/InfluencerHub-Platform`; pgsql DB
  `influencerhub` (owner = mac user, empty password; `DB_USERNAME` = mac user). Preview via
  `php artisan serve` (port 8030). E2E: `influencerhub_e2e` + `bash tests/e2e/boot.sh`.
  `gh` works; plain git https may time out in sandbox (retry).

## Done

- **PERF-01** (merged in #189): per-navigation Inertia payload ~207KB → 13–27KB (~90%);
  translation dictionary split to CORE + per-component (InjectPageTranslations +
  PageTranslationsCoverageTest guard); notifications on-demand; Organization resolved once;
  NavigationBadges 30s tenant cache; CreatorAnalytics `FILTER` aggregate; dashboard/reports
  time-series `date_trunc` SQL; reports `forPage` deduped. Baseline harness
  `tests/Perf/BaselineTest.php`. Details: `docs/AI/PERF-01-IDENTITY-03.md`.
- **IDENTITY-03** (merged in #189): one canonical token system; emerald removed as brand;
  logo + auth + gateway + error shells + email + PWA + theme-color migrated.
- **CSS consolidation** (merged in #189): three stacked identity blocks removed, `--ih-lime*`
  gone, gateway.css code-split (main app CSS −10% gzip).
- **GATE-00:** #189 CI green → merged → main CI/CD + deploy → production smoke; docs unit #190
  merged (main `a44c4db`, roadmap canonical).
- **CORE-01:** query-budget guard tests (`QueryBudgetTest` — locks PERF-01 on Dashboard/Creators/
  Campaigns/Reports/Payouts/Automation/Creator-360 against N+1) + permission sweep
  (`PermissionEnforcementSweepTest` — NavigationBadges 30s cache proven tenant-scoped, guest
  redirect, cross-portal blocked). Audit: cross-tenant/IDOR/cross-portal already covered by
  TenantIsolationSweep / TenantHttpIsolation / BrandWorkspaceIsolation / LoginPortalAccess /
  CrmPolicy / PlatformOwnerAccess — no enforcement gap found; CORE-01 adds the two missing guards.

- **CREATOR-01:** Creator 360 relationship layer + canonical relationship timeline (§3–§4).
  Migration (relationship fields on creators + `creator_notes` + `creator_tasks`); `CreatorNote`/
  `CreatorTask` models (tenant fail-closed); `CreatorTimeline` service (one chronological stream
  from real events: collabs/content/contracts/payouts/notes/tasks/invitations — no fabrication);
  relationship/notes/tasks CRUD on `CreatorDetailController` (RBAC `update`, IDOR-guarded);
  Creators/Show "العلاقة" tab (editable fields + timeline + notes + tasks, `unknown ≠ 0`).
  Social-account metric enrichment deferred to SOCIAL-01/02 (needs connectors — no dead schema).
  8 feature tests (`CreatorRelationshipTest`); verified end-to-end in browser (AR-RTL, IDENTITY-03).

## Execution order — MASTER_COMPLETION_ROADMAP §39

`GATE-00` ✅ → `CORE-01` ✅ → `CREATOR-01` ✅ → **`INTEGRATIONS-01`** (Integration Foundation V2 —
NEXT) → `GOOGLE-01` → `COMMS-01` → `SOCIAL-01` → `SOCIAL-02` →
`CASTING-01` → `CAMPAIGN-01` → `RIGHTS-01` → `PROOF-01` → `TRACK-01/02/03` →
`ADS-01/02/03` → `COMMERCE-01` → `REPORT-01` → `SHARE-01` → `FINANCE-01` → `AUTOMATION-01` →
`AI-01` → `QA-01` → `CLOSE-01`.

Each unit: audit → implement → tests → PR → CI → merge → deploy → production smoke → docs →
refresh main → next. One bounded branch per unit; never one unbounded branch for the program.

## Non-negotiable invariants (every unit)

1. Real persisted data only; unknown ≠ zero; missing looks missing.
2. No fake KPIs/revenue/ROAS/AI/Connected/delivery/payment without real evidence.
3. Client ⊬ creator cost/margin/payout internals/sourcing/bank; creator ⊬ sell price/margin.
4. Mutating provider/automation/payment: propose → permission → approve → execute → audit →
   read-back; "paid" only from verified settlement.
5. Multi-tenancy fail-closed; RBAC enforced server-side (not CSS hiding).
6. Preserve IDENTITY-03, PERF-01, mobile/PWA. One token system, no new identity.
7. Charts only on real data; every chart has a text/table twin + aria-label.
8. External integration without credentials → build everything internally (contract/adapter/
   OAuth/webhook/sync/health/UI/tests/sandbox), mark `BLOCKED_EXTERNAL_CREDENTIALS` in
   `docs/EXTERNAL_BLOCKERS.md`, continue to the next unit.

## Next exact step

**INTEGRATIONS-01** — fresh branch from current main (after CREATOR-01 merges): Integration
Foundation V2 (roadmap §5). Reuse/extend the existing IntegrationConnection / IntegrationAdapter /
AdapterRegistry / IntegrationSyncRun / IntegrationWebhookEvent / ExternalObjectMap / SyncProviderJob
(no parallel framework): OAuth state + PKCE, encrypted tokens, refresh/revoke, scopes, webhooks,
idempotency, cursor/incremental sync, backfill, reconciliation, retry/backoff, rate-limit handling,
health checks, scheduler, audit, safe errors — and the readiness ladder
(NOT_CONFIGURED → SANDBOX_VERIFIED → AWAITING_CREDENTIALS → READY_FOR_CONFIGURATION →
AUTH_VERIFIED → RESOURCE_DISCOVERY_VERIFIED → LIVE_READ_VERIFIED → LIVE_WRITE_VERIFIED). Token
alone ≠ Connected. No live provider creds needed for the foundation; providers come in later units.
