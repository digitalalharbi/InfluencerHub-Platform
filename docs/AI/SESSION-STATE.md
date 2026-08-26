# SESSION-STATE — Continuity Index

> Not a source of truth above Git. A resumable index. Reconcile with Git on every
> resume. Do not commit secrets.

_Last updated: 2026-08-26._

## Repository

- **Canonical repo:** `digitalalharbi/InfluencerHub-Platform`
- **Origin:** `https://github.com/digitalalharbi/InfluencerHub-Platform.git`
- **main = deployed = `b52f77cfd271f5e24b97e53ecfdfe8308f392c36`** (VPS deploy SUCCESS + authenticated production smoke SUCCESS).
- **Active working directory:** `/Users/mohammedalharbimacbook/Developer/InfluencerHub-nomination` (worktree; branch `feat/nomination-n2`).
- **Other worktrees:** `InfluencerHub-Platform` (holds protected uncommitted security-advisory work — do not touch), `InfluencerHub-comms`, `ih-autopilot`.

## Shipped (merged + deployed + production-verified)

- **#83 N1 — Influencer Nomination foundation** (`influencer_nomination`): single canonical access decision `App\Domain\Nomination\Access\NominationAccess` (feature + scope-entitlement + surface + role + context); `feature_availabilities` (platform-managed, default-ON, most-specific-scope-wins); `EnsureNominationEnabled` middleware on all nomination routes (OFF ⇒ 403, data preserved); nav single-source; Platform-Owner `manage_feature` toggle (audited). Live cross-browser verified.
- **#84 Notifications & Email — professional bilingual experience + human copy:** recipient-locale-aware `NotificationMail` (ar/en, dynamic lang/dir), `<x-mail.button>`, structured business-object cards (no "السياق"), personalized greeting, dev email gallery (`/app/preview/mail`, dev-only), `CopyQualityTest` (no internal-term leakage), real emitters (shortlist/content/service-request) emit business-object copy.
- **#85 Follow-up — overdue-invoice reminders:** `invoices.overdue_notified_at` one-shot marker, `InvoiceReminderService` + `invoices:scan-overdue` daily schedule, idempotent (no spam), real `due_date` only.

## Active Task

- **Phase:** **N2** — consolidate the canonical Nomination domain; unify parallel recommendation/matching paths; extract a shared client-safe nomination serializer. Then N3→N8.
- **Prohibitions:** no CampaignsHub; no rebuild/duplicate systems; no invented deadlines/escalation; no destructive prod ops; preserve tenant + Platform-Owner isolation.

## Delivery sequence status

N1 ✅ · **N2 → in progress** · N3 Nomination Workspace + matching · N4 contextual mounts · N5 exports · N6 client decision + conversion · N7 admin feature management · N8 role-based cross-browser E2E + production verification — all pending.

## Tests / gates (last full run on main)

- Backend `vendor/bin/phpunit`: green (1361 on the #84/#85 line) · Frontend typecheck + build: green · Playwright Chromium/Firefox/WebKit: green · VPS deploy: success · production smoke: success.

## Known open items (honest)

- Category taxonomy still three lists (canonical `NotificationCategory` = an N-track cleanup).
- Email delivery status advances queued→? only to `queued` (sent-advancement is a documented enhancement).
- Real external inbox delivery: **BLOCKED_EXTERNAL_REAL_INBOX** (no sanctioned SMTP/mailbox).
- Real follow-up beyond SLA + invoice needs per-domain reminder columns; shortlist/content have no persisted deadline.

## QA/test accounts (E2E seed — non-production)

`owner@platform.test` (Platform Owner), `admin@a.test` (agency admin, tenant A), `viewer@a.test`, `finance@a.test` (no nomination view), `admin@b.test` (tenant B), `client@a.test`, `creator@a.test`, `partner@a.test` — all with `E2E_PASSWORD`. Seed via `php artisan e2e:seed` (DB `influencerhub_e2e`).

## Next Exact Step

Audit the canonical nomination domain + parallel paths on `b52f77c`, then implement N2 consolidation (unify matching, extract client-safe serializer, route recommendation client-decision through shared notification/copy), with regression tests; commit → PR → CI green → merge → continue N3.
