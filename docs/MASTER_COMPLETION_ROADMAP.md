# InfluencerHub — MASTER COMPLETION ROADMAP

> **Repository:** `digitalalharbi/InfluencerHub-Platform`  
> **Production:** `https://influencerhub.io`  
> **Goal:** إكمال InfluencerHub ليصبح **Influencer Relationship Operating System** متكامل وقابل للبيع SaaS.
>
> القاعدة الأساسية:
>
> `User action → authorization → backend execution → persisted real data → result → UI feedback → tests`
>
> ممنوع عرض أي تكامل أو رقم أو حالة على أنها حقيقية قبل وجود دليل فعلي.

---

## 0. PROJECT FIREWALL

اعمل فقط على:

`digitalalharbi/InfluencerHub-Platform`

CampaignsHub وdigitalSmart = **READ-ONLY benchmarks**.

ممنوع نقل كود/commits/migrations/services/components/branches/worktrees منها.

قبل كل دفعة:

```text
REPO_OK=InfluencerHub-Platform
CAMPAIGNSHUB_TOUCHED=NO
DIGITALSMART_TOUCHED=NO
```

---

## 1. FIRST GATE — CLOSE CURRENT ACTIVE WORK

قبل أي ميزة جديدة:

1. افحص `main` الحالي فعليًا.
2. افحص PRs المفتوحة وCI.
3. إذا كان `#189` ما زال مفتوحًا، أصلح فقط فشل CI الحقيقي.
4. حافظ على PERF-01 وIDENTITY-03.
5. CI كامل أخضر.
6. merge.
7. deploy.
8. production smoke.
9. تحديث docs.
10. بعدها ابدأ برنامج الإكمال أدناه.

لا تبدأ تطويرًا كبيرًا فوق فرع فاشل أو stale.

---

## 2. CANONICAL DESIGN

الهوية المعتمدة:

- Midnight Navy
- Cool Neutrals
- Refined Indigo / Violet
- Controlled Blue
- Green = semantic success only

ممنوع إدخال هوية جديدة أو إعادة الأخضر كلون رئيسي.

يجب أن يبقى Design System واحد فقط بدون طبقات تاريخية متراكمة.

---

## 3. CREATOR 360 — COMPLETE RELATIONSHIP CRM

يجب أن يصبح Creator 360 هو المصدر الرئيسي لكل مؤثر.

### Identity
- الاسم والاسم العام والصورة وBio.
- الدولة، المدينة، اللغات، الفئات، Tags.
- مصدر المؤثر.
- مسؤول العلاقة الداخلي.
- Relationship stage/health.

### Contact
- Phone.
- WhatsApp.
- Email.
- Manager/Agent.
- Preferred channel.
- Consent / Do-not-contact.

### Social Accounts
دعم:
- Instagram
- TikTok
- Snapchat
- YouTube
- X
- LinkedIn
- Threads عند توفر دعم رسمي

لكل حساب:
- username
- public URL
- external platform ID
- followers
- following عند توفره
- posts/videos
- views
- engagement الحقيقي فقط
- audience data الحقيقي فقط
- verification
- source
- last_synced_at
- freshness
- connection state

**Unknown != 0**.

### Commercial
- Rate card.
- Historical rates.
- Platform/deliverable/package pricing.
- Negotiation history.
- Currency.
- Usage rights pricing.
- Exclusivity pricing.
- Payment terms.

### Relationship
- First contact.
- Last interaction.
- Next follow-up.
- Relationship owner.
- Notes.
- Tasks.
- Meetings.
- Issues/disputes.
- Communication preferences.
- Custom fields.

### History
- Campaigns.
- Brands.
- Collaborations.
- Deliverables.
- Content/revisions/approvals.
- Publication proof.
- Contracts/rights/exclusivity.
- Invoices/payouts.
- Tracking/attribution.

---

## 4. CANONICAL RELATIONSHIP TIMELINE

Timeline واحد للمؤثر يشمل:

