# BRAND-IDENTITY-AUDIT

Goal: anyone opening the website, receiving an email, viewing a PDF, downloading a
file or using a portal recognizes one professional product — **InfluencerHub ·
influencerhub.io**. The tenant (agency) keeps its own legal/business identity on
financial & legal documents; InfluencerHub appears only as the platform («Powered
by»). No invented legal data (company name / CR / VAT / address / phone).

Canonical source: `config/influencerhub.php` + `App\Support\Brand` (name, url
`https://influencerhub.io`, domain, locale `ar`, timezone `Asia/Riyadh`, mail
sender, support). React reads the Inertia `brand` shared prop.

Status vocabulary: `PRODUCTION_VERIFIED` · `INTERNAL_VERIFIED` · `BLOCKED_EXTERNAL` · `NOT_APPLICABLE` · `— (pending)`.

| Surface | Current identity (before) | Problem | Canonical identity | Tenant-specific? | Contact source | Fixed (PR) | Verified |
|---|---|---|---|---|---|---|---|
| Brand config | none (scattered) | identity hardcoded per surface | `config/influencerhub.php` + `Brand` helper | No | config | #60 | INTERNAL_VERIFIED |
| PDF footer (all 6 docs) | «إنفلونسر هَب — منصّة…» | inconsistent wordmark, no URL | «تم إنشاء هذا المستند عبر InfluencerHub · influencerhub.io» every page | No (platform) | config | #60 | INTERNAL_VERIFIED (live footer visual pending) |
| PDF metadata | mpdf/blank | framework leak / empty | Author/Creator/Title/Subject = InfluencerHub | No | config | #60 | INTERNAL_VERIFIED |
| PDF header / body (tenant) | org name | — (correct) | **tenant org name stays owner** | **Yes** | org.name | (unchanged) | PRODUCTION_VERIFIED (contract 2 visual) |
| Email shell + templates | «إنفلونسر هَب» | inconsistent wordmark, no URL | InfluencerHub + tagline + influencerhub.io footer link | No | config | #60 | INTERNAL_VERIFIED |
| Email subjects / CTA (Mail classes) | «— إنفلونسر هَب» | inconsistent | InfluencerHub | No | config | #60 | INTERNAL_VERIFIED |
| `MAIL_FROM_ADDRESS` | `no-reply@influencerhub.local` | `.local` in prod convention | `no-reply@influencerhub.io` | No | env/provider | #60 | INTERNAL_VERIFIED (prod sender = provider-verified) |
| Public site wordmark (PublicLayout) | «إنفلونسر هَب» | inconsistent | InfluencerHub + influencerhub.io | No | config | #60 | PRODUCTION_VERIFIED (/info) |
| Public `/info` page | did not exist | no honest product info page | Arabic-first «عن InfluencerHub» + capabilities + influencerhub.io | No | config | #60 | PRODUCTION_VERIFIED |
| App / auth / error / portal layouts wordmark | «إنفلونسر هَب» (30 files) | inconsistent across app | InfluencerHub | No | config/static | #61 (sweep) | INTERNAL_VERIFIED |
| Browser title template (`inertia.tsx`) | `… — إنفلونسر هَب` | inconsistent | `… — InfluencerHub` | No | static | #61 | INTERNAL_VERIFIED |
| Doc controller workspace fallback | `?? 'إنفلونسر هَب'` | literal fallback | `?? Brand::name()` | tenant-first, platform fallback | org.name → config | #61 | INTERNAL_VERIFIED |
| Signup/OTP email subjects (controllers) | «— إنفلونسر هَب» | inconsistent | InfluencerHub | No | static | #61 | INTERNAL_VERIFIED |
| SMS OTP body (Twilio) | «إنفلونسر هَب» | inconsistent | InfluencerHub | No | static | #61 | INTERNAL_VERIFIED |
| Export document filenames | `slug(title).pdf` (weak on Arabic) | not branded / meaningless | `InfluencerHub-<REF>.pdf` (e.g. `InfluencerHub-INV-1-0001.pdf`) | No | Brand helper | #61 | INTERNAL_VERIFIED |
| Showcase tenant labeling | «بيانات تجريبية» badge | — (already honest) | keep «بيئة استعراضية / بيانات تجريبية»; real tenants show their own name | **Yes** | `showcase` flag | (unchanged) | PRODUCTION_VERIFIED |
| Invoice/Contract legal issuer/parties | tenant data | — (must NOT become InfluencerHub) | **tenant data only**; missing legal fields ⇒ warn admin, never fabricate | **Yes** | org.* | (unchanged) | PRODUCTION_VERIFIED (client-safe + tenant-owner tests) |
| Support / contact | none | must not invent an inbox | show verified email only; else «زيارة influencerhub.io» (support null until configured) | No | config (null) | #60 | NOT_CONFIGURED (honest) |
| HTML head: title/description/canonical | none / framework | no title, no description, no canonical | `<title>` InfluencerHub + tagline, meta description, canonical | No | config | #61 | INTERNAL_VERIFIED |
| Favicon / app icons | files existed, not linked | not referenced in head | favicon.ico + ih-icon.svg + apple-touch, one family | No | assets | #61 | INTERNAL_VERIFIED |
| OpenGraph / Twitter meta | none | not shareable/branded | og:site_name/title/description/url + twitter card | No | config | #61 | INTERNAL_VERIFIED |
| Web manifest | already InfluencerHub | — | InfluencerHub + branded icons | No | assets | (existing) | PRODUCTION_VERIFIED |
| Error pages 404/403/429/500/503 | Laravel defaults (only 419 custom) | framework/blank error pages | branded Arabic pages via `<x-error-shell>` + influencerhub.io + safe nav; no stack/framework leak | No | static | #61 | INTERNAL_VERIFIED |
| XLSX/CSV export filenames | `clients-YYYYMMDD` | not branded | `InfluencerHub-clients-YYYYMMDD.<ext>` (ExportService prefixes) | No | Brand helper | #61 | INTERNAL_VERIFIED |
| Invoice/contract readiness warning | n/a | — | documents print only existing tenant data (org name); **no legal fields (VAT/CR/address) are printed**, so nothing to fabricate or warn about | tenant | org.name | — | NOT_APPLICABLE (no legal field in current doc semantics) |
| Org legal fields in Settings | n/a | — | not added — current documents don't use legal name/VAT/CR; adding would invent unspecified requirements | tenant | — | — | NOT_APPLICABLE (until a real business/legal need is specified) |

