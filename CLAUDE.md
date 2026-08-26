# InfluencerHub — Claude Project Instructions

> Root project memory for Claude Code. Loaded automatically each session. These
> instructions OVERRIDE default behavior. Keep durable; put volatile session state
> in `docs/AI/SESSION-STATE.md`, not here.

## Project

- **Product:** InfluencerHub — نظام علاقات المؤثرين (influencer relations platform).
- **Canonical repository:** `digitalalharbi/InfluencerHub-Platform` — the single source of truth.
- **Production:** https://influencerhub.io/
- **Stack:** Laravel 12 · PHP 8.4 · PostgreSQL · Inertia + React 19 + TypeScript + Vite · Tailwind. Arabic-first, RTL. Latin digits everywhere.
- **Domain-driven:** business logic under `app/Domain/<Domain>/**`; HTTP controllers under `app/Http/Controllers/**`; React under `resources/js/**`.

## 0. HARD PROJECT BOUNDARY (CampaignsHub firewall)

InfluencerHub is **100% independent** of CampaignsHub / campaignshub.io. CampaignsHub is **OUT OF SCOPE**.

Forbidden, always: merging any CampaignsHub PR/branch/commit/diff, copying its code, reusing its worktree, unifying the two projects, or making InfluencerHub architecture decisions based on CampaignsHub.

**Invariant:** cross-project merge = 0 · commit transfer = 0 · branch reuse = 0 · code migration = 0.

If anything CampaignsHub-related appears: **IGNORE AS OUT-OF-SCOPE** — do not ask what to do with it.

## Sources of truth (in order)

1. Canonical GitHub repository
2. Current exact branch / code
3. Tests / migrations / schema / routes / policies / services
4. Recent PRs + commits + CI
5. Explicitly verified production state
6. Documentation *after* freshness verification
7. Session/chat summaries — continuity aid only

If conversation or memory conflicts with Git/current code, **Git/current code wins.**
`merged` ≠ `deployed` ≠ `production-verified` — three independent states; never conflate.

## Protect existing local work

Never `reset --hard`, `clean`, delete, overwrite, auto-`stash`, `checkout` over files, or force-push `main`. Never lose uncommitted work. If a clean base is needed, create an **isolated worktree/branch from latest `origin/main`** — never reuse an old local branch just because it exists.

## Product doctrine

**Feature completion =** User action → authorization → backend execution → persisted real data → result → UI feedback → tests.

No fake-complete features. Forbidden: fake KPIs, fake revenue, fake orders, fake ROAS, fake creator performance, fake AI matching, fake production verification.

**Product Completion > Feature Expansion.** Missing data = **UNKNOWN**, never zero.

## Security / privacy invariants

- **Creator portal** NEVER sees: sell price, internal margin.
- **Client portal** NEVER sees: creator cost, internal margin, creator payout internals, internal sourcing information, banking/private creator information.
- **Client collection ≠ Creator payouts.**
- No auth bypass · no master password · no secret backdoor · no covert impersonation · no silent superuser · no real-user password resets for smoke tests.
- **Platform Owner** may be hidden from normal tenant UI, but **NEVER** from audit/security evidence. Actor identity must always survive preview/impersonation.
- Cross-tenant actions: explicit · authenticated · scoped · audited.
- `TenantScope` is **fail-closed** (no context ⇒ no rows).

## Platform Owner