- WhatsApp
- Gmail/Email
- Instagram messages عندما تدعم رسميًا
- SMS
- Notes
- Calls
- Meetings
- Tasks
- Offers
- Campaign assignments
- Shortlist decisions
- Contracts
- Content submissions/revisions
- Publication proof
- Payments
- Documents
- Automations
- Audit events

لا تنشئ Timeline منفصل لكل قناة.

---

## 5. INTEGRATION FOUNDATION V2

استخدم ووسّع الموجود:

```text
IntegrationConnection
IntegrationAdapter
AdapterRegistry
IntegrationSyncRun
IntegrationWebhookEvent
ExternalObjectMap
SyncProviderJob
```

لا تنشئ Framework موازي.

### Required
- OAuth state
- PKCE عند الحاجة
- encrypted tokens
- refresh/revoke
- scopes
- token expiry
- incremental authorization
- resource discovery
- webhooks
- idempotency
- cursor sync
- incremental sync
- backfill
- reconciliation
- retry/backoff
- rate-limit handling
- health checks
- scheduler
- audit
- safe errors

### Readiness ladder

```text
NOT_CONFIGURED
→ SANDBOX_VERIFIED
→ AWAITING_CREDENTIALS
→ READY_FOR_CONFIGURATION
→ AUTH_VERIFIED
→ RESOURCE_DISCOVERY_VERIFIED
→ LIVE_READ_VERIFIED
→ LIVE_WRITE_VERIFIED
```

Token وحده لا يعني Connected.

---

## 6. SOCIAL CREATOR DATA CONNECTORS

### Instagram / Meta
- OAuth.
- Professional Creator/Business accounts.
- Profile/media/insights/audience عندما تسمح الصلاحيات.
- Refresh/webhooks/health.
- ممنوع الادعاء بدعم الحسابات الشخصية غير المدعومة.

### TikTok
- OAuth.
- Display API.
- Profile/videos/followers/likes/video metrics.
- Authorized insights.
- Refresh/health.

### YouTube
- YouTube Data API.
- Channel discovery/statistics.
- Videos/video statistics.
- Analytics للحسابات المتصلة عند التصريح.

### X
- Profile/posts/metrics حسب مستوى API.
- Connected account.
- Rate-limit handling.

### Snapchat
- استخدم القدرات الرسمية فقط.
- لا Public Discovery وهمي.

### LinkedIn
- Authenticated member identity حيث يسمح رسميًا.
- Organization data عند الحاجة.
- لا scraping.

---

## 7. CREATOR DATA PROVIDER

أنشئ عقدًا محايدًا:

`CreatorDataProvider`

بحيث يدعم مزودات مثل:
- Phyllo
- Modash
- أي مزود معتمد لاحقًا

Capabilities:
- discovery
- profile
- audience
- engagement
- content
- connected account data
- provenance
- freshness
- provider health

كل Metric يحمل:

```text
source
collected_at
verified
freshness
```

---

## 8. GOOGLE WORKSPACE — COMPLETE

لا تكتفِ بـGoogle Login.

أنشئ `GoogleWorkspaceConnection` موحدًا بصلاحيات تدريجية.

### Google Drive
- OAuth.
- File/Folder Picker.
- Upload/download.
- Permissions.
- Campaign folders.
- Creator folders.
- Briefs/contracts/invoices/media kits/content/reports.

### Google Sheets
- Import creators.
- Mapping.
- Preview.
- Validation.
- Dedup/conflicts.
- Re-import.
- Export creator/shortlist/campaign/report.
- Scheduled sync عند الحاجة.

### Gmail
- OAuth.
- Threads/messages.
- Send/reply.
- Attachments.
- Labels.
- Push/watch + history sync.
- Creator/client matching.
- Creator Timeline.
- Audit.

لا تستورد كامل صندوق البريد بلا ضوابط.

### Google Calendar
- Calendars/availability.
- Create/update/delete event.
- Creator meetings.
- Briefings.
- Shoots.
- Content/review/publish deadlines.
- Contract/rights expiry.
- Payout due.
- Follow-ups.
- Attendee sync.

