# مصفوفة خصائص النظام القديم

| الخاصية | الحالة القديمة | خطة V2 (Domain) |
|---|---|---|
| مصادقة JWT + RBAC | ✅ backend | Identity (rebuild، sessions/2FA/devices) |
| عزل مؤسسات | ✅ AgencyScope | Tenancy (tenants/orgs/workspaces، fail-closed) |
| اشتراكات/خطط/قيود | ❌ بنية فقط | Billing (plans/subscriptions/entitlements/usage) |
| العملاء والعلامات | ✅ جزئي | CRM (clients/brands/members) |
| المؤثرون/UGC + بوابة | 🟡 محلي | Creators (portal، eligibility) |
| طلبات انضمام/عملاء | 🟡 محلي | Requests |
| منشئ الحملات + سوق | 🟡 محلي | Campaigns (builder، marketplace، eligibility) |
| التعاونات (workflow كامل) | ❌ | Collaborations |
| المحتوى والموافقات | 🟡 محلي | Content (versions، approvals) |
| العقود/الشحن/النزاعات | ❌ | Contracts |
| المالية + Ledger | 🟡 transfers فقط | Finance (double-entry، payouts، payments adapter) |
| التكاملات + Webhooks | 🟡 Mock | Integrations (adapters، vault، webhooks infra) |
| الأتمتة | ❌ | Automation (triggers/conditions/actions) |
| التحليلات/الإسناد | 🟡 محلي | Analytics (pipeline، attribution، reports) |
| التدقيق | ✅ AuditLog | Audit |
