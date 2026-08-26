# Notifications & Email — Product-Wide Audit

> Verified from source (call sites, migrations, routes) on 2026-08-26 against
> `origin/main@244851f`. Honest coverage: what is really wired vs. defined-but-not-wired.
> **Do not build a second notification system** — everything below already exists.

## Architecture (existing, canonical — reuse it)

`NotificationService::notify/notifyMany` → `DeliveryDispatcher::dispatch` → `ChannelRegistry`
(`InAppChannel`, `EmailChannel`, `WhatsAppChannel`, `SmsChannel`) → each writes a
`notification_delivery_attempts` row via `DeliveryDispatcher::record`.

- **`notifications`** table: `type`, `category`, `title`, `body`, `action_url` (deep link), `data` (json), `subject` morph, `read_at`. Deep link opened server-side on read (`NotificationController::read` → `redirect(action_url)`), IDOR-scoped to `user_id`.
- **`notification_delivery_attempts`**: `channel`, `provider`, `recipient`, `provider_message_id`, `status` (`sent|queued|delivered|failed|skipped|waiting_for_credentials`), `queued_at/delivered_at/read_at/failed_at`, `retry_count`, `failure_code`.
- **Preferences**: `notification_preferences` (`category` × `in_app/email/sms/whatsapp`), resolved in `DeliveryDispatcher` (`$pref->{channelKey}`).
- **In-app**: shared prop `unreadNotifications` (topbar bell), per-portal notification centers (agency center shows per-channel delivery chips), mark-read / mark-all-read, `paginate(20)`.

## Event matrix (real triggers, proven call sites)

`in_app` is always on (default pref true, channel always available). `email`/`whatsapp` are **pref-gated AND config-gated** uniformly (`CHANNEL_EMAIL_ENABLED` default **false**, WhatsApp needs Meta creds, SMS is a stub). So the email/whatsapp columns reflect the shared mechanism, not per-type wiring.

| category | event type | trigger (file) | recipient | in-app | email | deep link |
|---|---|---|---|---|---|---|
| general | `service_request.assigned` | ServiceRequestWorkflowService | new assignee | ✓ | pref+cfg | `/app/service-requests/{id}` |
| general | `sla.alert` (reminder + breach) | SlaEngineService (`sla:scan` hourly) | assignee, else agency_admin/operations_manager | ✓ | pref+cfg | request URL |
| general | `contract.{sent,signed,…}` | ContractWorkflowService | creator, client members, owner | ✓ | pref+cfg | `/app/contracts/{id}` |
| general | `collaboration.{offered,…}` | CollaborationWorkflowService | owner, creator | ✓ | pref+cfg | `/app/collaborations/{id}` |
| general | `content.{submitted,client_review,update}` | ContentWorkflowService | creator, reviewers, client | ✓ | pref+cfg | `/app/content/{id}` etc. |
| general | `payout.{state}` | PayoutWorkflowService | creator | ✓ | pref+cfg | payout URL |
| general | `shortlist.item_{approved,rejected}` | ShortlistService | campaign owner (agency) | ✓ | pref+cfg | `/app/campaigns/{id}/shortlist` |
| general | `creator_invitation.accepted` | CreatorInvitationService | inviter | ✓ | pref+cfg | `/app/creators/{id}` |
| general | `brand_claim.*`, `brand_delegation` | BrandClaimService, AgencyDelegationService | claimant/members | ✓ | pref+cfg | `/brand` |
| general | `automation.notice` | Automation `NotifyAction` (rules) | templated user | ✓ | pref+cfg | templated |
| brands | `brand.{approved,changes_requested}` | BrandDetailController → ClientNotifier | client members | ✓ | pref+cfg | `/client/brands/{id}` |
| profile | `profile.change_{approved,rejected}` | ClientReviewsController → ClientNotifier | requester | ✓ | pref+cfg | review URL |
| documents | `document.{decision}` | ClientReviewsController → ClientNotifier | client members | ✓ | pref+cfg | doc URL |

### Defined-but-NOT-wired (honest gaps)

1. **Category taxonomy mismatch (biggest gap).** Three disagreeing lists:
   - `NotificationController::CATEGORIES` (prefs UI): `tasks, campaigns, client_approvals, creator_invitations, content_reviews, publishing, finance, integrations, system, general`.
   - `NotificationPreference::CATEGORIES`: `brands, documents, profile, team, billing, general`.
   - **Actually emitted**: mostly `general`, plus `brands/profile/documents`.
   → Users can toggle categories that no event emits (`tasks`, `finance`, `team`, `billing`…), and real traffic is `general`, so per-category preference filtering is largely decorative. **Recommended fix**: one canonical `NotificationCategory` enum; map each emitted event to a real category; drive both prefs screens + the emitters from it.