### Google People
جهز Adapter اختياري للـcontacts/matching/dedup.

---

## 9. COMMUNICATION HUB

نموذج موحد:

```text
Conversation
→ Participant
→ Message
→ Attachment
→ Delivery
→ Channel
```

القنوات:
- WhatsApp
- Gmail/Email
- Instagram Messaging عندما تسمح رسميًا
- SMS
- Internal Notes

---

## 10. WHATSAPP BUSINESS PLATFORM

وسّع الموجود حاليًا إلى Inbox كامل:

- WABA/phone connection.
- Templates.
- Inbound webhook.
- Outbound session/template messages.
- Text/image/video/document.
- Interactive buttons.
- Sent/delivered/read/failed.
- Media download.
- Idempotency.
- Conversation creation.
- Creator/client matching.
- Assignment.
- Unread.
- SLA.
- Automation.
- Timeline.

QR/device pairing فقط إذا وجد مزود رسمي compliant.

ممنوع WhatsApp Web scraper غير رسمي.

---

## 11. EMAIL + SMS

### Email
- Production provider.
- SPF/DKIM/DMARC.
- Bounce/suppression/delivery events.
- Reply handling.
- Timeline.

### SMS
- OTP.
- Transactional SMS.
- Saudi-compatible provider.
- Delivery state.
- Consent/opt-out.

---

## 12. DISCOVERY & CASTING OS

- Universal creator search.
- Platform/category/location/language.
- Audience/followers/engagement الحقيقي فقط.
- Pricing/availability.
- Previous brand/relationship history.
- Verified connection.
- Saved filters/views.
- Shortlist tray.
- Side-by-side comparison.
- Nomination reasons.
- Deterministic ranking.
- Human override.
- Audit.

AI يشرح النتائج ولا يخترع بيانات المؤثر.

---

## 13. CAMPAIGN COMMAND CENTER

لكل حملة:
- Brief/client/brand/team.
- Budget/dates/stage/health.
- Creators/nominations/shortlist.
- Collaborations.
- Contracts/rights.
- Content/revisions/approvals.
- Publication proof.
- Tracking/finance/tasks/communications/reports.

كل Workspace يجيب:

**What must happen next?**

One dominant next action.

---

## 14. RIGHTS / LICENSING

أنشئ `UsageRight` مستقلًا:
- creator
- campaign
- content
- platform
- territory
- organic usage
- paid usage
- whitelisting
- exclusivity/category exclusivity
- start/expiry
- fee
- status
- evidence
- contract source

Automations للانتهاء والتجديد.

---

## 15. PUBLICATION PROOF

أنشئ `PublicationProof`:
- creator
- campaign
- collaboration
- deliverable
- platform
- URL
- platform post ID
- evidence/screenshot
- published_at
- fetched_at
- provider
- verified_by
- verification status

لا تعرض Published بلا source/evidence.

---

## 16. ADS HUB — ALL REQUIRED PROVIDERS

أنشئ Domain محايد:

```text
AdConnection
AdAccount
AdCampaign
AdGroup
Ad
AdCreative
AdInsight
ConversionEvent
```

اربطه بـTenant/Client/Brand/Internal Campaign.

### Meta Ads
- OAuth/businesses/ad accounts/campaigns/ad sets/ads/creatives/insights/spend/conversions/CAPI.

### TikTok Ads
- Business Center/advertisers/campaigns/ad groups/ads/reporting/spend/conversions/Events API.

### Snapchat Ads
- Organizations/ad accounts/campaigns/ad squads/ads/stats/conversions/CAPI.

### Google Ads
- Manager/customer accounts.
- Campaigns/ad groups/assets.
- GAQL.
- Spend/conversions.
- YouTube Ads.
- Offline/enhanced conversions.

### X Ads
- Accounts/campaigns/line items/promoted posts/analytics/conversions حيث تدعم.

