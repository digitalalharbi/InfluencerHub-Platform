# Resume State — InfluencerHub Next-Generation Program

> Authoritative, resumable pointer for the next-gen program. Read this +
> `CLAUDE.md` + `docs/AI/SESSION-STATE.md` after any compaction.
> Last updated 2026-10-23.

## CANONICAL DESIGN SYSTEM (RADICAL-IDENTITY-01, merged #185)

The product identity is **Eduplex-referenced**, NOT the old indigo-first direction:
- **Ink/Navy** `#1D1C2B` (identity, text, sidebar) + **warm off-white** `#F5F5F1`
  workspace + **white** cards + **acid lime** `#C8F45A` accent (active nav capsule,
  primary CTA = lime+ink, chart highlight). Purple is a tiny secondary accent only
  and survives in the legacy logo until the mark is refreshed.
- Frame: navy sidebar (one solid lime active capsule) · compact header · warm
  canvas · **bento** grid · **integrated right column of white cards** (not a heavy
  rail) · charts = lime + ink + grey (restrained).
- Shared component library: `resources/js/Components/Dash.tsx`
  (`MetricTile`, `ChartCard`, `SectionBar`, `RailCard`) + `Charts.AreaChart` +
  `AppShell.contextRail`. **One system, one identity — do not reinvent per page.**
- Tokens: `resources/css/tokens.css` (names preserved; whole app re-skins).
  Visual acceptance standard is the Eduplex reference, not a subjective "new look".

## Anchors

- **Canonical repo:** `digitalalharbi/InfluencerHub-Platform` (only write target).
- **Production:** https://influencerhub.io/
- **main after PR #180:** merge `2e6be07` (i18n bilingual Client portal — Documents/Notifications/Team).
- **Benchmarks (read-only patterns):** CampaignsHub, digitalSmart. `CampaignsHub touched = 0 · digitalSmart touched = 0`. Code firewall absolute (`CLAUDE.md` §0).
- **Local working clone:** `~/Desktop/Project/InfluencerHub/InfluencerHub-Platform`; pgsql DB `influencerhub` (owner = mac user, empty password; `DB_USERNAME` = mac user, NOT `postgres`). Preview via `php artisan serve` (used port 8030). `gh` works; plain git over https may time out in sandbox.

## Done in this program

- **#180** i18n client portal · **IH-X0** governance (#181) · **CLAUDE.md** §0 benchmark policy.
- **THEME-01** (#182) dark sidebar + token groundwork — merged, then **superseded** by the identity reset.
- **#183 / #184** (incremental theme / first bento) — **closed, visually rejected** (old-UI-recolor).
- **RADICAL-IDENTITY-01 (#185) MERGED** — the canonical design system above (ink/navy + warm off-white + acid lime; bento dashboard; integrated white-card right column; AreaChart). main `1aaf942`.
- **RADICAL-UI-02 — Creator 360** (this branch): rebuilt `Creators/Show` on the design
  system — compact entity header, score ring + contact + access in the white-card right
  column, 4 metric tiles, subscores Bars chart, relationship rows (not tables), content
  gallery. AccessPanel invite/resend/revoke logic preserved. Live QA 1440 + 390.

## Remaining page migrations (SAME design system — `Dash.tsx` + tokens; no per-page reinvention)
Campaign Command Center → Discovery → Client Portal → Communications → Attribution/Tracking → Reporting → Automation → Integrations/AI.

## Execution order (each = fresh main → audit → fail-first test → implement → tests → live QA → mobile → ar/en → PR → CI → merge → deploy → smoke → refresh → next)

**Governance**
- [x] IH-X0 Product truth + governance

**Visual program (owner's radical visual directive — highest live priority)**
- [ ] **THEME-01** tokens (`--ih-*`) + dark sidebar + light canvas shell + card/chart primitives + motion (next unit)
- [ ] THEME-02 dashboard command center ("المطلوب منك الآن", charts-first, table→card audit, form reduction)
- [ ] THEME-03 Creator 360 + right context panel + relationship timeline
- [ ] THEME-04 Campaign command center + contextual calendar
- [ ] THEME-05 client decision surfaces → **قبول / رفض / مشاركة** only (advanced behind "…")
- [ ] THEME-06 notifications center + first-class sharing (expiry/revoke/scope/audit)

**Communication**
- [ ] COMMS-01 unified creator communication timeline
- [ ] COMMS-02 WhatsApp official connector + conversation inbox
- [ ] COMMS-03 QR/device pairing — only if compliant provider supports it, else record `UNSUPPORTED_BY_APPROVED_PROVIDER`

**Attribution / tracking (athar.link-style, patterns only)**
- [ ] TRACK-01 tracking-link domain + first-party click events (opaque ids, consent)
- [ ] TRACK-02 UTM builder + creator link experience (copy/QR/share/report)
- [ ] TRACK-03 creator attribution dashboard + weekly deterministic insights
- [ ] TRACK-04 provider-neutral server-side event pipeline (adapters gated by readiness ladder)
- [ ] TRACK-05 tracking health center (per-connector rings/last-event/failures)

**Reporting / QA**
- [ ] REPORT-01 visual influencer report builder + client-safe share link
- [ ] QA-01 mobile 390 + ar/en + accessibility (WCAG 2.2 AA, chart a11y) + performance/query budgets

## Non-negotiable invariants (every unit)

1. Real persisted data only; unknown ≠ zero; missing looks missing.
2. No fake KPIs/revenue/ROAS/AI/integration-Connected.
3. Client ⊬ creator cost/margin/payout internals; creator ⊬ sell price/margin.
4. Mutating provider/automation/payment actions: propose → permission → approve → execute → audit → read-back; payment "paid" only from verified settlement.
5. Preserve InfluencerHub indigo identity; add lime accent as a token layer, never hard-coded.
6. Charts only on real data; every chart has a text/table twin + aria-label.
7. Do not merge automatically — stop before merge for review (per CLAUDE.md working agreements).

## Next exact step

Start **THEME-01**: audit current `resources/css/*` and the app shell layout, introduce the `--ih-*` token layer (dark nav + light canvas + lime accent over the existing indigo identity), build the dark sidebar + content shell + card/chart primitives, preview live, then PR.
