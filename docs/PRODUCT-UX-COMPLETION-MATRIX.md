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
| Campaign detail · readiness checklist | Execution-readiness per criterion | Completed items rendered with **strikethrough + faded** (read as cancelled/deleted); binary done/not-done; hint only when incomplete | Per-criterion state (جاهز / يحتاج انتباه / محظور / لا ينطبق) with reason + evidence + working action; **no strikethrough**; completed = «جاهز» | `CampaignAnalytics::readiness()` returns state/reason/evidence/action + ready/blocked counts | Deep links → client, brand-reviews, deliverables, shortlist, content | pending deploy | INTERNAL_VERIFIED |
| Campaign detail · progress label | Meaning of "40%" | Bare `40%` with no source; conflated with 5/7 readiness | Labelled **«تقدّم دورة الحملة»** (13-stage lifecycle %) separated from **«جاهزية التنفيذ X/Y»** | uses `lifecycle.progress` + `readiness.ready/total` | — | pending deploy | INTERNAL_VERIFIED |
| Campaign detail · budget | Financial control | Two bare numbers (budget / red committed) | **الحالة المالية** panel: budget · committed · remaining/overrun · variance% + actions (مراجعة التكاليف / فتح المؤثرين) when over budget; label «المبلغ الملتزم به» | `readiness.budget` {budget, committed, remaining, overBudget, variancePct} | Shortlist / deliverables | pending deploy | INTERNAL_VERIFIED |
| Campaign detail · Next Best Action | First-class next step | Present (ih-nba) — deterministic from lifecycle | Verify actions execute; confirm on over-budget campaign | `CampaignAnalytics::commandCenter().next_action` | — | — (pending) | — (pending) |
| Campaign detail · Campaign Health | Healthy/Attention/At Risk/Blocked w/ reason | Not yet a first-class explainable state | Derive from readiness.blocked + lifecycle + late + finance | — | — | — (pending) | — (pending) |
| Campaign detail · timeline | Operational history | Lists events; raw `16:20 31-07-2026`; no filters | Add actor/role/state-change/filters; localized date | `CampaignAnalytics::timeline()` | Audit log | — (pending) | — (pending) |

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
| Agency | Campaigns (command center) | in progress (this PR) |
| Agency | Shortlisting | — (pending) |
| Agency | Collaborations / Content / Contracts | — (pending) |
| Agency | Invoices / Payouts | — (pending) |
| Agency | Reports / Integrations | partial (integrations honest states verified) |
| Agency | Automation / System Health / Exports / Notifications | verified (prior gate) |
| Client | Decision workspace | — (pending) |
| Creator | Execution workspace | — (pending) |
| Partner | Partner workspace | — (pending) |
| All | Modals / drawers / forms audit | — (pending) |
| All | **Unified PDF Artifact + preview (preview==download bytes)** | — (pending) — P1 |

---

## Unified Document Artifact (P1)

**Built** (`DocumentArtifactService` + `PdfPreviewModal`): one immutable artifact per
(type, subject, template_version), keyed by a source **fingerprint**. Preview (inline)
and Download (attachment) stream the **same stored bytes** — `sha256(preview) ==
sha256(download)`, verified by an `X-Artifact-Checksum` header. Source change ⇒ old
artifact stays immutable, UI shows a **stale** banner, explicit **regenerate** makes a
new version (no silent regeneration). Private disk, tenant-scoped, mount-relative URLs
(no public URLs, no `/app` double-prefix). Stores on `export_jobs`
(subject_type/subject_id/checksum/fingerprint/template_version) — no duplicate model.

| Document | PR | Preview modal | sha256 preview==download | Stale + regenerate | Live | Status |
|---|---|---|---|---|---|---|
| Campaign client brief | #52, #53 | ✓ | test + **live** (`078c2ca2…`) | ✓ test | **prod** | PRODUCTION_VERIFIED |
| Shortlist proposal | #54 | ✓ | test | ✓ (fingerprint per version) | pending deploy | INTERNAL_VERIFIED |
| Invoice | #54 | ✓ | test | ✓ | pending deploy | INTERNAL_VERIFIED |
| Report | — | — | — | — | — | — (pending — needs null-subject/aggregate variant) |
| Payout statement | — | — | — | — | — | — (pending — new template) |
| Contract | — | — | — | — | — | — (pending — new template) |

Currency polish (`SAR → ر.س`) in tenant UI: campaign readiness/financial panel — PR #55
(invoice PDF keeps ISO code as a formal financial document).

### Live production proof (campaign brief, `8c32c809`)
`deploy-vps` success → preview (inline) & download (attachment) both 25,640 B, valid
`%PDF`, **identical sha256** `078c2ca2…664e`, matching `X-Artifact-Checksum`. Modal
opens with title + toolbar (تحميل / تبويب جديد / إعادة إنشاء / إغلاق). A double-`/app`
prefix defect was found live (iframe 404), reproduced, fixed with a regression test,
and shipped as #53.
