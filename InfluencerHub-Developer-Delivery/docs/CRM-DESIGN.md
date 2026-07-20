# CRM Design (Phase 3)

Domain: `app/Domain/CRM`. الفصل المعماري:
- **Tenant** = المشترك في InfluencerHub. **Organization/Workspace** = بيئة عمل فريقه.
- **CRM Client** = عميل الوكالة (تابع `tenant_id`). **Brand** = علامة تابعة لعميل.

## الكيانات
clients (tenant-scoped، soft delete، status history) · brands (belongs to client) · client_contacts (primary) · client_notes (داخلية) · client_status_history (append-only).

## Entitlement customers.max
- العميل يُحسب عند status ∈ {qualified, active} وغير مؤرشف/محذوف (موثّق ومُختبَر).
- **CreateClient** ضمن معاملة واحدة: فحص + استهلاك (idempotency `client:create:{uuid}`) + إنشاء + status_history + audit. فشل الإنشاء = rollback = لا استهلاك.
- **ArchiveClient**: release idempotent (`client:archive:{id}`). **RestoreClient**: re-consume مع فحص الحد.
- **RecalculateCustomerUsage**: يحسب العدد الحقيقي من جدول clients (لا counter منحرف).
- Contacts/Brands/Notes/Documents لا تستهلك وحدات (الحد للعملاء فقط).

## Actions
CreateClient, ArchiveClient, RestoreClient, RecalculateCustomerUsage, CreateBrand (+ المتبقّي: ChangeClientStatus, CreateClientContact, InviteClientMember, AttachClientDocument, AddInternalClientNote).