Preserve the existing architecture (PRs #77–#82); do not rebuild. Inspect current `main` + merged PRs before making claims. Hierarchy: **Platform Owner > System Admin > Tenant Admin**. Dedicated `is_platform_owner` identity — not an ordinary tenant member, no tenant seat impact, no billing impact. Concepts already built: control center, tenant switcher, global search, exact portal/user/entity contexts, request-scoped read-only preview, multi-tab safety, audit. **Do not start P4/P5/P6 unless the current task explicitly requires it.**

## Current active mission — Influencer Nomination (ترشيح المؤثرين)

- **Feature key:** `influencer_nomination` · **Arabic:** ترشيح المؤثرين · **Nav:** الترشيحات
- **Architecture invariant:** ONE nomination engine · ONE data model · ONE permission model · ONE export pipeline · MULTIPLE contextual surfaces.
- **Reuse/extend/consolidate, never duplicate:** AdminPool (`app/Domain/AdminPool`), `PoolMatchService`, Campaigns, Shortlists (`CampaignShortlist*` + `ShortlistService`), Creator DB, existing permissions/entitlements, Export engine (`app/Domain/Exports`), PDFs, client-safe serializers.

### Nomination access model — single canonical decision

`app/Domain/Nomination/Access/NominationAccess` composes: **Feature enabled AND Tenant/scope entitled AND Surface(portal) enabled AND Role/User permitted AND Context valid.** Every reachable surface (server guard + nav share) consumes this one source.

When feature OFF: navigation hidden · actions hidden · direct route = 403 · API = 403 · export = 403 — **but existing persisted nomination data is NOT deleted; re-enable returns the same records.** Availability defaults ON (managed by Platform Owner via `feature_availabilities`).

Capabilities: `influencer_nomination.{view,create,update,manage_candidates,approve,export,share,client_view,manage_feature}` (see `app/Domain/Nomination/Support/NominationAbilities.php`).

### Delivery sequence (do not skip phases with UI placeholders)

N1 feature/entitlement architecture + audit · N2 canonical Nomination domain + consolidation/migration · N3 Nomination Workspace + real matching · N4 contextual mounts (Campaign/Client/Brand/Agency/Platform) · N5 PDF/XLSX/CSV exports · N6 approval + client decision + conversion · N7 platform/admin feature management · N8 cross-browser E2E + production verification.

### Matching truth

Reuse/deepen `PoolMatchService`; score only persisted real fields. Every recommendation exposes `score` + `reasons` + `flags`. Never claim engagement/demographics/conversion/sales/ROAS/performance without reliable persisted data. No fake AI.

### Export invariant

Preview first. Preview and Download of one artifact = same immutable bytes. Underlying change ⇒ mark old artifact stale; Regenerate ⇒ new version (never mutate old silently). Client-safe exports never leak internal financial/private creator info. Arabic RTL. Correct tenant/platform identity.

## Working agreements

- **Do not merge automatically.** Stop before merge for review.
- **Live preview:** every UI must be browsable during dev; no backend-only "done".
- Run the gate before claiming done: `php artisan test` · `./vendor/bin/pint --test` · `./vendor/bin/phpstan analyse` · `npm run typecheck` · `npm run lint` · `npm run build` · E2E where applicable.
- Update `docs/AI/SESSION-STATE.md` at major milestones and before ending a long session. Track authoritative pointers in `docs/AI/SOURCES.md`.

## Context compaction

Auto-compaction is configured at **project level** via `.claude/settings.json` → `env.CLAUDE_AUTOCOMPACT_PCT_OVERRIDE = 70` (begin auto-compaction ≈70% of the context window). Never disable auto-compaction (`DISABLE_AUTO_COMPACT` / `DISABLE_COMPACT` are forbidden).

**After any compaction, before editing code:** re-read this `CLAUDE.md` → re-read `docs/AI/SESSION-STATE.md` → `git status` → current branch → `HEAD` → `origin/main` → verify active PR/CI if any → reconcile the compacted summary with Git. Compaction must preserve (verbatim, resumable): canonical repo, working dir, branch, base/HEAD/origin-main SHA, working-tree state, uncommitted files, current task/phase/acceptance, active PR + head SHA + state + CI + merge state, production deployed SHA + verification, explicit user decisions and prohibitions, completed work, open blockers, review findings, test/build/typecheck/browser results, sources used, next exact step, and this CampaignsHub separation invariant.
