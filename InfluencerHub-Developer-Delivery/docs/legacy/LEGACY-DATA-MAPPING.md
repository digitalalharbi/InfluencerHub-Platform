# ربط بيانات القديم → V2

| كيان قديم | حقول رئيسية (قديم) | كيان V2 (Domain) | ملاحظات المحاذاة |
|---|---|---|---|
| agencies | name, slug, status, settings | Tenancy: tenants/organizations | فصل tenant عن organization/workspace |
| users | username, name, email, role, agency_id, is_active, must_change_password, two_factor_* | Identity: users + organization_memberships | العضوية متعددة workspaces بأدوار مختلفة |
| customers | name, brand, coordinator, cr_number, vat_number, activity_type, is_complete, kyc_documents | CRM: clients + brands | brand ككيان مستقل |
| influencers | name, platform, followers, category, rating, cost_price, sale_price, iban, social_links | Creators: creators + creator_social_accounts + creator_pricing | فصل الحسابات والأسعار |
| campaigns | code, name, customer_id, budget, status, tags | Campaigns: campaigns + campaign_briefs | builder متعدد الخطوات |
| nominations | influencer_id, request_id, campaign_id, selling_price, cost_price, client_decision | Collaborations: collaborations | دورة كاملة |
| transfers | direction, amount_base, vat, amount_total, recipients | Finance: payouts + ledger journal_lines | double-entry |
| requests | number, title, type, source, status, brief | Requests: requests | + status_history/attachments |
| نماذج localStorage (ugc_*, calendar_events, ...) | متنوعة | تُبنى backend كامل في V2 | لا ترحيل مباشر لـlocalStorage |

**مبدأ:** لا ترحيل لطبقة localStorage. الترحيل عبر CSV/Excel/DB export بأوامر `legacy:import` (Dry-run + validation + rollback).
