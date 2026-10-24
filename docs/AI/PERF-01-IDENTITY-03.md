# PERF-01 + IDENTITY-03 — measured results

Branch: `perf-01-identity-03` (from `main` @ 4ca6c9b). InfluencerHub-Platform only.
Measurement harness: `tests/Perf/BaselineTest.php` (read-only, GET, real middleware).
Run: `DB_DATABASE=influencerhub php artisan test --filter BaselineTest`.

## PERF-01 — server payload & query count (before → after)

Per authenticated agency route. "bytes" = Inertia response size (X-Inertia JSON).

| Route            | Inertia bytes          | Translations (raw / gzip)         | SQL queries |
|------------------|------------------------|-----------------------------------|-------------|
| /app             | 211,334 → **19,287**   | 99KB/25 ns → **~14KB/6 ns**       | 73 → **61** |
| /app/creators    | 208,909 → **18,769**   | 99KB/25 ns → **~15KB/6 ns**       | 27 → **15** |
| /app/campaigns   | 206,530 → **27,187**   | 99KB/25 ns → **~25KB/6 ns**       | 24 → **21** |
| /app/reports     | 207,066 → **13,488**   | 99KB/25 ns → **~11KB/6 ns**       | 85 → **62** |
| /app/payouts     | 207,901 → **15,111**   | 99KB/25 ns → **~12KB/6 ns**       | 16 → **13** |
| /app/automation  | 211,107 → **16,783**   | 99KB/25 ns → **~12KB/6 ns**       | 19 → **16** |

**Inertia payload per navigation: ~207–211 KB → 13–27 KB (≈87–93% smaller).**

### What changed (measured, not guessed)

1. **Translations split** (the dominant win). `HandleInertiaRequests` shared the entire
   dictionary — **25 namespaces, 99,323 raw / 25,269 gzip bytes on every page**.
   Now it ships **CORE only** (common, navigation, actions, statuses, entities =
   8,605 raw / 2,778 gzip), and `InjectPageTranslations` middleware adds *only the
   current component's* namespace(s), mapped in `App\Support\I18n\PageTranslations`
   (derived from real `t()` usage; guarded by `PageTranslationsCoverageTest` so no
   surface can ship an untranslated key). Works for initial HTML (`<script data-page>`)
   and warm X-Inertia JSON; `recentNotifications` stays absent on both.
2. **Notifications on-demand**. `recentNotifications` was an 8-row query on every page;
   now `Inertia::optional()` — loaded only when the bell opens (NotificationBell does a
   partial reload). Unread count stays (1 cheap COUNT).
3. **Context resolved once**. `workspaceName`/`isShowcase`/`navCapabilities` each did a
   separate `Organization` lookup (+tenant); now one `currentOrg()` (org + eager tenant).
4. **NavigationBadges**: 7 COUNTs per request → 30s cache keyed `t{tenant}:o{org}`
   (fail-closed; client-recommendations stay per-request).
5. **CreatorAnalytics::summary**: 10 COUNT queries → **1** `count(*) FILTER (...)`
   aggregate (exact-same truth; used on dashboard/creators/reports).
6. **Dashboard 8-week trend** & **Reports 6-month timeline**: `pluck()` + PHP grouping
   (pulled every row) → `date_trunc('week'|'month')` grouped SQL (UTC — matches
   `app.timezone=UTC` + `timestamp without time zone`). Returns only the buckets.
7. **Reports dedup**: `ClientAnalytics::forPage` ran 3× per request (reportDocMeta,
   operational, index) → computed once and reused.

`CampaignAnalytics::summary` was already consolidated (single groupBy + 2 composite
queries) — left unchanged per "optimize only when measurable."

### First-load application assets — measured bottleneck

Authenticated shell first-load (gzip): `inertia.js` **106 KB** + `app.css` **31.6 KB**
+ `AppShell` **14.4 KB** + lazy page chunk (~5–9 KB).

- Pages are **already code-split** (each `Pages/*.tsx` is its own lazy chunk); AppShell
  is its own chunk; lucide is **tree-shaken** (~60 named icons, one import); the command
  palette is 95 lines and platform-only.
- `inertia.js` (106 KB gz) is **React + ReactDOM + @inertiajs/react runtime** — the
  framework floor, not reducible without rewriting React (explicitly out of scope).
- The actionable first-load win *is* captured: the translation split cut the initial
  HTML `data-page` by ~190 KB raw / ~22 KB gzip.

**Per the acceptance note, warm-navigation payload/server-response was prioritized and
cut ~90%; for first-load assets the React/Inertia runtime dominates and the reducible
HTML-payload portion is already removed.** A per-dependency bundle-visualizer pass is the
next lever if deeper asset cuts are wanted.

## IDENTITY-03 — one canonical design system

