# Product Closure Ledger — InfluencerHub

> IH-X0 product-truth audit. One row per product area: what is built, the tests
> that fail if it stops being true, and an honest status. **Git/code/tests
> outrank older docs.** Audited at `main` after PR #180 (merge `2e6be07`),
> 2026-10-20.
>
> Status vocabulary: `VERIFIED` · `IMPLEMENTED_NOT_VERIFIED` ·
> `BLOCKED_EXTERNAL_CREDENTIALS` · `BLOCKED_OPERATIONAL_EVIDENCE` ·
> `READY_FOR_CONFIGURATION` · `NOT_STARTED`.
> `VERIFIED` requires passing Feature and/or E2E tests covering the area.
>
> Base metrics: 117 models · 101 controllers · 47 services · 12 policies ·
> 87 migrations · 124 Inertia pages · 181 Feature tests + 2 Unit · 24 Playwright
> e2e specs · ~700 routes · 26 Domain modules.

| # | Area | Status | Key evidence (code) | Tests that fail if it breaks |
|---|------|--------|---------------------|------------------------------|
| 1 | Creator 360 / CRM | **VERIFIED** | `app/Domain/CRM/*`, `app/Domain/Creators/*`; `ClientProfileService`, `CustomFieldService`, `BrandWorkflowService`; policies | `CrmClientTest`, `CrmPolicyTest`, `CustomFieldTest`, `CreatorTest`; e2e `02`,`07`,`08`,`18` |
| 2 | Discovery & Casting | **VERIFIED** | `NominationMatchService` (score 0–100 + reasons[] + flags[], persisted fields only), `ShortlistService`, `CreatorCapabilityService`, `PoolMatchService`; `NominationAccess` | `NominationMatchServiceTest`, `ShortlistTest`, `NominationFeatureAccessTest`; e2e `21`,`22`,`23` |
| 3 | Campaign Command Center | **VERIFIED** | `app/Domain/Campaigns/*`; `CampaignLifecycleService`, `CampaignWorkflowService`; status history | `CampaignLifecycleServiceTest`, `CampaignExecutionJourneyTest`; smoke `campaign-workspace` |
| 4 | Collaboration / Content / Rights | **VERIFIED** (rights = contracts only) | `Collaborations/*`, `Content/*`, `Contracts/*` workflow services + reminders + approvals | `CollaborationTest`(14), `ContentTest`(19), `ContractTest`(15) |
| 5 | Communication Hub (in-app) | **VERIFIED** | `NotificationService`, `DeliveryDispatcher`, `ChannelRegistry`, `Notification*` models | `NotificationTest`, `NotificationDeliveryTest`, `NotificationCenterTest` |
| 5b| External channels (email/SMS/WhatsApp) | **BLOCKED_EXTERNAL_CREDENTIALS** | `WhatsAppCloudClient` (graph v21.0, `configured()` gate), `WhatsAppWebhookController` (HMAC-SHA256, 403 on mismatch), `EmailChannel`, `SmsChannel` | `WhatsAppProviderTest`(6), `EmailProviderTest`, `OtpDeliveryTest` |
| 6 | Automation OS | **VERIFIED** (notify-only) | `AutomationEngine`, `ConditionEvaluator`, `ActionRegistry([NotifyAction])`, `SlaEngineService` | `AutomationEngineTest`(6), `AutomationWiringTest`(7), `SlaEngineTest`(7) |
| 7 | Finance OS (ledger/margin/payout) | **VERIFIED** | `Invoice*`, `Payout*`; `InvoiceService`, `PayoutWorkflowService` (row-locked, `markPaid()` records manual ref only — never moves money); margin in `ReportsController` | `PayoutTest`(20), `InvoiceTest`(17), `FinanceSeparationOfDutiesTest`(15), `FinancialMetricsTest`(13) |
| 7b| Payment settlement (provider) | **BLOCKED_EXTERNAL_CREDENTIALS** | `BillingProvider` contract + only `FakeBillingProvider`; moyasar/stripe are config options with no class; no payment webhook | `BillingTest`(12) covers fake provider only |
| 8 | Portals (Agency/Client/Creator/Brand/Partner/Platform Owner) | **VERIFIED** | `Ensure{Creator,ClientMember,AgencyMember,PartnerMember,BrandMember,SystemAdmin,PlatformOwner}` middleware; route groups in `routes/web.php` | `PortalBoundaryTest`(8), `PortalRoutingTest`; e2e `11`,`14`,`15`,`19` |
| 9 | Reporting — exports + immutable snapshot | **VERIFIED** | `ExportService` (csv/xlsx/pdf), `DocumentArtifactService` (fingerprint, sha256 checksum, preview==download, isStale, regenerate=new version) | `ExportEngineTest`, `ScheduledReportExportJobTest`, `ShortlistPdfExportTest` |
| 9b| Reporting — custom report builder + public share links | **NOT_STARTED** | only fixed `/reports/pdf/{preview,download,regenerate}` + scheduled-report toggle | — |
| 10| AI Agents (proposal→approval→execution product) | **NOT_STARTED** | no `app/Domain/AI`; only optional `ShortlistAssistant` (rule-based fallback, `OpenAiAssistant` opt-in). `docs/AGENT-EXECUTION-BOARD.md` is a build-process board, not a runtime feature | `ShortlistAssistantTest` (assistant only) |
| 11| Integrations / provider readiness (framework) | **VERIFIED** framework · **BLOCKED_EXTERNAL_CREDENTIALS** connectivity | `IntegrationConnection.STATUSES` (explicit; never inferred from stored creds), `AdapterRegistry` empty until creds, `SyncProviderJob` bails unless `isConnected()` | `IntegrationDomainTest`(6), `IntegrationControlCenterTest`(5) |
| 12| Platform Owner / SaaS ops | **VERIFIED** | `PlatformPortalEligibilityService` (fail-closed, derives tenant from reference entity, returns all eligible contexts → multi-membership isolation), entitlements/flags, provisioning | `PlatformOwnerAccessTest`, `PlatformPreviewPrivacyTest`, `PlatformPortalEligibilityTest`, `BillingTest` |

