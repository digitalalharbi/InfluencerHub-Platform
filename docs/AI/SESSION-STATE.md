# SESSION-STATE — Continuity Index

> Not a source of truth above Git. A resumable index. Reconcile with Git on every
> resume. Do not commit secrets.

_Last updated: 2026-08-26._

## Repository

- **Canonical repo:** `digitalalharbi/InfluencerHub-Platform`
- **Origin:** `https://github.com/digitalalharbi/InfluencerHub-Platform.git`
- **main = deployed = `9e90c42` (#97)** — Nomination N1–N8 complete; + internal-staff provisioning tool (#96) and Creator-Database category browsing (#97). VPS deploy SUCCESS + production-safe smoke SUCCESS on this SHA (`/up` 200 ×3; auth-gated routes 302; `POST …/shortlist/convert` 419 CSRF-live; Vite build shipped). Production-ops note: internal @influencerhub.io accounts are provisioned by the OPERATOR via `php artisan identity:provision-staff` (Claude cannot access prod DB or handle credentials). N6-conversion code merged in `aebc674` (#93); `c0b5af6` (#94) fixed a firefox E2E hydration flake that had skipped the #93 deploy.
- **Active working directory:** `/Users/mohammedalharbimacbook/Developer/InfluencerHub-nomination` (worktree).
- **Other worktrees:** `InfluencerHub-Platform` (holds protected uncommitted security-advisory work — do not touch), `InfluencerHub-comms`, `ih-autopilot`.

## Shipped (merged + deployed + production-verified)

- **#83 N1 — Influencer Nomination foundation** (`influencer_nomination`): single canonical access decision `App\Domain\Nomination\Access\NominationAccess` (feature + scope-entitlement + surface + role + context); `feature_availabilities` (platform-managed, default-ON, most-specific-scope-wins); `EnsureNominationEnabled` middleware on all nomination routes (OFF ⇒ 403, data preserved); nav single-source; Platform-Owner `manage_feature` toggle (audited). Live cross-browser verified.
- **#84 Notifications & Email — professional bilingual experience + human copy:** recipient-locale-aware `NotificationMail` (ar/en, dynamic lang/dir), `<x-mail.button>`, structured business-object cards (no "السياق"), personalized greeting, dev email gallery (`/app/preview/mail`, dev-only), `CopyQualityTest` (no internal-term leakage), real emitters (shortlist/content/service-request) emit business-object copy.
- **#85 Follow-up — overdue-invoice reminders:** `invoices.overdue_notified_at` one-shot marker, `InvoiceReminderService` + `invoices:scan-overdue` daily schedule, idempotent (no spam), real `due_date` only.
- **#86 N2 — canonical matcher + client-safe presenter:** `App\Domain\Nomination\Services\NominationMatchService` (single scorer, score/reasons/flags; ShortlistService + CreatorMatchingService delegate to it); `App\Domain\Nomination\Support\ClientNominationView` (single client-safe projection, no cost/margin).
- **#87 Notifications — ONE canonical category + email queued→sent lifecycle:** `App\Domain\Communications\Enums\NotificationCategory` (single source: general/campaigns/finance/requests/creators/reviews/system; `map()`/`values()`/`normalize()`); 20 emitters remapped off blanket "general" to correct categories; both preference screens read the enum (no divergent const lists). `AdvanceEmailDeliveryOnSent` listener advances the email delivery attempt `queued→sent` from the real `MessageSent` transport event (via `X-IH-Notification-Id` header) — never sets "delivered" without provider truth.
- **#88 N6 — client "request alternative" decision:** client `needs_alternative` → item flagged, version `changes_requested` (الدور على الوكالة), agency notified ("طلب بديلًا", CTA "اقتراح بديل"); client-safe copy via `ClientNominationView`.
- **#89 N3/N5 — workspace honesty + internal export:** agency candidate cards surface the canonical matcher's honest `flags` (⚠ incomplete-price / different-platform); internal Excel/CSV/PDF export buttons on the shortlist workspace (shared `ExportService`).
- **#90 N8 — cross-browser E2E for the live client decision chain:** `tests/e2e/22-nomination-client-decision.spec.js` — agency submits → client requests alternative → per-item "طلب بديلًا" returns to the agency, verified on chromium/firefox/webkit. Caught+fixed a real N6 gap: agency per-item `decisionLabel`/`decisionTone` lacked `needs_alternative` (showed as "بانتظار القرار").
- **#91 N2 (deferred) — pool-recommendation client-decision notification (safe consolidation):** client approve/reject on an admin-pool recommendation notifies the tenant's `agency_admin` through the ONE shared `NotificationService` (category `creators`). The cross-tenant platform owner (`recommended_by`) is intentionally not notified (no accessible notification center ⇒ would be invisible); no recipient ⇒ no notification. Zero data-model change.

## Active Task

- **Phase:** Influencer Nomination mission N1–N8 **complete** (incl. the deferred N2 PoolRecommendation consolidation). Steady-state: further nomination work is incremental/optional.
- **Prohibitions:** no CampaignsHub; no rebuild/duplicate systems; no invented deadlines/escalation; no destructive prod ops; preserve tenant + Platform-Owner isolation; keep personalized copy (no technical labels/«السياق»).

## Delivery sequence status

N1 ✅ · N2 ✅ (incl. deferred PoolRecommendation notification, #91) · N3 ✅ (#89) · N4 ✅ (Campaign/Client-portal/Agency/Platform mounts through the single engine; **Brand** context now surfaced too — brand detail shows each campaign's nomination status + link, gated on the feature flag; admin pool uses `PoolMatchService` on `PoolCreator` by design — a distinct entity/flow, not a duplicate) · N5 ✅ (#89) · **N6 ✅ INCLUDING CONVERSION** (#88/#90 decision + client alternative; conversion: approved shortlist items → canonical `Collaboration` execution objects via `CollaborationWorkflowService::offer`, durable `collaborations.shortlist_item_id` back-reference + unique index for idempotency, approved-only/no-backup, pending blocks, audited `nomination.converted`, cross-browser E2E `23-nomination-conversion`) · N7 ✅ (owner-only audited `feature_availabilities` toggle + 4-dimension `nomination` middleware + `withBypass` isolation + `platform_owner` RBAC) · N8 ✅ (#90 + conversion E2E cross-browser; production authenticated nomination QA = BLOCKED_PRODUCTION_NOMINATION_QA_DATA — sanctioned Showcase QA tenant has no seeded shortlist data).

## Tests / gates (last full run on main)

- Backend `php artisan test`: **1379 green** (on the #91 line; +9 in `ClientRecommendationTest`, +2 nomination-decision E2E on 3 engines) · Frontend typecheck + build: green · Playwright Chromium/Firefox/WebKit: green (adds `22-nomination-client-decision`) · tenant-context safety guard: green · VPS deploy: success · production smoke: success (per-merge; last on `a2cc80f`).

## Known open items (honest)

- ~~Category taxonomy three lists~~ → **closed (#87):** one canonical `NotificationCategory`.
- ~~Email status only `queued`~~ → **closed (#87):** advances `queued→sent` from the real `MessageSent` transport event (never "delivered" without provider webhook truth).
- Real external inbox delivery: **BLOCKED_EXTERNAL_REAL_INBOX** (no sanctioned SMTP/mailbox) — the only remaining email-delivery gap, external-credential-blocked.
- Real follow-up beyond SLA + invoice needs per-domain reminder columns; shortlist/content have no persisted deadline (deliberately no invented deadlines/escalation).

## QA/test accounts (E2E seed — non-production)

`owner@platform.test` (Platform Owner), `admin@a.test` (agency admin, tenant A), `viewer@a.test`, `finance@a.test` (no nomination view), `admin@b.test` (tenant B), `client@a.test`, `creator@a.test`, `partner@a.test` — all with `E2E_PASSWORD`. Seed via `php artisan e2e:seed` (DB `influencerhub_e2e`).

## Next Exact Step

Nomination mission complete (N1–N8 incl. N6 conversion + notification/email hardening + deferred PoolRecommendation). Optional future increments if requested: auto-generating per-creator deliverables at conversion time (today conversion creates the `Collaboration` execution object tied to the campaign; deliverables stay a separate planning step), an agency-side list surface for admin-pool recommendations (today the agency is notified, no dedicated view), and lifting BLOCKED_EXTERNAL_REAL_INBOX / BLOCKED_PRODUCTION_NOMINATION_QA_DATA once a sanctioned SMTP/mailbox and seeded QA-tenant nomination data exist. Each: focused PR → full gates → merge → deploy → smoke.