Emerald (IDENTITY-02) removed as the brand. New: **Midnight Navy + cool neutrals +
refined indigo-violet (#5B57D9)**; green is **semantic success only** (#33966F).

- `tokens.css` rewritten as **one** canonical layer (light + dark), owner's exact palette
  mapped to the existing semantic token names — no stacked purple/lime/emerald blocks.
- Light: canvas #F5F6FA, sidebar #F0F2F7, surfaces #FFFFFF/#ECEFF5/#E5E8F0,
  text #1A1D29/#656B7C/#9298A8, brand #5B57D9 (hover #4D49C8, soft #EEEDFC, ink #3F3B9E),
  nav-active #EEEDFC/#4541AE.
- Dark: canvas #11131A, sidebar #0D0F16, surfaces #181B24/#202430/#282D3A,
  text #F3F4F8/#B0B5C3/#7E8494, brand #8883EF (soft #29264C, ink #D7D4FF),
  nav-active #29264C/#D7D4FF — same brand, not another skin.
- Semantic: success #33966F, warning #D5963F, danger #CC5C68, info #527FBB.
- Charts: indigo / blue (#557DB8) / slate (#7B8498) / sand (#C39D6B) + success/danger
  reserved for meaning (no green as a generic highlight).
- Browser chrome theme-color: light #F5F6FA, dark #11131A (never saturated violet).
- Applied to app, auth gradient (navy + subtle violet/blue atmospherics), login gateway,
  brand logo (midnight/indigo mark, soft-violet signature dot — no green dot), error
  shells, email templates, Inertia progress bar (#5B57D9), and the PWA manifest.
- Legacy `#3FAE86/#4BB890` emerald and `#5B45E0/#6252E5/#22D3EE` indigo/cyan: **zero**
  in all built assets.

## Verification

- Full backend suite: **1446 passed** (after fixing `InjectPageTranslations` to preserve
  `$response->original` so Inertia test assertions keep working).
- `BrandIdentityTest` / `PwaManifestTest` / `DesignSystemPreviewTest` /
  `PageTranslationsCoverageTest`: green (updated to IDENTITY-03).
- `npx tsc --noEmit`: clean. Build: clean.
- Browser (local): dashboard + login light **and** dark — indigo-violet brand, cool
  neutrals, no green; translations resolve (no raw keys) on initial HTML and warm nav;
  notification bell loads on open.

## Frontend / CSS consolidation (same branch, follow-up pass)

The backend/warm-nav wins above were accepted; this pass removes the historical CSS
debt and trims the first-load bundle.

### One canonical system — superseded layers deleted
Removed the three stacked identity blocks (`RADICAL-IDENTITY-01`, the duplicate
`IDENTITY-03` "unified" block, and the `IDENTITY-03 authoritative` override block). Each
component's final value now lives **once**, in its own section:

| Selector | Before | After |
|---|---|---|
| `.btn-primary` (+ variants) | 3 definitions | **1** (buttons section) |
| `.ih-side .nav-link.active` | 4 | 2 (generic + sidebar) |
| `.field` | 3 | **1** |
| `.ih-tile__icon--primary` | 4 | **1** |
| `.ih-sectionbar__count` | 4 | **1** |
| `.ih-setup-cta` | 3 | **1** |

Structural token overrides (radius / shadow / ring / sidebar-width) moved out of app.css
into `tokens.css`. `app.css`: **1555 → 1443 lines; 113.0 KB → 104.6 KB raw;
21.7 KB → 20.2 KB gzip**. ~9 superseded/duplicate rule blocks removed.

### Legacy brand aliases gone
`--ih-lime` / `--ih-lime-soft` / `--ih-lime-ink` / `--ih-lime-600/700` (indigo values
hiding under a "lime" name) **migrated to `--ih-accent*`** across app.css, Charts.tsx,
Dashboard.tsx, then deleted from tokens.css. `--ih-emerald*`: none. Repo-wide scan of
built assets: **0** `ih-lime` / `ih-emerald` / `3FAE86` / `4BB890`. Brand = indigo-violet,
success = green, and they are never conflated.

### First-load CSS trimmed (measured)
`gateway.css` (login gateway, `.gw-*`, used only by `Public/Gateway` + `Public/Start`) was
`@import`ed into the global bundle — every authenticated page downloaded it. Moved to a
per-page `import` so Vite code-splits it:

| Bundle (gzip) | Before | After |
|---|---|---|
| main app CSS | **31.66 KB** | **28.53 KB** (−9.9%) |
| gateway (loads only on 2 public pages) | — | 3.37 KB |

`public.css` was **kept** global — it is used by authenticated Admin/Platform/Invoices/
BrandPortal pages too, so splitting it would break them.

### First-route / fonts / images / prefetch
- **Bundle**: pages already code-split, AppShell its own chunk, lucide tree-shaken, command
  palette platform-only (95 lines) — no first-paint eager load worth splitting for >1–2 KB.
- **Fonts**: already optimal — `preconnect` + `display=swap`, only the used weights
  (Plex Sans/Arabic 400–700, Mono 400–500); no change.
- **Images**: thumbnails/avatars use `loading="lazy"` inside CSS-sized containers (no
  attribute CLS).
- **Prefetch**: hover-prefetch added to the primary sidebar destinations (Inertia `<Link>`;
  Blade/external items stay `<a>`). Not applied product-wide.

### Re-verified (no regression)
PERF baseline **identical** (/app 19,287 B / 61 q, etc.); full backend **1446 passed**;
`tsc` clean; browser QA of dashboard (dark) + creators (light) — indigo-violet, cool
neutrals, green only for success/status, identical to the approved IDENTITY-03.

`CampaignsHub touched = 0 · digitalSmart touched = 0`.
