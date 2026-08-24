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
| Support / contact | none | must not invent an inbox | show verified email only; else «زيارة influencerhub.io» (support null until configured) | No | config (null) | #60 | INTERNAL_VERIFIED |

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

## Remaining (later slices, honest backlog)
- Auth pages visual polish beyond wordmark; branded error pages (403/404/419/429/500/maintenance) content review.
- Settings → organization legal-identity fields with an admin warning when a legally-required field is missing (step 26/28).
- Favicon / manifest / OpenGraph audit on public pages (step 17).