### LinkedIn Ads
- Accounts/campaign groups/campaigns/creatives/reporting/Lead Gen/conversions.

### OpenAI / ChatGPT Ads
أضف Provider رسمي داخل Ads Hub:
- account
- campaigns
- ad groups
- ads
- creatives
- budgets/status
- insights
- conversions
- health
- external mapping

ممنوع Metrics وهمية.

---

## 17. FIRST-PARTY TRACKING & ATTRIBUTION

Architecture:

```text
TrackingLink
→ Click
→ Session
→ Conversion
→ Attribution
→ ProviderForwarding
```

### Tracking Links
- unique creator/campaign link
- opaque ID
- custom slug
- UTM builder
- QR
- copy/share
- destination validation

### Clicks
Privacy-first، بدون fingerprinting غير ضروري.

### Conversions
- lead/order/purchase
- revenue/currency
- coupon
- creator/campaign
- source/evidence

### Truth rules
- CPC فقط مع cost + clicks.
- CPA فقط مع cost + conversions.
- ROAS فقط مع spend + attributed revenue.
- Unknown != zero.

---

## 18. SERVER-SIDE EVENT PIPELINE

```text
FirstPartyEvent
→ ConsentPolicy
→ EventNormalizer
→ ProviderMapper
→ Queue
→ ProviderAdapter
→ DeliveryReceipt
→ Retry
→ Audit
→ Health
```

Providers:
- Meta CAPI
- TikTok Events API
- Snapchat CAPI
- Google conversions
- LinkedIn conversions
- X إذا دعم رسميًا
- OpenAI Ads conversions

---

## 19. TRACKING HEALTH CENTER

لكل Connector:
- status
- last event
- last success/failure
- success rate
- token expiry
- API quota/rate limit
- webhook health
- queue backlog
- invalid/delayed events
- remediation

Token alone != Connected.

---

## 20. COMMERCE

### Salla
- OAuth.
- Stores/products/orders/coupons.
- Webhooks.
- Revenue/attribution/reconciliation.

### Zid
نفس المتطلبات.

### Shopify
جهز Provider contract للمرحلة اللاحقة.

---

## 21. REPORT BUILDER

فوق immutable snapshot الموجود:
- overview
- creators
- content
- publication
- tracking
- conversions
- audience
- client-safe finance
- evidence
- timeline
- insights

Outputs:
- interactive web
- secure share
- PDF
- XLSX
- CSV

مع versioning/checksum/stale warning/regenerate.

---

## 22. FIRST-CLASS SHARING

دعم مشاركة:
- Creator Profile
- Shortlist
- Campaign Update
- Content Review
- Contract
- Report
- Publication Proof
- Tracking Report
- Document

مع:
- expiry
- revoke
- scope
- access code
- view/download permissions
- audit
- client-safe serialization
- watermark عند الحاجة

---

## 23. CLIENT EXPERIENCE

### Shortlist
- قبول
- رفض
- مشاركة

### Content
- قبول
- طلب تعديل
- مشاركة

### Contract
- موافقة/توقيع
- رفض
- مشاركة

Advanced تحت `...`.

ممنوع كشف:
- creator cost
- agency margin
- payout internals
- private contacts
- bank data
- sourcing notes

---

## 24. CALENDAR

Unified calendar:
- campaign start/end
- meeting
- briefing
- shoot
- content/review/publish deadlines
- rights/exclusivity expiry
- contract renewal
- invoice/payout due
- follow-up

Google Calendar two-way sync فقط عند تفعيله.

---

## 25. AUTOMATION OS V2

Modes:

```text
MONITOR
RECOMMEND
AUTO
```

Actions:
- notify
- create/assign task
- update safe status
- request approval
- send approved communication
- schedule follow-up
- calendar event
- report generation
- integration retry

Sensitive actions:

```text
proposal
→ validation
→ permission
→ approval
→ execution
→ audit
→ read-back
```

No uncontrolled financial actions.

---

## 26. AI AGENTS

