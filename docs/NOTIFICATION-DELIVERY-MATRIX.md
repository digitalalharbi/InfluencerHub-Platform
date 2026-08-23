# Notification Delivery Matrix

Provider-neutral delivery layer. Every notification records one **delivery attempt per channel** with an honest status, the provider, the recipient, a provider message id (when the provider returns one), and lifecycle timestamps (`queued_at`/`delivered_at`/`read_at`/`failed_at`) + `retry_count`.

Statuses: `pending` · `queued` · `sending` · `sent` · `delivered` · `read` · `failed` · `skipped` · `waiting_for_credentials`.

## Channels

| Channel | Provider | `available()` when | Send status today | Notes |
|---|---|---|---|---|
| in_app | `in_app` | always | **PRODUCTION_VERIFIED** — the stored notification *is* the delivery | recipient `user:{id}` |
| email | `smtp` | `CHANNEL_EMAIL_ENABLED=true` (+ mail transport) | `waiting_for_credentials` until the Email-provider unit lands | recipient = user email |
| whatsapp | `whatsapp_cloud` | `CHANNEL_WHATSAPP_ENABLED=true` + `WHATSAPP_PHONE_NUMBER_ID` + `WHATSAPP_ACCESS_TOKEN` | `waiting_for_credentials` until the WhatsApp-provider unit lands (**BLOCKED_EXTERNAL** — needs Meta Cloud API creds + approved templates) | recipient = user phone → E.164 |
| sms | `sms` | `CHANNEL_SMS_ENABLED=true` | `waiting_for_credentials` (no SMS provider wired) | recipient = user phone |

## Architecture (this unit)

- `App\Domain\Communications\Channels\DeliveryChannel` — interface (`key`/`provider`/`available`/`recipientFor`/`send`).
- `InAppChannel`, `EmailChannel`, `WhatsAppChannel`, `SmsChannel` — honest implementations; external channels report `available()=false` until their provider unit implements `send()`.
- `ChannelRegistry` (container singleton, ordered) — swappable per test.
- `DeliveryDispatcher` — for each preference-enabled channel: **provider-availability first** (`waiting_for_credentials` if not configured), then recipient (`skipped` if none), then `send()`. A channel exception never breaks the notification (logged, recorded `failed`).
- Preferences now include **whatsapp** (`notification_preferences.whatsapp`).

## Verification

- `NotificationDeliveryCoreTest` — in_app always sent (with provider + delivered_at); external channels `waiting_for_credentials` when unconfigured (recipient still recorded); an available channel records `sent` + `provider_message_id` + `delivered_at`; a throwing channel records `failed` without breaking the notification; disabled in_app records `skipped`. `INTERNAL_VERIFIED`.
- Existing `NotificationTest` still green (behaviour preserved through the new layer).

## Next units

Email provider (real transport + RTL Arabic branded template), then WhatsApp Cloud API provider (send template + webhook GET verify / POST status + signature + idempotency), then digests + SLA escalation routing.
