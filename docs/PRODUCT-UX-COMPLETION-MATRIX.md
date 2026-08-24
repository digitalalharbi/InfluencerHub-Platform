# PRODUCT-UX-COMPLETION-MATRIX

Tracks the system-wide product transformation: every portal → module → page →
action → modal, turning informational dashboards into operating workspaces.

**Status vocabulary (only these):** `PRODUCTION_VERIFIED` · `INTERNAL_VERIFIED` ·
`BLOCKED_EXTERNAL` · `NOT_APPLICABLE`. A row with no evidence yet is marked
`— (pending)` and is NOT a completion claim.

**Core principle** — every important page must answer: what is happening · what
is blocked · why · who owns the next action · when is it due · what can I do now ·
what changes if I act · where is the evidence. Not merely how-many / what-status /
what-percent.

---

## P1 — Campaign Command Center

| Page / Element | Purpose | Problem (before) | Improvement | Backend effect | Cross-module | Live verified | Status |
|---|---|---|---|---|---|---|---|
| Campaign detail · readiness checklist | Execution-readiness per criterion | Completed items rendered with **strikethrough + faded** (read as cancelled/deleted); binary done/not-done; hint only when incomplete | Per-criterion state (جاهز / يحتاج انتباه / محظور / لا ينطبق) with reason + evidence + working action; **no strikethrough**; completed = «جاهز» | `CampaignAnalytics::readiness()` returns state/reason/evidence/action + ready/blocked counts | Deep links → client, brand-reviews, deliverables, shortlist, content | ✓ campaign 2 (over-budget): `ضمن الميزانية`=محظور w/ numeric evidence + actions; ready items clean | **PRODUCTION_VERIFIED** |
| Campaign detail · progress label | Meaning of "40%" | Bare `40%` with no source; conflated with 5/7 readiness | Labelled **«تقدّم دورة الحملة»** (13-stage lifecycle %) separated from **«جاهزية التنفيذ X/Y»** | uses `lifecycle.progress` + `readiness.ready/total` | — | ✓ live (تقدّم 23% vs جاهزية 5/7) | **PRODUCTION_VERIFIED** |
| Campaign detail · budget | Financial control | Two bare numbers (budget / red committed) | **الحالة المالية** panel: budget · committed · remaining/overrun · variance% + actions (مراجعة التكاليف / فتح المؤثرين) when over budget; label «المبلغ الملتزم به» | `readiness.budget` {budget, committed, remaining, overBudget, variancePct} | Shortlist / deliverables | ✓ live (180,433 / 243,585 / +35% / actions) | **PRODUCTION_VERIFIED** |
| Campaign detail · Next Best Action | First-class next step | Present (ih-nba) — deterministic from lifecycle | Deterministic next step from lifecycle; renders live | `CampaignAnalytics::commandCenter().next_action` | — | ✓ live (اعتماد المحتوى المعلّق) | PRODUCTION_VERIFIED |
| Campaign detail · Campaign Health | Healthy/Attention/At Risk/Blocked w/ reason | Not a first-class explainable state | Financial/blocked state surfaced via readiness.blocked + الحالة المالية badge | — | — | — (backlog — not this gate) | — (pending) |
| Campaign detail · timeline | Operational history | Lists events; raw `16:20 31-07-2026`; no filters | Add actor/role/state-change/filters; localized date | `CampaignAnalytics::timeline()` | Audit log | — (backlog — not this gate) | — (pending) |

### Regression tests
- `CampaignReadinessTest` — over-budget ⇒ `blocked` with numeric evidence + action;
  completed ⇒ `ready` (no binary `done`, no action); no-budget ⇒ `not_applicable`;
  budget object math (remaining, variance%).

---

## Pending modules (not yet transformed — honest backlog)

Work proceeds module-by-module; each row will be filled with evidence as it lands.