بعد استقرار deterministic workflows:
- Relationship Agent
- Casting Agent
- Campaign Operations Agent
- Content Agent
- Finance Agent
- Client Success Agent

نفس دورة Proposal → Approval → Execution → Audit.

AI لا يخترع أداء أو بيانات مالية.

---

## 27. DOCUMENTS + E-SIGN

Documents:
- Internal storage
- Google Drive
- Versioning
- Permissions
- Audit
- Briefs/contracts/invoices/media/content

أنشئ `SignatureProvider` لدعم مزود مثل DocuSign/Adobe Sign/مزود معتمد إقليميًا.

Capabilities:
- send/sign/decline/expire/complete/webhook/evidence.

---

## 28. FINANCE / BILLING

حافظ على Finance Ledger الحالي.

### SaaS Billing
أنشئ Provider حقيقي محايد.

خيارات سعودية للتقييم:
- Moyasar
- Tap
- HyperPay
- Stripe إذا كان مناسبًا للحساب/المنتج

Required:
- checkout
- subscription monthly/annual
- renewal/retry/cancel/refund
- invoices/VAT
- verified webhooks
- amount/currency read-back
- idempotency

لا Payment Success من redirect فقط.

### Creator payouts
- payable
- approved
- scheduled
- paid
- disputed

إذا لا يوجد payout provider حقيقي: paid يحتاج مرجع تسوية يدوي موثّق.

---

## 29. SAAS COMMERCIALIZATION

Platform Owner يتحكم في:
- plans
- monthly/annual pricing
- seats
- creator limits
- campaign limits
- integration limits
- storage
- entitlements
- trials/grace/suspension
- usage metering
- invoices
- MRR/ARR الحقيقي
- churn/revenue

Tenant isolation absolute.

---

## 30. INVITATIONS

Complete flows لـ:
- Creator
- Client
- Brand
- Partner
- Team

مع:
- single/bulk invite
- expiring token
- resend/revoke
- accept
- create/link account
- existing-account matching
- onboarding

---

## 31. NOTIFICATION CENTER

Groups:
- Needs Action
- Today
- Campaign
- Creator
- Client
- Finance
- System

Capabilities:
- read/unread
- mark all
- priority
- direct action/deep link
- preferences
- channel preferences
- digest
- dedup

---

## 32. AUTH + SECURITY

Authentication:
- Email/password
- Google
- OTP
- Recovery
- Session management
- TOTP 2FA
- Recovery codes
- Role-required 2FA
- Rate limiting
- Login audit

Security sweep:
- IDOR
- cross-tenant
- cross-portal
- authorization
- object ownership
- file/export/share access
- OAuth state
- webhook signatures
- replay prevention
- encrypted tokens
- payment idempotency

Fail closed.

---

## 33. OBSERVABILITY

Add:
- request IDs
- job IDs
- integration correlation IDs
- structured logs
- queue monitoring
- failed jobs
- scheduler health
- webhook health
- provider failures
- DB slow queries
- uptime
- storage/disk
- backup verification
- error monitoring

Health endpoints:
- liveness
- readiness
- database
- queue
- scheduler
- storage
- integrations

---

## 34. PERFORMANCE

Preserve PERF-01.

Required:
- query budgets
- no N+1
- pagination
- domain-scoped translation payload
- deferred secondary props
- safe caching
- SQL aggregation
- lazy page chunks
- image optimization
- CSS cleanup
- compression

Query-budget tests:
- Dashboard
- Creator Database
- Creator 360
- Campaign Workspace
- Reports
- Finance

---

## 35. MOBILE / PWA

كل Workflow يعمل على `390×844`.

Required:
- bottom nav
- safe areas
- drawer/sheet
- standalone PWA
- Service Worker
- offline safe page
- reconnect
- no authenticated HTML cache
- iPhone WebKit
- Android Chromium

لا Desktop-table dump على الجوال.

---

## 36. ACCESSIBILITY

Target: `WCAG 2.2 AA`.

