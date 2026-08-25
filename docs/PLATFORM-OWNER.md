# PLATFORM-OWNER

A real Platform Owner / Super-Admin layer for InfluencerHub: one owner account that can
manage and review the whole system, move across all tenants and portals, and (later) view
the system as any real user — **without** knowing user passwords, and **without** any
backdoor. Owner-grade access with security-grade auditing.

This document reflects the **actual** architecture (audited before implementation) and the
staged plan P1–P6. No secrets are committed here.

## Grounding facts (from the code audit)

- **`TenantContext`** (`app/Domain/Tenancy/Support/TenantContext.php`) stores the current
  tenant/org/workspace in **`static` request-scoped** properties — **not** the session. PHP is
  shared-nothing, so context is rebuilt every request and is inherently **multi-tab-safe** as
  long as the *target is carried per-request* (URL/signed token), never as one global session
  value. `withBypass()` / `withTenant()` are the cross-tenant primitives.
- **Tenant resolution** is `SetTenantContext` (alias `tenant`): `is_system_admin` → global
  `bypass(true)` + audit; else the user's first active `OrganizationMembership` (optionally an
  `X-Organization-Id` header, currently unused by the frontend).
- **One `web` session guard**, **database** session driver (→ impersonation sessions are
  listable/killable server-side). No per-portal guards; portals differ by `Ensure*Member`
  middleware + membership role.
- **`is_system_admin`** is a real boolean; `Gate::before` makes it pass every policy. Existing
  system-admin oversight (`/beta/admin`) is deliberately **read-only** — `EnsureAgencyMember`
  blocks system-admins from unsafe HTTP methods inside `/app` (a written decision after a real
  incident). Any impersonation must extend that boundary consciously and auditably.
- **Audit** (`AuditLogger` + `audit_logs`) is **append-only** (Postgres triggers + model
  guards) and auto-captures actor + IP + user-agent + request-id. Adding columns is safe (the
  triggers only block UPDATE/DELETE).
- **No pre-existing impersonation** anywhere. **No MFA** (only vestigial columns; no Fortify).
  No recent-auth. `/login` is currently unthrottled. No secure admin-provisioning command.
- **Authorization** = pure function of (`TenantContext.organizationId`, `user.roleIn(org)`) via
  hardcoded ability matrices (`CrmAbilities`, …). **No central serializer** — client/creator
  safety is per-controller field whitelisting, so previewing the *real* portal controllers is
  automatically safe; the only risk is reusing an agency controller under a client identity.
- **Portal acting-entity link tables** (what a preview must satisfy):
  agency → `organization_memberships` (agency role); client → `client_members` (+ session
  `active_client_id`, request attr `activeClient`); creator → `creators.user_id` (+
  `creator_portal.enabled`, attr `creator`); partner → `external_agency_members` (+ approved
  `external_agencies`, session `active_agency_id`, attr `activeAgency`).

## Capability model

`App\Domain\Platform\Support\PlatformCapabilities` — explicit, testable capabilities gated by
a **dedicated** identity marker, **not** by `is_system_admin` alone:

```
platform.owner · platform.tenants.view · platform.tenants.manage · platform.impersonate ·
platform.portal.preview · platform.global_search · platform.system.manage
```

**Dedicated identity — `users.is_platform_owner` (boolean, default false).** The hierarchy is
**Platform Owner ⊃ System Admin ⊃ Tenant Admin**: an ordinary `is_system_admin` account is
**not** a Platform Owner and is rejected from `/platform`; existing system-admin behavior
(`/beta/admin`) is unchanged. `isOwner(user) = user.is_platform_owner`. A provisioned owner is
also `is_system_admin=true` so `Gate::before`/`withBypass` work, but the `/platform` gate keys
on the dedicated marker exclusively.

`can(user, cap)` grants a capability only when its **feature slice is live** — P1 grants
`platform.owner` (identity/access) only; `impersonate`/`portal.preview`/`global_search` are
*defined* constants but return `false` until their slice ships, so P1 never implies unfinished
functionality is operational (§4). The single check-point lets finer platform roles be added
later without touching controllers/middleware. Every platform action routes through
**authenticated owner → `platform_owner` middleware / capability check → audit** — no hidden
URL, magic param, master password, secret cookie, or permission bypass (§11).

## Context & multi-tab model

