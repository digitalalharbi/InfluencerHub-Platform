# Integration Readiness — InfluencerHub

> Updated 2026-10-25 at `main` `6f02e41`. One provider-readiness model (the canonical ladder is
> `MASTER_COMPLETION_ROADMAP.md` §5; owner-action register is `docs/EXTERNAL_BLOCKERS.md`).
> **"Connected" is never shown from stored credentials alone.** Commissioning
> must prove: credential → auth round-trip → resource discovery → first real
> read/sync → webhook (where applicable) → write/read-back → result visible in
> product.

## Readiness ladder (canonical)

`NOT_CONFIGURED → SANDBOX_VERIFIED → AWAITING_CREDENTIALS → READY_FOR_CONFIGURATION → LIVE_READ_VERIFIED → LIVE_WRITE_VERIFIED`

Code model: `app/Domain/Integrations/Models/IntegrationConnection.php` —
`STATUSES = [not_configured, waiting_for_credentials, waiting_for_approval, connecting, connected, sandbox, limited, degraded, error, expired, disconnected]`;
`CONNECTED_STATUSES = [connected, sandbox, limited]`. Status is set **explicitly**,
never inferred from a stored token (`IntegrationConnectionService`:
«لا يُخترع connected بلا رمز حقيقي»). `AdapterRegistry` starts empty; real adapters
register only when credentials exist; `SyncProviderJob` bails unless `isConnected()`.
Framework **VERIFIED** by `IntegrationDomainTest`(6), `IntegrationControlCenterTest`(5).

## Provider matrix (current truth)

| Provider | Purpose | Readiness | Evidence / note |
|----------|---------|-----------|-----------------|
| WhatsApp Business (Meta Cloud) | Messaging, OTP, notifications | **READY_FOR_CONFIGURATION** | `WhatsAppCloudClient` (graph v21.0), webhook HMAC verified; gated `configured()`. Needs Meta app + phone id + token. |
| WhatsApp QR / device pairing | Owner-requested device sync | **UNSUPPORTED_BY_APPROVED_PROVIDER** (pending) | Meta Cloud API has no device-pair/QR. COMMS-03 records this unless a compliant provider offers it. No unofficial WhatsApp-Web scraper — ever. |
| Email (SMTP) | Transactional + notifications | **AWAITING_CREDENTIALS** | `EmailChannel` code-complete; needs SMTP creds. |
| SMS / Twilio Verify | OTP | **AWAITING_CREDENTIALS** | `SmsChannel`, `TwilioVerifyOtpSender`; `OtpDeliveryTest`. |
| Google OAuth (Login) | Sign-in | **READY_FOR_CONFIGURATION** | `GoogleAuthController`, routes `auth.google.*`, `config/services.php`. Built; needs client id/secret. |
| Google Drive / Ads / Analytics | Data sources | **NOT_CONFIGURED** | Not built. |
| Snapchat / TikTok / X / LinkedIn / YouTube / Instagram | Creator platform data | **NOT_CONFIGURED** (`available_manual`) | `config/platforms.php`; `PublisherConnectors` forces live-discovery → `waiting_for_credentials`. Manual/import only today. |
| Payment gateway (Moyasar / Stripe / Mada / Apple Pay) | Billing + payout settlement | **NOT_CONFIGURED** | `BillingProvider` contract + only `FakeBillingProvider`; moyasar/stripe are config options with no class; **no payment webhook controller**. |
| First-party tracking (attribution) | Click/event capture | **NOT_STARTED** (TRACK units) | New native layer — build before any ad-platform forwarding. |
| Meta / Snap / TikTok / Google / X / ChatGPT Ads CAPI (server-side events) | Conversion forwarding | **NOT_STARTED** (TRACK-04) | Provider-neutral pipeline first; adapters gated by readiness ladder. |

## Rules (enforced)

