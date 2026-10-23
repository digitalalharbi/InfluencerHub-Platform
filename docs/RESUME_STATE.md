# Resume State — InfluencerHub Next-Generation Program

> Authoritative, resumable pointer for the next-gen program. Read this +
> `CLAUDE.md` + `docs/AI/SESSION-STATE.md` after any compaction.
> Last updated 2026-10-23.

## CANONICAL DESIGN SYSTEM — IDENTITY-02 (EMERALD · premium editorial)

Premium, calm, creator-economy-native identity. **No purple, no chartreuse/neon.** Accent used on ~4–7% of a screen only (primary action, selected state, key chart highlight, active nav) — never a status, never on every card/chart/badge. The product must look premium even with the accent removed.

- **Midnight graphite** `#161A1F` (nav) / `#101317` (deep) · **porcelain** `#F6F7F4` canvas ·
  **white** cards · soft surface `#EEF1EC` · border `#E4E7E1` · text `#171A1D` / secondary `#72777A` / muted `#A2A6A6`.
- **Emerald accent** `#3FAE86` (hover `#359A76`, strong-for-CTA `#2E8468`, soft `#E8F5EF`, ink `#143D31`) + **sage** `#91A68B` secondary. Small pastels (sand/soft-blue/rose/lavender) are supporting surfaces only.
- Semantic states (separate from accent): success `#3E9B73` · warning `#D99A3F` · danger `#CF5B5B` · info `#5E88B9`.
- Chart palette (≤4 per chart): graphite `#22272B`, emerald, sage, soft blue `#7EA2C7`, sand `#C8AD7F`, coral `#D98778`; grid `#ECEEEA`.
- Tokens: `resources/css/tokens.css` (semantic names: `--ih-bg/surface/ink/text/border/accent*/nav*/chart-*`). Shared library `Components/Dash.tsx` + `Charts.AreaChart` + `AppShell.contextRail`. **One system — no per-page reinvention.**
- Logo: monochrome UI lockup (white on nav, ink on light) + tiny chartreuse dot — purple removed from mark + nprogress.
- 80% neutral / 15% ink / 5% chartreuse. Sidebar 236px, one chartreuse active capsule. Cards 20px / 16px radius, very soft shadow, minimal border. Field focus = ink border + chartreuse ring.

Acceptance standard = the Eduplex composition reference, verified at 1440×900 / 1280 / 390 in AR-RTL + EN-LTR. **No new feature until IDENTITY-02 is merged + production-verified**, then: Creator 360 → Campaign Command Center → Discovery → Client Portal → Communications → Tracking → Reporting → Automation.

## Anchors

- **Canonical repo:** `digitalalharbi/InfluencerHub-Platform` (only write target).
- **Production:** https://influencerhub.io/
- **main after PR #180:** merge `2e6be07` (i18n bilingual Client portal — Documents/Notifications/Team).
- **Benchmarks (read-only patterns):** CampaignsHub, digitalSmart. `CampaignsHub touched = 0 · digitalSmart touched = 0`. Code firewall absolute (`CLAUDE.md` §0).
- **Local working clone:** `~/Desktop/Project/InfluencerHub/InfluencerHub-Platform`; pgsql DB `influencerhub` (owner = mac user, empty password; `DB_USERNAME` = mac user, NOT `postgres`). Preview via `php artisan serve` (used port 8030). `gh` works; plain git over https may time out in sandbox.

## Done in this program

- **PR #180 merged** (CI green: Backend ✅ Frontend ✅ E2E ✅). main refreshed.
- **IH-X0 product-truth audit** complete → this doc + `PRODUCT_CLOSURE.md`,
  `REQUIREMENTS_TRACEABILITY_MATRIX.md`, `PRODUCTION_READINESS.md`,
  `INTEGRATION_READINESS.md`. `CLAUDE.md` §0 updated to the benchmark policy.

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