Because `TenantContext` is request-scoped, the owner's inspection/impersonation target is
**never** stored as a single session value. Tab A inspecting tenant 14 and tab B inspecting
tenant 22 never collide. The target will be carried **per-URL** under the portal's own mount
prefix (so `base`/`u()` links stay correct), authorized per request, and (for interactive
impersonation) bound to a short-lived, server-side, revocable **impersonation session id**
that is validated — not trusted — on every request.

## Staged plan

- **P1 — foundation + shell (this PR).** `PlatformCapabilities`, `EnsurePlatformOwner`
  (`platform_owner` alias), the `/platform` control center (`ControlCenterController`) with
  **real** cross-tenant counts + recent activity + security events (no fake KPIs), the
  `platform` mount prefix + `platformNav` + `isPlatformOwner` shared prop, and the secure
  `platform:provision-owner` command. Access is owner-only (403 otherwise) and audited.
- **P2 — tenant switcher + real global search** (server-backed across tenants/orgs/users/
  clients/brands/campaigns/creators/contracts/invoices/payouts) + a real command palette
  (the current one is a client-only "jump to page" Alpine widget).
- **P3 — read-only portal preview.** A per-guard "preview" branch that accepts an owner-signed
  target and populates `TenantContext` + the portal request attribute
  (`activeClient`/`creator`/`activeAgency`), reusing `isMethodSafe()` to stay read-only.
- **P4 — interactive impersonation + audit.** Explicit confirmation to go interactive; a
  short-lived (30–60 min) revocable impersonation session; the owner context bar; and the
  added audit columns (`acting_as_user_id`, `organization_id`, `session_id`, `reason`).
- **P5 — multi-tab-safe context hardening + security** (login throttle, recent-auth for
  sensitive actions; MFA if/when adopted).
- **P6 — production verification** via an owner-approved secure path (never ephemeral; the
  Platform Owner credential is **never** rotated by Production Smoke, which keeps its own
  independent QA account — §19).

## Visibility (§2) — enforced, not aspirational

The owner account is **invisible to tenants** and must **stay** that way: it holds no
`OrganizationMembership` / `client_members` / `creators.user_id` / `external_agency_members`
row, so it never appears in any tenant's Team/Client/Creator/Partner lists, seat counts, or
billing user counts. The provisioning command **hard-refuses** to promote a user that has any
of those links (it does **not** silently delete them) — so a Platform Owner is always a
**dedicated standalone account**. It is **not** a stealth account: every access and (later)
every impersonation is written to the append-only audit trail with actor + IP + UA +
request-id.

## Provisioning & recovery (§18)

`php artisan platform:provision-owner {email} --name="…"` — password read from
`PLATFORM_OWNER_PASSWORD` env (non-interactive deploy) or an interactive `secret()` prompt;
minimum 16 chars enforced; sets **`is_platform_owner=true` + `is_system_admin=true`**; audited;
**never printed, never committed, never seeded**. **Refuses tenant-linked users** ("create a
dedicated owner account instead") — never mutates their memberships. Idempotent for a
standalone user. Recovery = re-run the command to reset the password from a fresh secret. This
account is **separate** from the Production-Smoke QA account, the Showcase admin, and all
tenant users — and is never rotated by CI.

## Login security (§5)

`/login` (and the creator/client/partner logins) are now rate-limited before the most
privileged account is introduced: a composite **per-(hashed-email + IP)** limit (20/min) plus a
generous **per-IP** limit (60/min), returning a uniform `429` that does **not** reveal whether
an account exists (no enumeration). MFA is **not** added — the codebase has no Fortify/2FA
implementation yet (only vestigial columns); it is a documented future item, not faked.

## Security boundaries

- Owner passes policies via `Gate::before(is_system_admin)`, but `/platform` is additionally
  gated by the explicit `platform_owner` capability middleware (defence in depth + a clean
  seam for finer roles).
- Impersonation (P3+) never reads/resets/exposes a user password and never logs in with user
  credentials — it is server-side, session-based, read-only by default, time-boxed, revocable,
  and fully audited.
- Client-/creator-safe boundaries are preserved by previewing the **real portal controllers**
  (field-whitelisted), not by bypassing Resources.
- The recent simplification work (navigation 26→9, عملي, Campaign Workspace, finance
  separation, canonical terminology, global footer) is **not** reversed: the Platform Owner
  layer sits *above* the product and never re-exposes tenant complexity to ordinary users.
