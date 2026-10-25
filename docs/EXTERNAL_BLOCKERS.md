# External Blockers — InfluencerHub

> Owner-action register for integrations whose internal build is complete but that need
> external credentials or provider approval to go live. Per `MASTER_COMPLETION_ROADMAP.md` §38.
> Last updated 2026-10-25 at `main` `6f02e41`.

## How this file works

For every external integration, the integration unit first builds **everything internal**:
contract · adapter · OAuth lifecycle · callback · token refresh/revoke · webhook · sync ·
reconciliation · health · UI · tests · sandbox/fake. Only then is the provider added below with
an honest state:

- `BLOCKED_EXTERNAL_CREDENTIALS` — code-complete; needs the owner's app keys/secrets/tokens.
- `BLOCKED_PROVIDER_APPROVAL` — code-complete; needs the provider to approve the app/scopes.

Never claim `CONNECTED` / `LIVE_VERIFIED` without real evidence. Each provider is added **once**;
do not re-request data already captured here.

## Entry format (per provider)

```
Provider:
App / Account:
Required credentials:
Scopes:
Redirect URL:
Webhook URL:
Approval status:
Sandbox / live status:
State: BLOCKED_EXTERNAL_CREDENTIALS | BLOCKED_PROVIDER_APPROVAL
Next commissioning action (owner):
```

## Providers (populated as integration units land)

### Google Workspace — Drive · Sheets · Gmail · Calendar (GOOGLE-01)

Internal build **complete** and fake/sandbox-tested: provider-neutral OAuth lifecycle (state +
PKCE S256 + exchange/refresh/revoke), `GoogleWorkspaceAdapter` (incremental consent, one `google`
connection per tenant, capabilities on `integration_connections.capabilities`, cursors/watch in
`meta`), swappable `GoogleClient` (`HttpGoogleClient` real / `FakeGoogleClient` sandbox), Drive
(list/metadata/upload/download/permissions + doc↔campaign/creator link via `external_object_map`),
Sheets (creator import: mapping/preview/validation/dedup/conflict/commit + shortlist/report export),
Gmail (threads/messages/send/reply/attachments/history sync + contact matching + relationship
timeline), Calendar (calendars/availability/typed-kind events/sync + timeline). No live Google call
is made until the owner supplies the values below.

```
Provider: Google (Workspace APIs)
App / Account: Google Cloud project owned by the agency; OAuth consent screen (External or Internal)
Required credentials:
  - GOOGLE_CLIENT_ID (OAuth 2.0 Client ID — Web application)
  - GOOGLE_CLIENT_SECRET (OAuth 2.0 Client Secret)
  - GOOGLE_REDIRECT_URI  (the app's callback route: https://influencerhub.io/app/integrations/google/callback)
  - GOOGLE_GMAIL_PUBSUB_TOPIC  (projects/<gcp-project>/topics/<topic> — Gmail push/watch)
  - GOOGLE_WEBHOOK_URL  (public HTTPS endpoint subscribed to that Pub/Sub topic)
Scopes (least privilege, incremental per capability — requested only for granted capabilities):
  - identity (always): openid, userinfo.email, userinfo.profile
  - Drive:    drive.file, drive.metadata.readonly
  - Sheets:   spreadsheets
  - Gmail:    gmail.readonly, gmail.send
  - Calendar: calendar, calendar.events
  - (optional People): contacts.readonly
Redirect URL: GOOGLE_REDIRECT_URI above (the app route GET /app/integrations/google/callback) —
  must be registered as an Authorized redirect URI on the OAuth client exactly (scheme+host+path).
Webhook URL: GOOGLE_WEBHOOK_URL above — Gmail watch() publishes to the Pub/Sub topic; the topic
  pushes to this endpoint. Required only for live Gmail push; history sync also works on-demand.
Approval status: Google OAuth **consent screen verification required** before external users can
  grant the restricted Gmail scopes (gmail.readonly/gmail.send) — security assessment may apply.
  Drive.file/Sheets/Calendar are sensitive (consent-screen listing) but not restricted.
Sandbox / live status: SANDBOX_VERIFIED (FakeGoogleClient, full suite). Live = AWAITING.
State: BLOCKED_EXTERNAL_CREDENTIALS (also BLOCKED_PROVIDER_APPROVAL for restricted Gmail scopes)
Next commissioning action (owner):
  1. Create/choose a Google Cloud project; enable Drive, Sheets, Gmail, Calendar APIs.
  2. Configure the OAuth consent screen; add the scopes above; submit for verification (Gmail
     restricted scopes need Google review + possibly a CASA security assessment).
  3. Create an OAuth 2.0 Web Client; set the Authorized redirect URI to GOOGLE_REDIRECT_URI.
  4. For Gmail push: create a Pub/Sub topic, grant gmail-api-push@system.gserviceaccount.com the
     Publisher role, create a push subscription to GOOGLE_WEBHOOK_URL; set GOOGLE_GMAIL_PUBSUB_TOPIC.
  5. Provide GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET / GOOGLE_REDIRECT_URI (+ Pub/Sub values) as
     production env; a real OAuth round-trip then advances readiness to AUTH_VERIFIED → LIVE_*.
```

_Remaining planned coverage (roadmap): WhatsApp Business · Instagram · TikTok · YouTube · X ·
Snapchat · LinkedIn · Meta Ads · TikTok Ads · Snapchat Ads · Google/YouTube Ads · X Ads · LinkedIn
Ads · OpenAI Ads · Salla · Zid · Email (SMTP) · SMS/OTP · SaaS billing + payment settlement ·
e-signature provider — each added here once its unit ships its internal build._
