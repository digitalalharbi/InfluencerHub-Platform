# PRODUCT-JOURNEYS

The real end-to-end journey for each role, mapped from controllers/pages/routes (not from
imagination). For each: goal, entry, flow, decisions, blockers/waiting, completion, related
modules, and the proposed simplified journey. Companion to `PRODUCT-INFORMATION-ARCHITECTURE.md`
and `PRODUCT-COMPLEXITY-AUDIT.md`.

Evidence anchors (file:line) are given so each journey is verifiable against code.

---

## 1. Agency / Workspace Admin

- **Goal:** run the agency's campaigns profitably; keep the team unblocked.
- **Entry:** `/app` Dashboard (`AgencyDashboardController` → `OperationalDashboard::compose`).
- **Flow (today):** Dashboard brief → skim `myWork` → jump to whichever module has the pending
  item (Requests, Content, Brands, Payouts…) → act → return. Admin also configures team,
  settings, automation, integrations, watches system health.
- **Decisions:** approve/deny brand & client reviews; approve payouts; assign requests.
- **Waiting states:** awaiting client decision, awaiting creator confirmation, awaiting provider
  (payouts), SLA timers on requests.
- **Blockers:** items surfaced by `NavigationBadges` + `myWork` priority ranks.
- **Completion:** all `myWork` cleared; campaigns progressing; no overdue SLA.
- **Related modules:** all of them (this is the super-user).
- **Proposed simplified journey:** start in **عملي** (unified, per-item actionable), operate
  campaigns from the **Campaign Workspace** (no module-hopping), admin tooling moves to a
  secondary **Administration** section so the daily surface is ~7 primary items.

## 2. Operations Team (agency member, non-admin)

- **Goal:** move assigned campaigns/requests/content forward.
- **Entry:** `/app` Dashboard; realistically should be **عملي**.
- **Flow (today):** sees ~18 sidebar items (no admin/reviews); works Requests → Campaigns →
  Shortlisting → Collaborations → Content → Contracts, switching modules each step.
- **Decisions:** nominate creators, send to client, request content revisions, issue contracts.
- **Waiting:** client decision, creator acceptance.
- **Blockers:** content in `agency_review`, requests near SLA.
- **Completion:** deliverables published, content approved.
- **Related modules:** execution group + finance (read).
- **Proposed:** **عملي** for the day; **Campaign Workspace** for execution; execution modules
  leave primary nav (reachable inside the campaign). Primary drops ~18 → ~6.

## 3. Client

- **Goal:** review and decide — approve creators, approve content, sign, pay, watch progress.
- **Entry:** `/client` dashboard (`Client/DashboardController`).
- **Flow (today):** dashboard → Requests / Campaigns / ترشيحات المؤثرين (proposal) / المحتوى
  (approvals) / العقود. 9 nav items.
- **Decisions:** approve/reject shortlist proposal (`Client/CampaignController::shortlistDecision`),
  approve/comment content, sign contracts, view/pay invoices.
- **Waiting:** agency to send proposal/content.
- **Blockers:** anything "بانتظار قرارك".
- **Completion:** decisions made; deliverables approved; invoices settled.
- **Related modules:** client portal only (agency internals never exposed; client-safe
  serializers enforce this — `toSharedArray`, portal controllers).
- **Proposed:** client home answers only "**ما الذي ينتظر قراري؟** / ما الجاري / المستندات /
  الفواتير". Tighten to decision-first; keep the deliberate shortlist proposal fields
  (score/reasons/fee are intentionally shared on the proposal — do **not** hide, do **not**
  add cost/margin).

## 4. Creator

- **Goal:** accept work, deliver content, get paid.
- **Entry:** `/creator` dashboard.
- **Flow (today):** invitations → accept/decline → collaborations → content submission →
  revision handling → contracts → payouts. 7 nav items.
- **Decisions:** accept/decline collaboration; submit/revise content.
- **Waiting:** agency review, client approval, payout provider.
- **Blockers:** revision requests, unsigned contracts.
- **Completion:** content approved & published; payout marked paid.
- **Related modules:** creator portal only; own bank/IBAN in Account (self-owned).
- **Proposed:** keep the tight 7; fix the payouts label (المستحقات, not المدفوعات); creator
  home = "دعوات جديدة + مطلوب منك".

## 5. Partner Agency

- **Goal:** operate only the clients/scopes delegated to it.
- **Entry:** `/partner` dashboard (`Partner/DashboardController` — only active `PartnerClientLink`).
- **Flow (today):** clients (linked only) + requests. 3 nav items — already minimal.
- **Decisions:** within delegated scope only (`PartnerScope`).
- **Completion:** delegated work delivered.
- **Related modules:** thin, scope-limited; **not** an agency replica (confirmed).
- **Proposed:** keep minimal; ensure it never surfaces full agency chrome.

## 6. System Admin (SaaS platform)

- **Goal:** oversee tenants, plans, subscriptions, audit — read-mostly.
- **Entry:** `/admin` (`is_system_admin`).
- **Flow (today):** tenants · signup requests · plans · subscriptions · audit · (pool/shortlisting). 8 items.
- **Decisions:** approve signups, manage plans/subscriptions.
- **Completion:** platform healthy, requests actioned.
- **Related modules:** platform-level only — must stay separated from workspace operations (§23).
- **Proposed:** keep platform admin clearly separate from any workspace nav; move workspace
  "system health" here or mark it clearly workspace-scoped.

---

## Campaign lifecycle journey (the spine — all agency roles)

Canonical 13 stages (`CampaignLifecycleService::STAGES`): creation → nomination →
internal_approval → send_to_client → client_decision → quotation_contract → client_collection →
creator_booking → scheduling → creator_finance → publishing → archive_performance → closure.

**UI grouping (mission §2)** — keep all 13 internally; present as higher-level phases:
الإعداد · الترشيح والاعتماد · التعاقد · التنفيذ · النشر · المالية · الإغلاق.

**Before:** operating these 13 stages means visiting Campaigns, Shortlisting, Collaborations,
Content, Contracts, Invoices, Payouts — ≈7 module switches, re-finding the campaign each time.

**After:** the **Campaign Workspace** holds every stage as a tab; the Overview shows current
phase, next best action (derived from persisted state via `CampaignLifecycleService`), owner,
blocker, and per-area status (client/creator/content/finance). Context switches ≈1.

Next-best-action is **derived from real persisted state**, never fabricated (§9): the stage
computation already exists in `CampaignLifecycleService`; the workspace surfaces its output.
