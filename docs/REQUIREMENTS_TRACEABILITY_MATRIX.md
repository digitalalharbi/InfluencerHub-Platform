# Requirements Traceability Matrix — InfluencerHub Next-Generation

> Maps the owner's next-generation + radical-visual/attribution/communication
> directives to current code reality and the execution unit that closes each gap.
> Audited 2026-10-25 at `main` (`6f02e41` (PR #189 merged)). Benchmarks (CampaignsHub, digitalSmart)
> inform PATTERNS only — see `CLAUDE.md` §0.

Status legend: `VERIFIED` · `PARTIAL` · `NOT_STARTED` · `BLOCKED_EXTERNAL`.

## A. Next-generation program (CRM OS)

| Req | Requirement | Current status | Gap → Unit |
|-----|-------------|----------------|------------|
| A1 | Creator 360 relationship CRM + single relationship timeline | **VERIFIED** (CREATOR-01: relationship layer + `CreatorTimeline` + notes/tasks + "العلاقة" tab) | Social metric enrichment → SOCIAL-01/02 |
| A2 | Discovery & Casting OS (search-first, compare, shortlist tray) | PARTIAL (engine VERIFIED; UX not search-first) | IH-X3 |
| A3 | Campaign Command Center (one dominant next action) | PARTIAL (lifecycle VERIFIED; not command-center UX) | IH-X4 / THEME-04 |
| A4 | Collaboration / Content / **Rights** model | PARTIAL (contracts VERIFIED; no usage-rights/licensing model, no publication-proof store) | IH-X5 |
| A5 | Communication Hub (one canonical timeline) | PARTIAL (in-app VERIFIED; no unified cross-channel timeline) | IH-X6 / COMMS-01 |
| A6 | Automation OS with MONITOR/RECOMMEND/AUTO + safeguards | PARTIAL (deterministic notify-only engine VERIFIED; **no modes, notify-only action**) | IH-X7 |
| A7 | Finance OS (cost/fee/margin/payout, provider webhook) | PARTIAL (ledger VERIFIED; settlement BLOCKED_EXTERNAL) | IH-X8 |
| A8 | Portal suite value (client/creator/brand/partner distinct) | VERIFIED (guards + boundaries) | Refine per-portal UX → IH-X9 |
| A9 | Reporting (builder, share links, snapshot) | PARTIAL (exports+snapshot VERIFIED; **no builder, no share links**) | IH-X10 / REPORT-01 |
| A10| AI Agents (proposal→approval→execution) | NOT_STARTED | IH-X11 (after workflows reliable) |
| A11| Integrations / commissioning (readiness ladder) | VERIFIED framework; BLOCKED_EXTERNAL connectivity | IH-X12 |
| A12| Platform Owner / SaaS ops + multi-membership isolation | VERIFIED | Dedicated isolation audit → IH-X13 |
| A13| Security / performance / a11y gates | PARTIAL (isolation VERIFIED; no query-budget tests; permission-enforcement sweep needed) | IH-X14 |

## B. Radical visual + attribution + communication directive

| Req | Requirement | Current status | Unit |
|-----|-------------|----------------|------|
| B1 | Canonical visual system: one `--ih-*` token layer, light + dark, theme-following sidebar | **VERIFIED** (IDENTITY-03, PR #189) | — |
| B2 | Refined indigo-violet brand accent (`#5B57D9`); green = semantic success only | **VERIFIED** (IDENTITY-03, PR #189) | — |
| B3 | Redesigned agency dashboard (command center, "المطلوب منك الآن", charts-first) | NOT_STARTED | THEME-02 |
| B4 | Creator 360 + right context panel | PARTIAL (data VERIFIED) | THEME-03 |
| B5 | Campaign command center visual | PARTIAL | THEME-04 |
| B6 | Client decision surfaces → **قبول / رفض / مشاركة only**, advanced behind "…" | NOT_STARTED (richer client UI today) | **THEME-05** |
| B7 | Notifications center (grouped, actionable) | PARTIAL (NotificationCenter exists; not grouped/premium) | THEME-06 |
| B8 | Sharing as first-class (expiry, revoke, scope, audit, access code, client-safe) | NOT_STARTED | THEME-06 |
| B9 | WhatsApp official connector + conversation inbox | PARTIAL (`WhatsAppCloudClient`+webhook; no inbox) | COMMS-02 |
| B10| WhatsApp QR / device sync | UNSUPPORTED_BY_APPROVED_PROVIDER (record; keep Business API path) | COMMS-03 |
| B11| Creator communication timeline (WhatsApp/email/notes/offers/approvals/…) | NOT_STARTED | COMMS-01 |
| B12| Tracking links (ih.link/{brand}/{campaign}/{creator}, opaque ids) | NOT_STARTED | TRACK-01 |
| B13| Click event model (first-party, privacy-first, consent) | NOT_STARTED | TRACK-01 |
| B14| UTM builder + creator link experience (copy/QR/share/report) | NOT_STARTED | TRACK-02 |
| B15| Creator attribution dashboard (visits, CTA, CPC, ROAS only if real) | NOT_STARTED | TRACK-03 |
| B16| Server-side event pipeline (provider-neutral → adapters) | NOT_STARTED | TRACK-04 |
| B17| Tracking health center (per-connector rings, last event, failures) | NOT_STARTED | TRACK-05 |
| B18| Weekly deterministic insights (computed facts; AI only rewrites) | NOT_STARTED | TRACK-03 / REPORT-01 |
| B19| Visual influencer report builder + client-safe share link | NOT_STARTED | REPORT-01 |
| B20| Contextual calendar (deadlines, publish, rights expiry, payout due) | NOT_STARTED | THEME-04 / REPORT-01 |
| B21| Form reduction (essential first, Advanced, reopen on hidden error) | NOT_STARTED | THEME-01/02 |
| B22| Table→card audit (cards for review/decision; tables for finance/audit/exports) | NOT_STARTED | THEME-02+ |
| B23| Microinteractions + prefers-reduced-motion | NOT_STARTED | THEME-01 |
| B24| Mobile 390px (bottom nav, sheets, no raw tables/overflow) | PARTIAL (responsive exists) | QA-01 |
| B25| Chart a11y (text equiv, SR label, keyboard, non-color meaning), WCAG 2.2 AA | PARTIAL | QA-01 |

## Invariants carried into every unit (from benchmarks + CLAUDE.md)

- Real persisted data only; **unknown ≠ zero**; a missing figure looks missing (em-dash + reason), never 0.
- No fake KPIs / revenue / ROAS / AI matching / integration "Connected".
- Client never sees creator cost / internal margin / payout internals; creator never sees sell price / margin.
- Charts only where real data exists; every chart has a text/table twin + aria-label.
- Mutating provider/automation actions: propose → permission → approve → execute → audit → read-back.
- `CampaignsHub touched = 0 · digitalSmart touched = 0` (patterns only).
