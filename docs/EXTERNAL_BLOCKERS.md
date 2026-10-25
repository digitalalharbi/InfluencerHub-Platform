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

### Communications — WhatsApp Business · Email · SMS (COMMS-01)

Internal build **complete** and fake-tested. The outbound notification layer already existed
(channels, delivery attempts, status webhook). COMMS-01 adds the **two-way relationship layer**:
inbound WhatsApp ingestion (idempotent via `integration_webhook_events`), sender→creator/client
matching (`ContactMatcher`, phone digit-normalized), `message_in`/`message_out` events persisted as
`IntegrationExternalEvent` feeding `CreatorTimeline`, free-form `sendText` + template send
(`ConversationService`), and WhatsApp/Email/SMS represented on the readiness ladder
(`CommsConnectionService`, status derived from real config — never invented). No live message is
sent/received until the owner supplies the values below.

```
Provider: WhatsApp Business (Meta Cloud API, Graph v21.0)
App / Account: Meta app + WhatsApp Business Account (WABA) + a registered business phone number
Required credentials (config/channels.whatsapp):
  - WHATSAPP_PHONE_NUMBER_ID
  - WHATSAPP_ACCESS_TOKEN (System User long-lived token)
  - WHATSAPP_BUSINESS_ACCOUNT_ID
  - WHATSAPP_APP_SECRET (verifies X-Hub-Signature-256 on inbound webhook)
  - WHATSAPP_VERIFY_TOKEN (webhook subscribe handshake)
  - CHANNEL_WHATSAPP_ENABLED=true
Scopes / features: whatsapp_business_messaging, whatsapp_business_management; approved message
  templates for out-of-session initiation (free-form text only inside the 24h service window).
Redirect URL: n/a (long-lived token, not OAuth redirect).
Webhook URL: https://influencerhub.io/api/webhooks/whatsapp (GET verify + POST receive; subscribe
  the WABA to the `messages` field).
Approval status: Meta app review for the messaging permissions + business verification; each
  template requires Meta approval.
Sandbox / live status: SANDBOX_VERIFIED (fake WhatsApp client, CommunicationsInboxTest). Live = AWAITING.
State: BLOCKED_EXTERNAL_CREDENTIALS + BLOCKED_PROVIDER_APPROVAL (app review + template approval)
Next commissioning action (owner): create the Meta app + WABA, register the number, generate a
  System User token, set the env vars above, subscribe the webhook to `messages`, submit the app +
  templates for review.
```

```
Provider: Email (transactional SMTP / provider)
Required credentials: MAIL_* (host/port/username/password/from) or provider API key; CHANNEL_EMAIL_ENABLED=true
Inbound: optional — two-way email relationship events arrive via GOOGLE-01 Gmail (already built);
  a non-Google inbound-email provider (e.g. SES inbound/Postmark) would be a later increment.
State: BLOCKED_EXTERNAL_CREDENTIALS
Next commissioning action (owner): provide SMTP/provider credentials; enable the channel.
```

```
Provider: SMS / OTP (Twilio)
Required credentials (config/services.twilio): TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_VERIFY_SID,
  TWILIO_WHATSAPP_FROM (optional); CHANNEL_SMS_ENABLED=true
State: BLOCKED_EXTERNAL_CREDENTIALS
Next commissioning action (owner): provide Twilio account SID + auth token (+ Verify service SID);
  enable the channel.
```

_Remaining planned coverage (roadmap): Instagram · TikTok · YouTube · X · Snapchat · LinkedIn ·
Meta Ads · TikTok Ads · Snapchat Ads · Google/YouTube Ads · X Ads · LinkedIn Ads · OpenAI Ads ·
Salla · Zid · SaaS billing + payment settlement · e-signature provider — each added here once its
unit ships its internal build._
