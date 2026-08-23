# Integration Capability Matrix

Real integration domain (`app/Domain/Integrations`) — persisted per-tenant connection state + a provider-neutral sync framework. Complements the static `PlatformRegistry` (provider catalog); does not duplicate it.

## Domain

| Model | Purpose |
|---|---|
| `IntegrationConnection` | one row per (tenant, provider, environment): explicit **status**, external account, scopes, **encrypted** access/refresh tokens (hidden from serialization), token expiry, last-success/attempt/next sync, safe last-error, **health**, per-capability status, connected_by/at, disconnected_at |
| `IntegrationSyncRun` | one row per sync: type (initial/incremental/manual/scheduled/webhook/backfill/reconciliation), status, cursor, counters (fetched/created/updated/skipped/failed), rate-limit remaining, retry_count, safe error |
| `IntegrationWebhookEvent` | raw provider event, unique per `(provider, event_id)` for idempotency, signature-valid flag, processing status |
| `ExternalObjectMap` | external object ↔ local record map, unique per `(tenant, provider, external_type, external_id)` |

## Status vocabulary (not derived from static config)
`not_configured` · `waiting_for_credentials` · `waiting_for_approval` · `connecting` · `connected` · `sandbox` · `limited` · `degraded` · `error` · `expired` · `disconnected`. Health: `healthy` · `degraded` · `error` · `unknown`.

## Framework
- `IntegrationAdapter` interface (`provider`/`capabilities`/`sync`) — every real provider implements it.
- `AdapterRegistry` (container singleton) — **empty until a real provider's credentials exist**; a provider with no adapter is never synced (no false "connected").
- `IntegrationConnectionService` — `connect` (explicit status, never invents "connected"), `disconnect` (clears tokens), `runSync` (creates a SyncRun, runs the adapter, records counters + cursor + health, safe-errors and rethrows for retry).
- `SyncProviderJob` (queued, `$tries=3`, exponential `$backoff`) — passed by id (no tokens in the queue payload); skips unconnected connections; runs off the web request.

## Security (VERIFIED)
- Tokens **encrypted at rest** (`encrypted` cast), `$hidden` from JSON, never logged, tenant-scoped, cleared on disconnect. Proven: raw DB column ≠ plaintext; decrypts via model; absent from `toArray()`.
- Webhook events unique per provider event id (idempotency).
- Errors stored safe (class + message, truncated) — no secrets.

## Verification
`IntegrationDomainTest` (6): token encryption/hide; sync run records metrics + health + cursor; failure records safe error + rethrows + health=error; disconnect clears tokens; object-map uniqueness; job skips unconnected. **INTERNAL_VERIFIED.**

## Providers

| Provider | Adapter | Status |
|---|---|---|
| TikTok, Meta/Instagram, Snapchat, YouTube, X, LinkedIn | — | **BLOCKED_EXTERNAL** — need OAuth app credentials + approvals; framework ready, register adapter when creds exist |
| Meta/TikTok/Snapchat/Google/X Ads | — | **BLOCKED_EXTERNAL** |
| Salla, Zid (commerce) | — | **BLOCKED_EXTERNAL** (webhook + reconciliation ready) |
| WhatsApp Cloud (messaging) | delivery channel + webhook shipped (separate unit) | **BLOCKED_EXTERNAL** for live send (Meta creds) |

No misleading production "Connect" controls are exposed for providers that cannot execute; capability status stays honest.
