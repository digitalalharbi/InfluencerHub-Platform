# SESSION-STATE — Continuity Index

> Not a source of truth above Git. A resumable index. Reconcile with Git on every
> resume. Do not commit secrets.

_Last updated: 2026-08-26._

## Repository

- **Canonical repo:** `digitalalharbi/InfluencerHub-Platform`
- **Origin:** `https://github.com/digitalalharbi/InfluencerHub-Platform.git`
- **main = deployed = `c54c1ef97f61fe6f7eb258450300a3d86564e59e`** (VPS deploy SUCCESS + authenticated production smoke SUCCESS on this SHA).
- **Active working directory:** `/Users/mohammedalharbimacbook/Developer/InfluencerHub-nomination` (worktree; current branch continues N3–N8 + notification gaps).
- **Other worktrees:** `InfluencerHub-Platform` (holds protected uncommitted security-advisory work — do not touch), `InfluencerHub-comms`, `ih-autopilot`.

## Shipped (merged + deployed + production-verified)

- **#83 N1 — Influencer Nomination foundation** (`influencer_nomination`): single canonical access decision `App\Domain\Nomination\Access\NominationAccess` (feature + scope-entitlement + surface + role + context); `feature_availabilities` (platform-managed, default-ON, most-specific-scope-wins); `EnsureNominationEnabled` middleware on all nomination routes (OFF ⇒ 403, data preserved); nav single-source; Platform-Owner `manage_feature` toggle (audited). Live cross-browser verified.
- **#84 Notifications & Email — professional bilingual experience + human copy:** recipient-locale-aware `NotificationMail` (ar/en, dynamic lang/dir), `<x-mail.button>`, structured business-object cards (no "السياق"), personalized greeting, dev email gallery (`/app/preview/mail`, dev-only), `CopyQualityTest` (no internal-term leakage), real emitters (shortlist/content/service-request) emit business-object copy.
- **#85 Follow-up — overdue-invoice reminders:** `invoices.overdue_notified_at` one-shot marker, `InvoiceReminderService` + `invoices:scan-overdue` daily schedule, idempotent (no spam), real `due_date` only.
- **#86 N2 — canonical matcher + client-safe presenter:** `App\Domain\Nomination\Services\NominationMatchService` (single scorer, score/reasons/flags; ShortlistService + CreatorMatchingService delegate to it); `App\Domain\Nomination\Support\ClientNominationView` (single client-safe projection, no cost/margin).

## Active Task

- **Phase:** N3→N8 + notification-gap closure. Current unit: canonical `NotificationCategory` + emitter/preference remap.
- **Prohibitions:** no CampaignsHub; no rebuild/duplicate systems; no invented deadlines/escalation; no destructive prod ops; preserve tenant + Platform-Owner isolation; keep personalized copy (no technical labels/«السياق»).

## Delivery sequence status

N1 ✅ · **N2 core ✅** (deferred: safe PoolRecommendation consolidation) · N3 Workspace+matching UX · N4 contextual mounts · N5 exports · N6 client decision+conversion · N7 admin management hardening · N8 role-based cross-browser E2E — N3–N8 pending.

## Tests / gates (last full run on main)

- Backend `vendor/bin/phpunit`: green (1369 on the #86/N2 line) · Frontend typecheck + build: green · Playwright Chromium/Firefox/WebKit: green · VPS deploy: success · production smoke: success on `c54c1ef`.

## Known open items (honest)

- Category taxonomy still three lists (canonical `NotificationCategory` = an N-track cleanup).
- Email delivery status advances queued→? only to `queued` (sent-advancement is a documented enhancement).
- Real external inbox delivery: **BLOCKED_EXTERNAL_REAL_INBOX** (no sanctioned SMTP/mailbox).
- Real follow-up beyond SLA + invoice needs per-domain reminder columns; shortlist/content have no persisted deadline.

## QA/test accounts (E2E seed — non-production)

`owner@platform.test` (Platform Owner), `admin@a.test` (agency admin, tenant A), `viewer@a.test`, `finance@a.test` (no nomination view), `admin@b.test` (tenant B), `client@a.test`, `creator@a.test`, `partner@a.test` — all with `E2E_PASSWORD`. Seed via `php artisan e2e:seed` (DB `influencerhub_e2e`).

## Next Exact Step

Canonical `NotificationCategory` enum (single source) + safe emitter/preference remap; then N5 export wiring, N6 client alternative-request + conversion, N7 admin hardening, N3/N4 UX, N8 role-based E2E, and the safe PoolRecommendation consolidation. Each: focused PR → full gates → merge → deploy → smoke → continue.
