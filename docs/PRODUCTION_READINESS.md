# Production Readiness — InfluencerHub

> IH-X0 audit, 2026-10-20 at `main` (`2e6be07`). Production: https://influencerhub.io/
> `merged ≠ deployed ≠ production-verified` — three independent states.

## CI gates (run on every PR)

| Gate | Job | State |
|------|-----|-------|
| Backend Laravel checks | `php artisan test` (+ pint, phpstan per CLAUDE.md gate list) | ✅ green on #180 |
| Frontend React checks | tsc strict · lint · build | ✅ green on #180 |
| End-to-end (Playwright, cross-browser) | 24 specs, Chromium + Firefox/WebKit | ✅ green on #180 (16m33s) |
| Deploy to VPS over SSH | on push to `main` only | runs post-merge (`git reset --hard origin/main`) |
| Production smoke | `production-smoke.yml` | post-deploy |

Local gate before "done" (from `CLAUDE.md`): `php artisan test` · `pint --test` ·
`phpstan analyse` · `npm run typecheck` · `npm run lint` · `npm run build` · E2E.

## Production-ready now (VERIFIED, no external blocker)

- Multi-tenant core with fail-closed isolation; six portals with server guards.
- Creator CRM, Discovery/Nomination, Campaign lifecycle, Collaboration/Content/Contracts.
- In-app notifications; automation (notify-only) + SLA engine.
- Finance ledger: invoices, margins, payout lifecycle (manual settlement), separation of duties.
- Reporting exports (CSV/XLSX/PDF) with immutable snapshots.
- Platform Owner control center, entitlements, feature flags, scoped read-only preview.

## Blocked on external credentials (code-complete, awaiting commissioning)

- Email (SMTP), SMS/OTP (Twilio), WhatsApp Business (Meta), Google OAuth login.
- Payment settlement (no provider class/webhook yet) — **blocks** real money movement.
- Social-platform creator data sync (all `waiting_for_credentials`).

## Not started (next-gen program — see `RESUME_STATE.md`)

- Radical visual system (dark sidebar + light canvas + lime accent) — THEME units.
- WhatsApp conversation inbox + QR device pairing — COMMS units.
- Native attribution/tracking layer (links, click events, UTM, health, server-side events) — TRACK units.
- Custom report builder + public client-safe share links — REPORT unit.
- AI agents (proposal→approval→execution) — after deterministic workflows reliable.

## Known production-readiness risks (fix before scaling)

- **Permission-enforcement sweep:** some guards exist in schema/UI but not server-enforced (IH-X14). Needs IDOR + permission-matrix tests per surface.
- **Performance/query budgets:** no query-budget tests yet on high-value pages (dashboard, Creator 360, reports) — add before data volume grows.
- **Publication-proof / results store** missing — required for truthful attribution evidence.
- Stale docs reconciled in this pass; keep `docs/AI/SESSION-STATE.md` current per CLAUDE.md.