1. Never render "Connected" / "LIVE_VERIFIED" without a real round-trip visible in the product.
2. Sandbox can never report a live verdict; sandbox state is visibly labelled.
3. No provider capability may be invented; capability flags gate UI actions.
4. Payment "paid" is never set from a redirect/UI — only from verified settlement (webhook + amount/currency re-check + backend read-back + audit). Today `markPaid()` records a manual reference only.

## Foundation status (INTEGRATIONS-01)

The provider-neutral foundation is built and fake-adapter-tested:

- `IntegrationConnection` — encrypted `access_token`/`refresh_token` (hidden), `token_expires_at` +
  `tokenExpired()`, scopes/capabilities/health, sync timestamps, safe `last_error`.
- **Readiness ladder** — `IntegrationConnection::readiness()` derives one of
  `NOT_CONFIGURED → SANDBOX_VERIFIED → AWAITING_CREDENTIALS → READY_FOR_CONFIGURATION →
  AUTH_VERIFIED → RESOURCE_DISCOVERY_VERIFIED → LIVE_READ_VERIFIED → LIVE_WRITE_VERIFIED` from real
  signals (status/token/discovered-account/last-successful-sync/live-write flag). **Token alone ≠ Connected.**
- **OAuth lifecycle** — `SupportsOAuth` adapter contract + `OAuthFlowService`: `begin` (state + PKCE
  S256, single-use state, 10-min TTL) → `complete` (state verification + code exchange + encrypted
  token persist, tenant-scoped) → `refresh` → `revoke`. Providers implement `SupportsOAuth`.
- Sync: `IntegrationConnectionService::runSync` (cursor/incremental, rate-limit tracking, health),
  `SyncProviderJob` (`tries=3`, backoff `[30,120]`), `AdapterRegistry`.
- Webhooks: `IntegrationWebhookEvent` with `unique(provider,event_id)` idempotency + `signature_valid`.
- Object mapping: `external_object_map` unique per `(tenant,provider,type,external_id)`.

Tests: `IntegrationDomainTest`, `OAuthFlowTest`, `IntegrationControlCenterTest`. Real provider
adapters (Google/Meta/TikTok/…) are built in their own units and recorded in `EXTERNAL_BLOCKERS.md`
until live credentials/approval arrive.

## Google Workspace (GOOGLE-01)

First real provider on the foundation — Drive · Sheets · Gmail · Calendar — **extending
`IntegrationConnection` with no parallel architecture**: provider `google`, one OAuth connection per
tenant, granted capabilities on `capabilities`, cursors/watch/sync-tokens in `meta`, Drive file
links via `external_object_map`.

- **Adapter** — `GoogleWorkspaceAdapter` (`IntegrationAdapter` + `SupportsScopedAuthorization`):
  incremental Google consent (least-privilege scopes per requested capability via `GoogleScopes`),
  `exchangeCode`/`refreshTokens`/`revokeTokens` delegating to a swappable `GoogleClient`
  (`HttpGoogleClient` real — fails closed without creds; `FakeGoogleClient` sandbox/tests).
- **Capabilities** — Drive (list/metadata/upload/download/permissions/share + doc↔creator/campaign
  link), Sheets (creator import: mapping/preview/validation/dedup/conflict/commit reusing
  `CreateCreator` + shortlist/report export), Gmail (threads/messages/send/reply/attachments/
  history-sync + contact matching + timeline), Calendar (calendars/availability/typed-kind CRUD/
  sync + timeline).
- **Timeline** — `IntegrationExternalEvent` persists real Gmail/Calendar events; `CreatorTimeline`
  reads them (no fabrication; `unknown ≠ 0`).
- **Reachable** — OAuth connect/callback at `/app/integrations/google/*` (admin-gated).
- **Readiness** — SANDBOX_VERIFIED (FakeGoogleClient, `GoogleWorkspaceTest` 18 tests). Live Google =
  `BLOCKED_EXTERNAL_CREDENTIALS` + `BLOCKED_PROVIDER_APPROVAL` (restricted Gmail scopes). Exact owner
  inputs: `docs/EXTERNAL_BLOCKERS.md`.
