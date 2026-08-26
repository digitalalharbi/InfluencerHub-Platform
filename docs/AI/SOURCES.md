# SOURCES — Authoritative Source Registry

> Pointers only, not duplicated documentation. Every important conclusion should be
> traceable here. Mark a source **STALE** rather than silently relying on it.

## Canonical

- **Repository:** `digitalalharbi/InfluencerHub-Platform` — sole source of truth.
- **Production:** https://influencerhub.io/ — **deployed = main = `09c6de1` (#92)** (updated to the N4/N6-conversion merge SHA on completion). Non-destructive pipeline (`migrate --force` additive + `db:seed --force` reference-only); per-merge VPS deploy + unauthenticated production smoke SUCCESS. Authenticated production **nomination** QA: BLOCKED_PRODUCTION_NOMINATION_QA_DATA — the sanctioned Showcase QA tenant (`showcase_admin@showcase.test`) has no seeded shortlist/nomination data (`ShowcaseBuilder` seeds none); the mutation chain is proven by cross-browser CI E2E instead.
- **Baseline history:** `244851f` (#82) → `e66a6fa` (#83 N1) → `b43abfa` (#84 comms) → `b52f77c` (#85 follow-up) → `c54c1ef` (#86 N2) → #87 categories/email-lifecycle → `3d26b3e` (#88 N6 decision) → `a2cc80f` (#89 N3/N5) → `5f300d9` (#90 N8 E2E) → `5ca6a4f` (#91 N2 pool-rec notify) → `09c6de1` (#92 docs) → N4 Brand + N6 conversion (this PR).

## Shipped PRs (merged + deployed + production-verified)

- **#83** N1 foundation · **#84** email/copy · **#85** follow-up · **#86** N2 canonical matcher (`app/Domain/Nomination/Services/NominationMatchService.php`) + client-safe presenter (`app/Domain/Nomination/Support/ClientNominationView.php`) · **#87** canonical `NotificationCategory` + email `queued→sent` lifecycle (`app/Domain/Communications/Enums/NotificationCategory.php`, `app/Domain/Communications/Listeners/AdvanceEmailDeliveryOnSent.php`) · **#88** N6 client "request alternative" · **#89** N3 honest match flags + N5 internal export · **#90** N8 cross-browser E2E (`tests/e2e/22-nomination-client-decision.spec.js`) + agency per-item `needs_alternative` label fix · **#91** N2 deferred: pool-recommendation client-decision notification (`app/Http/Controllers/Inertia/Client/RecommendationController.php::announceRecommendationDecision`) · **N4 Brand + N6 conversion (this PR):** `ShortlistService::convertApprovedToCollaborations` (approved shortlist items → `Collaboration` via `CollaborationWorkflowService::offer`), migration `2026_08_26_400001_add_shortlist_item_id_to_collaborations` (durable back-reference + unique idempotency index), `ShortlistController::convert` + route, workspace "تحويل المعتمَدين للتنفيذ" button, brand-detail nomination status (`BrandDetailController`), E2E `tests/e2e/23-nomination-conversion.spec.js`. All squash-merged, CI green (backend/frontend/e2e cross-browser), deployed, smoke-verified.

## Active work

- **Influencer Nomination mission N1–N8 complete** (+ notification/email hardening + deferred PoolRecommendation). Steady-state; only incremental/optional increments remain (see SESSION-STATE "Next Exact Step").

## Communications code paths (N1/N2 track)

- Email: `app/Mail/NotificationMail.php` (recipient-locale + structured business-object copy), `resources/views/components/mail/{layout,button}.blade.php`, `resources/views/mail/notification.blade.php`, `lang/{ar,en}/mail.php`, gallery `app/Http/Controllers/Web/DevMailGalleryController.php` (`/app/preview/mail`, dev-only). Copy guard: `tests/Feature/CopyQualityTest.php`.
- Follow-up: `app/Domain/Finance/Services/InvoiceReminderService.php` + `app/Console/Commands/ScanOverdueInvoicesCommand.php` + `routes/console.php` (daily 08:00). SLA reference: `app/Domain/Automation/Services/SlaEngineService.php`.
- Real emitters emitting business-object copy: `ShortlistService::announceDecision`, `ContentWorkflowService::notify{Creator,Agency,Client}`, `ServiceRequestWorkflowService::assign`.

## Nomination — critical code paths (verified by audit 2026-08-26)

- **Canonical nomination engine:** `app/Domain/Campaigns/Models/{CampaignShortlist,CampaignShortlistVersion,CampaignShortlistItem}.php` + `app/Domain/Campaigns/Services/ShortlistService.php` (versioned, audited, notified, client-decision-aware). Migration `database/migrations/2026_07_29_700001_create_campaign_shortlists.php` (`match_score`, `reasons`, `client_decision`).
- **New access foundation (N1):** `app/Domain/Nomination/**` (`NominationAccess`, `FeatureAvailabilityResolver`, `NominationDecision`, `NominationAbilities`, `Models/FeatureAvailability`) + migration `database/migrations/2026_08_26_100001_create_feature_availabilities_table.php` + middleware `app/Http/Middleware/EnsureNominationEnabled.php` (alias `nomination`, `bootstrap/app.php`).
- **Global creator pool + matching:** `app/Domain/AdminPool/Models/PoolCreator.php` (table `admin_creator_pool`, dedup on `platform`+`account_url`); `app/Domain/AdminPool/Services/PoolMatchService.php` → `{score, reasons[], flags[]}` (real fields only, no fabrication); `app/Domain/AdminPool/Support/CreatorNormalizer.php` (deterministic; null on ambiguity).
- **Pool→tenant bridge (idempotent):** `app/Domain/AdminPool/Actions/MaterializeSharedCreator.php` (relies on `creators.pool_creator_id` unique). Existing nominate endpoint: `app/Http/Controllers/Inertia/CreatorDatabaseController.php::nominate`.
- **Entitlements:** `app/Domain/Billing/Services/EntitlementService.php::allows($org,$key)` (docs `docs/ENTITLEMENTS-DESIGN.md`). Feature rows: `features` table.
- **RBAC pattern:** ability-matrix classes (`app/Domain/AdminPool/Support/CreatorDatabaseAbilities.php`, `app/Domain/CRM/Support/CrmAbilities.php`); roles `app/Domain/Identity/Enums/Role.php`; `User::roleIn($orgId)`.
- **Portals/guards:** `bootstrap/app.php` aliases; `app/Http/Middleware/Ensure*Member.php`, `EnsurePlatformOwner.php`, `EnsureSystemAdmin.php`. Tenant isolation: `app/Domain/Tenancy/Scopes/TenantScope.php` (fail-closed).
- **Platform Owner:** `app/Domain/Platform/Support/PlatformCapabilities.php`; `app/Http/Controllers/Platform/*`; per-tenant grant precedent `app/Http/Controllers/Inertia/Admin/PlatformController.php::setCreatorDatabaseAccess`.
- **Export engine:** `app/Domain/Exports/**` (`ExportService::download`, `TabularData`, `CsvWriter`/`XlsxWriter`/`PdfWriter`, `DocumentArtifactService`); React `resources/js/Components/{ExportButtons,PdfPreviewModal}.tsx`. Docs `docs/EXPORT-CAPABILITY-MATRIX.md`.
- **Nav source:** `resources/js/lib/nav.ts` + `app/Http/Middleware/HandleInertiaRequests.php::navCapabilities` (`nav.can`).

## Tests establishing behavior

- `tests/Feature/NominationFeatureAccessTest.php` (N1 access decision).
- `tests/Feature/InertiaShortlistTest.php`, `ShortlistTest.php`, `CampaignShortlistExportTest.php`, `ShortlistProposalArtifactTest.php` (existing nomination behavior).
- `tests/Feature/CreatorEntitlementsTest.php`, `PlatformOwnerAccessTest.php` (entitlement + platform-owner governance).

## Design docs (verify freshness before relying)

- `docs/SHORTLISTING-AND-SMART-MATCHING.md` (matcher plan) · `docs/ENTITLEMENTS-DESIGN.md` · `docs/EXPORT-CAPABILITY-MATRIX.md` · `docs/PLATFORM-OWNER.md` · `docs/CREATOR-PERMISSIONS.md`.
- **STALE:** `docs/CONTINUATION-STATE.md` (2026-07-18) and `docs/AGENT-EXECUTION-BOARD.md` (2026-07-19) predate the late-August Simplification + Platform Owner work; use Git history, not these, for current state.

## Out of scope (firewall)

- **CampaignsHub / campaignshub.io** — separate project. No merge/commit/branch/code transfer. Ignore if it appears.