## Real public contact data + global footer + policy pages (final pass)

Owner-canonical source of truth (NOT demo values — must never be removed or classified placeholder):
public email **info@influencerhub.io**, public phone **+966550137003** (stored raw; displays `+966 55 013 7003`).

| Surface | Before | After | Source | Verified |
| --- | --- | --- | --- | --- |
| Canonical contact email | fake `info@influencerhub.sa` → then nulled | **info@influencerhub.io** (`Brand::publicEmail`) | config/env | PRODUCTION path (footer/pages) |
| Canonical contact phone | fake `+966 55 000 0000` → then nulled | **+966550137003** raw, display `+966 55 013 7003` | config/env | INTERNAL_VERIFIED (local browser) |
| Transactional sender (distinct) | — | `no-reply@influencerhub.io` (never shown as public contact) | config | asserted ≠ publicEmail |
| Global app footer (all portals) | none | one `AppFooter` in `AppShell`: privacy/terms/help + © year InfluencerHub + influencerhub.io; normal-flow, soft divider, neutral z-index, RTL, mobile-centered | shared `brand` prop | INTERNAL_VERIFIED (/app agency; DOM: below content, 1px divider, z=auto) |
| Public site footer | policy links only | + تواصل column (email + phone) | shared brand | INTERNAL_VERIFIED |
| /privacy /terms /help /info | existed, no real contact | real contact channel via shared `PublicContact` (no hardcoding); own titles + brand-domain canonical | shared brand | INTERNAL_VERIFIED (200; `الخصوصية — InfluencerHub`; canonical `https://influencerhub.io/terms`) |
| Per-page canonical/description | root static (serving host) duplicated | single canonical on **brand domain**; removed root static dup | PublicLayout head-key | INTERNAL_VERIFIED (canonCount=1) |
| Mail shell footer | website only | + public email + privacy/terms/help links | Brand helpers | test-verified |

Note: brand-domain canonical requires `PRODUCT_URL=https://influencerhub.io` (set in deploy env); locally it reflects the dev origin.

## Classification of repo identity tokens (step 15)
- `إنفلونسر هَب` (old Arabic wordmark) — USER_FACING → replaced with `InfluencerHub` across app/resources (#61); only remaining occurrences are TEST_FIXTURE assertions that intentionally check branding.
- `Laravel` — framework internals only (DEV_ONLY); config fallbacks changed to InfluencerHub.
- `localhost` / `127.0.0.1` — DEV_ONLY (local URLs); production output uses `Brand::url()` = `https://influencerhub.io`.
- `example.com` / `test@` / `demo@` — TEST_FIXTURE (kept; not user-facing).
- `influencerhub.local` — removed from env conventions → `influencerhub.io`.
- `Showcase` — legitimate demo tenant name (labeled «بيانات تجريبية»); not changed.

## Not fabricated (verify-first, never invent)
Legal company name · CR · VAT · physical address · phone · a functioning support
inbox. `Brand::supportEmail()` returns `null` until a real channel is configured;
UI falls back to «زيارة influencerhub.io».

## Remaining internally-executable brand tasks: 0
Everything internally executable is done: brand config, PDF footer/metadata/filenames,
email shell/templates/subjects/from-address convention, public site + `/info`,
wordmark sweep (0 user-facing legacy occurrences), browser title/description/canonical,
favicon/app-icon links, OpenGraph/Twitter meta, branded error pages, XLSX/CSV filenames.

Marked `NOT_APPLICABLE` (would require inventing unspecified data — deliberately not done):
organization legal fields (legal name / VAT / CR / address) and a document-readiness
warning — current invoice/contract documents print only the tenant org name, so there
is no legally-required field to fabricate or warn about.

Marked `BLOCKED_EXTERNAL` (needs a real external provider/channel, cannot be verified in-repo):
- **Operational email sending** — `no-reply@influencerhub.io` is the canonical sender
  convention; it is not `PRODUCTION_VERIFIED` until a real provider accepts a controlled
  message. Production mail is currently `not_configured` (see System Health).
- **Support inbox** — `Brand::supportEmail()` stays `null`; UI falls back to «زيارة influencerhub.io».