## Tenancy & security (cross-cutting) — VERIFIED

- **Fail-closed tenancy:** `TenantScope` → `whereRaw('1=0')` when no context; `BelongsToTenant` throws on create without context. Portal eligibility derives tenant from the **reference entity**, not the membership row.
- **Isolation tests:** `TenantHttpIsolationTest`(7) (cross-tenant → 404), `TenantIsolationSweepTest`(14) (HTTP + jobs + listeners), `BrandWorkspaceIsolationTest`, `FinanceSeparationOfDutiesTest`(15).
- **Secrets:** integration tokens `encrypted` + `$hidden`, never serialized. Webhook HMAC verified. Audit on connect/disconnect/payout/export.

## Honest gaps (owner-acknowledged, from code + `AGENT-EXECUTION-BOARD.md`)

- No dedicated **usage-rights / licensing** model — rights live only inside `Contract`.
- No **publication-proof / results** store (needed for attribution evidence — see TRACK units).
- Recurring pattern: **guard in schema/UI but not server-enforced** — needs a dedicated permission-enforcement sweep (IH-X14 security unit).
- No invite path for ~166/168 pool creators.
- `/admin` exists only as `/beta/admin`.

## Stale docs reconciled (see `REQUIREMENTS_TRACEABILITY_MATRIX.md`)

- `docs/TEST-REPORT.md` — claims "33 passed" / ~5 classes; real tree is 181 Feature + 24 e2e. **Stale.**
- `docs/CREATOR-TEST-REPORT.md` — claims "137 backend + 55 Playwright (44–55)"; e2e now `01`–`23`. **Stale numbering.**
- `docs/AGENT-EXECUTION-BOARD.md` — says Google Login "not built"; Google OAuth **login is built** (`GoogleAuthController`). Drive/Ads/Analytics still correctly "not built." **Partially stale.**