| Portal | Module | Status |
|---|---|---|
| Agency | Dashboard (operational queues) | — (pending) |
| Agency | My Tasks | — (pending) |
| Agency | Service Requests | — (pending) |
| Agency | Clients / Brands / Creators detail | — (pending) |
| Agency | Shared Creator Database | — (pending) |
| Agency | Publishers (purpose re-eval) | — (pending) |
| Agency | Creator Applications | — (pending) |
| Agency | Campaigns command center (readiness/progress/budget) | **PRODUCTION_VERIFIED** |
| Agency | Shortlisting (client-safe proposal PDF preview) | **PRODUCTION_VERIFIED** |
| Agency | Contracts (PDF preview) | **PRODUCTION_VERIFIED** · rest of module — (pending backlog) |
| Agency | Invoices / Payouts (PDF preview) | **PRODUCTION_VERIFIED** · rest of module — (pending backlog) |
| Agency | Reports (PDF preview) | **PRODUCTION_VERIFIED** · Integrations honest states verified (prior gate) |
| Agency | Automation / System Health / Exports / Notifications | verified (prior gate) |
| Client | Decision workspace | — (pending backlog) |
| Creator | Execution workspace | — (pending backlog) |
| Partner | Partner workspace | — (pending backlog) |
| All | Modals / drawers / forms audit | — (pending backlog) |
| All | **Unified PDF Artifact + preview (preview==download bytes)** | **PRODUCTION_VERIFIED** — all 6 documents live |

---

## Unified Document Artifact (P1 — DELIVERED, PRODUCTION_VERIFIED)

**Built & live.** One immutable artifact per (type, subject, template_version), keyed
by a source **fingerprint**. Preview (inline) and Download (attachment) stream the
**same stored bytes** — verified live by equal SHA-256 and a matching
`X-Artifact-Checksum` header. Source change ⇒ old artifact immutable, UI marks
**stale**, explicit **regenerate** creates a new version (**no silent regeneration**).
Private disk, tenant-scoped, mount-relative URLs, no public URLs.
Backed by `DocumentArtifactService` + reusable `PdfPreviewModal`, stored on
`export_jobs` (subject_type/subject_id/checksum/fingerprint/template_version) —
no duplicate model. Production: `e43a0946`.

| Capability | PR(s) | Live evidence (SHA-256 preview==download) | Status |
|---|---|---|---|
| Campaign PDF (client brief) | #52 #53 #57 | `dbe81817…` + visual RTL modal | **PRODUCTION_VERIFIED** |
| Shortlist PDF (proposal) | #54 | `e364ff3b…` | **PRODUCTION_VERIFIED** |
| Invoice PDF | #54 | `9c45841d…` | **PRODUCTION_VERIFIED** |
| Reports PDF (aggregate / tenant-subject) | #58 | `1748f797…` | **PRODUCTION_VERIFIED** |
| Payout Statement PDF | #59 | `ea2d5f39…` | **PRODUCTION_VERIFIED** |
| Contract PDF | #59 | `f7a710ff…` + visual RTL modal (signature block) | **PRODUCTION_VERIFIED** |
| preview == download (same bytes) | #52 | live for all six + `X-Artifact-Checksum` match | **PRODUCTION_VERIFIED** |
| Stale detection + explicit regeneration | #52 | live: change field → stale, old bytes unchanged, no silent regen; regenerate → new version `bc8b0c40→f2ed9b08`, old immutable | **PRODUCTION_VERIFIED** |
| Persistent artifact storage | #57 | `app_storage` volume (app/queue/scheduler) + self-heal on missing file | **PRODUCTION_VERIFIED** |
| Tenant isolation on preview/download | #52 #54 #59 | non-tenant → 404 live; cross-tenant INTERNAL_VERIFIED | **PRODUCTION_VERIFIED** |
| Currency `SAR → ر.س` (tenant UI) | #55 | live in readiness/financial panel (invoice keeps ISO) | **PRODUCTION_VERIFIED** |

### Production defects found & fixed during this gate (each: reproduce → regression test → PR → CI → merge → deploy → re-prove)
| Defect | Symptom | Fix | PR |
|---|---|---|---|
| Double `/app` PDF preview path | modal iframe hit `/app/app/…/preview` → 404 | emit mount-relative URLs; `u()` adds the single prefix | #53 |
| composer / GitHub 504 deploy resilience | `deploy-vps` failed 3× — GitHub API rate-limited zipballs (504) | retry `composer install` within the layer (cache accumulates) | #56 |
| Ephemeral artifact storage | preview/download 200 but **0 bytes** after redeploy (files wiped, rows persist) | persistent `app_storage` volume + self-heal (`latest()` skips orphaned rows → regenerate) | #57 |
| Scheduler cross-container heartbeat | `/app/system-health` scheduler stuck **down** (heartbeat written to per-container file cache, invisible to web) | drop 24h overlap-mutex + write/read the heartbeat via the shared DB cache store | #49 #50 |
| Arabic currency formatting | tenant UI showed `SAR 180,433` | map `SAR → ر.س` (ISO kept for formal docs) | #55 |
