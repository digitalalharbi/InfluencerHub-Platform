# CRM Permissions (Phase 3)

الصلاحيات: clients.{view,create,update,archive,restore,manage_contacts,manage_documents,manage_members,view_internal_notes,manage_internal_notes}, brands.{view,create,update,archive}.

## القواعد (مُنفَّذ منها العزل عبر TenantScope + Sanctum؛ Policies التفصيلية متبقّية)
- مستأجر A لا يرى/يعدّل/يحذف عميل مستأجر B (مُختبَر HTTP: 404 IDOR).
- client_member لا يرى الملاحظات الداخلية (client_notes منفصلة عن بوابة العميل).
- viewer لا ينشئ/يعدّل. finance يرى الفوترة ولا يعدّل CRM إلا بصلاحية إضافية.
- تغيير ID في URL لا يتجاوز العزل (route binding بعد سياق المستأجر).
**متبقٍّ:** Policies صريحة لكل دور (system_admin/agency_admin/account_manager/campaign_manager/finance/viewer/client_admin/client_member) + اختباراتها.