2. **`SmsChannel`** — always `waiting_for_credentials` (no provider). Wired but dormant.
3. **`WhatsAppChannel`** — fully coded, dormant behind `CHANNEL_WHATSAPP_ENABLED` + Meta-approved templates (`BLOCKED_EXTERNAL`).
4. **`retry_count`** and `notification_delivery_attempts.read_at` — columns exist, never written (no retry loop; no read-receipt for email/whatsapp).
5. **Email delivery status never advances** past `queued` for the email channel — see email audit below (this is a truthful *under*-claim, not a false claim).

## Email (see also code under `app/Mail`, `resources/views/mail`, `components/mail`)

- 3 Mailables: `NotificationMail` (queued, generic), `SignupDecisionMail`, `CreatorOtpMail` (not queued). Plus raw `Mail::send('mail.verification-code', …)` in 3 signup controllers (no delivery-attempt record).
- **ONE** base layout `components/mail/layout.blade.php` (dark header, inline SVG logo, RTL, Brand footer with privacy/terms/help). 5 content views. No layout duplication.
- Mailer default `log` (dev-safe); `smtp` points at `127.0.0.1:2525` (Mailpit-style). From = `no-reply@influencerhub.io` / `InfluencerHub`.

### Email honesty verdict
- **Correct (no over-claim):** `EmailChannel::send` records `queued` (the Mailable is `ShouldQueue`) — it never falsely records `delivered`. The delivery-attempt table distinguishes `queued/sent/delivered/failed/skipped/waiting_for_credentials` truthfully.
- **Gap (under-claim):** nothing advances the email attempt `queued → sent` after the transport accepts it, and `provider_message_id` is never captured for email. **Recommended enhancement**: a `MessageSent` listener keyed by an `X-IH-Notification-Id` header advances the attempt to `sent` + records the provider id (do not claim `delivered` without a provider webhook).

## Follow-up feasibility (honest — do NOT invent deadlines)

| domain | real deadline column | reminder markers | scanner | real follow-up possible? |
|---|---|---|---|---|
| **Service requests** | `due_at` | `sla_reminded_at`, `sla_breached_at` | `sla:scan` hourly → `SlaEngineService` | **YES — already built** (the reference pattern) |
| Invoices / collections | `due_date` | none | none | buildable (needs marker columns) |
| Payouts | `due_date` | none | none | buildable |
| Collaborations | `due_date` | none | none | buildable |
| Contracts | `end_date` | none | none | buildable (expiry reminders) |
| Creator invitations | `expires_at`, `last_sent_at` | none | none | buildable (expiring/resend) |
| **Shortlist client decision** | **none** | — | — | **NO** — no persisted deadline; "overdue" would be invented |
| **Content review** | `scheduled_at` (soft) only | — | — | **NO hard review deadline** |

- **Idempotency**: real only on two paths — Automation `automation_runs.event_key`, and SLA `*_reminded_at`/`*_breached_at`. **Base `NotificationService` has NO dedup** (bare `Notification::create`). Any new follow-up must bring its own marker or route through the automation event-key gate.
- **Escalation**: only `assigned_to` / `clients.account_manager_id` + a runtime role fallback (`agency_admin`/`operations_manager`). No `manager_id`/escalation tier — multi-level escalation is not modelled.

## Privacy (email must obey portal boundaries)

- One reusable client-safe serializer: **`PoolCreator::toSharedArray()`** (never exposes cost/margin/store/source/banking). `toBookingArray()` is agency-only — never reuse in client/creator email.
- Shortlist client view + creator portal projections are **inline per-controller** (no shared safe method) — the main reuse hazard. Client-facing notifications currently carry no financial fields; creator emails carry only the creator's own fee/payout.
- **Template-level guard (added on `feat/notification-email-experience`)**: `NotificationMail` renders only whitelisted `data` keys (`context/status/requester/due/priority/secondary_*`) — arbitrary internal keys (`cost_minor`, `margin`, `internal_note`) can never leak through the template even if an emitter mistakenly attaches them (regression test: `EmailExperienceTest::test_template_does_not_leak_non_whitelisted_data_keys`).