Verify:
- keyboard
- focus
- contrast
- reduced motion
- forms/errors
- modal focus
- screen readers
- chart text equivalents
- status not color-only
- RTL/LTR

---

## 37. DATA IMPORT / EXPORT

Imports:
- creators
- clients
- contacts
- campaigns
- rates
- historic collaborations

Sources:
- CSV
- XLSX
- Google Sheets

Flow:

```text
Upload/Connect
→ Preview
→ Mapping
→ Validation
→ Dedup
→ Conflict Resolution
→ Import
→ Audit
```

---

## 38. EXTERNAL CREDENTIALS POLICY

إذا أي API يحتاج credentials، **لا توقف المشروع**.

ابنِ أولًا:
- contract
- adapter
- fake/sandbox
- OAuth/callback
- refresh/revoke
- webhook
- sync/reconciliation
- health
- UI
- tests
- docs

ثم سجل الحالة:

`BLOCKED_EXTERNAL_CREDENTIALS`

وانتقل مباشرة للمرحلة التالية.

اجمع كل ما يحتاجه المالك في:

`docs/EXTERNAL_BLOCKERS.md`

لكل مزود:

```text
Provider
App / Account
Required credentials
Scopes
Redirect URL
Webhook URL
Approval status
Sandbox/live status
Next commissioning action
```

لا تطلب نفس البيانات أكثر من مرة.

---

## 39. EXECUTION ORDER

نفّذ بهذا الترتيب:

```text
GATE-00
Current PR/CI → fix → merge → deploy → smoke

CORE-01
Security + permission enforcement + query budgets

CREATOR-01
Creator 360 + relationship timeline

INTEGRATIONS-01
Integration Foundation V2

GOOGLE-01
Drive + Sheets + Gmail + Calendar

COMMS-01
WhatsApp + unified conversations + email

SOCIAL-01
Instagram + TikTok + YouTube

SOCIAL-02
X + Snapchat + LinkedIn + CreatorDataProvider

CASTING-01
Discovery & Casting

CAMPAIGN-01
Campaign Command Center

RIGHTS-01
Usage rights + licensing

PROOF-01
Publication proof

TRACK-01
Tracking links + UTM + clicks

TRACK-02
Conversions + attribution

TRACK-03
Tracking Health Center

ADS-01
Meta + TikTok + Snapchat Ads

ADS-02
Google + YouTube + X + LinkedIn Ads

ADS-03
OpenAI / ChatGPT Ads

COMMERCE-01
Salla + Zid

REPORT-01
Report Builder

SHARE-01
Secure sharing

FINANCE-01
SaaS billing + payment provider

AUTOMATION-01
Automation V2

AI-01
AI Agents

QA-01
Security + mobile + accessibility + performance

CLOSE-01
Final production truth audit
```

---

## 40. DEFINITION OF DONE PER UNIT

كل دفعة يجب أن تنتهي بـ:

1. Verify repo/current main.
2. Scope.
3. Fail-first tests where applicable.
4. Implement.
5. Tenant/RBAC review.
6. Backend tests.
7. Typecheck.
8. Build.
9. Focused E2E.
10. Mobile check.
11. Arabic RTL.
12. English LTR.
13. No fake data.
14. Docs update.
15. PR.
16. CI.
17. Merge when green.
18. Deploy.
19. Production smoke.
20. Refresh from main.
21. Next clean unit.

لا تبقِ Branch واحدًا مفتوحًا لكل المشروع.

---

## 41. FINAL COMPLETION CONDITIONS

لا يعتبر InfluencerHub مكتملًا لمجرد وجود الصفحات.

مكتمل عندما:
- Creator 360 كامل.
- Relationship Timeline كامل.
- Discovery/Casting كامل.
- Campaign Command Center كامل.
- Rights/Licensing موجود.
- Publication Proof موجود.
- WhatsApp/Email داخل Timeline.
- Google Workspace كامل.
- Social APIs تعمل حيث رسميًا ممكن.
- Ads Hub كامل.
- Tracking/Attribution حقيقي.
- Commerce متكامل.
- Reports قابلة للمشاركة.
- SaaS Billing فعلي.
- Security passed.
- Performance passed.
- Mobile/PWA passed.
- Accessibility passed.
- Monitoring/backups verified.
- Docs تطابق الواقع.
- كل external blockers موثقة بدقة.

