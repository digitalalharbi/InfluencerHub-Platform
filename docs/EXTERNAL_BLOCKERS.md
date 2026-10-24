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

_No integration unit has shipped its internal build yet. Entries are added here as each unit
(GOOGLE-01, COMMS-01, SOCIAL-01/02, ADS-01/02/03, COMMERCE-01, FINANCE-01, …) completes._

Planned coverage (roadmap): Google Drive/Sheets/Gmail/Calendar · WhatsApp Business · Instagram ·
TikTok · YouTube · X · Snapchat · LinkedIn · Meta Ads · TikTok Ads · Snapchat Ads · Google/YouTube
Ads · X Ads · LinkedIn Ads · OpenAI Ads · Salla · Zid · Email (SMTP) · SMS/OTP · SaaS billing +
payment settlement · e-signature provider.