---

## 42. FINAL PRODUCT AUDIT

عند عدم بقاء أي عمل يمكن إنجازه داخليًا، أنشئ:

`docs/FINAL_PRODUCT_AUDIT.md`

بالتصنيفات:

```text
VERIFIED
LIVE_VERIFIED
BLOCKED_EXTERNAL_CREDENTIALS
BLOCKED_PROVIDER_APPROVAL
OPTIONAL_FUTURE
```

مع Owner Action محدد لكل blocker.

---

# CLAUDE CODE — EXECUTION DIRECTIVE

بعد وضع هذا الملف في:

`docs/MASTER_COMPLETION_ROADMAP.md`

نفّذ التعليمات التالية:

```text
MASTER EXECUTION DIRECTIVE — COMPLETE INFLUENCERHUB

Read and execute:

docs/MASTER_COMPLETION_ROADMAP.md

This file is now the canonical roadmap for completing InfluencerHub.

FIRST verify fresh repository truth:

- git remote origin
- repository name
- current main SHA
- branch/worktree
- open PRs
- current CI

Work ONLY on:

digitalalharbi/InfluencerHub-Platform

Required:

REPO_OK=InfluencerHub-Platform
CAMPAIGNSHUB_TOUCHED=NO
DIGITALSMART_TOUCHED=NO

START by closing the current active PR/CI gate.

If #189 is still open, inspect its exact current state and latest CI.
Fix only the real failure.
Preserve PERF-01 and IDENTITY-03.
Run CI.
When fully green:
merge → deploy → production smoke → update canonical docs.

Then execute the roadmap in the exact ordered units defined in:

docs/MASTER_COMPLETION_ROADMAP.md

Do NOT ask routine implementation questions.

Do NOT stop because external provider credentials are unavailable.

For every external integration, build everything internally possible first:

contract
adapter
OAuth lifecycle
callback
refresh/revoke
webhook
sync
reconciliation
health
UI
fake/sandbox tests
documentation

If live credentials are unavailable, mark honestly:

BLOCKED_EXTERNAL_CREDENTIALS

Then immediately continue to the next independent unit.

Never claim:

CONNECTED
LIVE_VERIFIED
real revenue
ROAS
conversion
delivery
payment

without real evidence.

Preserve:

multi-tenancy
RBAC
privacy
client-safe finance
real-data doctrine
IDENTITY-03
mobile/PWA
PERF-01

Every unit must close:

audit
→ implement
→ tests
→ PR
→ CI
→ merge
→ deploy
→ production smoke
→ docs
→ refresh main
→ next clean unit

Do NOT keep one unbounded feature branch alive for the whole product.

Keep updated after every unit:

docs/RESUME_STATE.md
docs/AI/SESSION-STATE.md
docs/PRODUCT_CLOSURE.md
docs/PRODUCTION_READINESS.md
docs/REQUIREMENTS_TRACEABILITY_MATRIX.md
docs/INTEGRATION_READINESS.md when relevant
docs/EXTERNAL_BLOCKERS.md when relevant

Do not reopen completed architecture without evidence.

Continue until every internally-buildable item in MASTER_COMPLETION_ROADMAP.md is either:

VERIFIED
or
BLOCKED_EXTERNAL_CREDENTIALS / BLOCKED_PROVIDER_APPROVAL

At the end create:

docs/FINAL_PRODUCT_AUDIT.md

with:

VERIFIED
LIVE_VERIFIED
BLOCKED_EXTERNAL_CREDENTIALS
BLOCKED_PROVIDER_APPROVAL
OPTIONAL_FUTURE

and the exact remaining owner actions.

START NOW.
```
