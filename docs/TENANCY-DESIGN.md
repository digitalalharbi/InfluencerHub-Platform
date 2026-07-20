# تصميم تعدّد المستأجرين — InfluencerHub V2

## المفاهيم
- **Tenant** = المشترك في InfluencerHub. **Organization/Workspace** = بيئة عمل المشترك. **CRM Client** = عميل الوكالة. **Brand** = يتبع Client. **Creator** = مبدع يتبع Tenant.

## الآلية
- `TenantContext` (حامل ثابت set/tenantId/organizationId/bypass/reset). `TenantScope` عالمي fail-closed. `BelongsToTenant` trait.
- `SetTenantContext` middleware (يُشغَّل قبل SubstituteBindings) يضبط السياق من عضوية المستخدم؛ `system_admin` يتجاوز (مُدقّق).

## حلّ المستأجر للبوابات العامة (بلا مصادقة)
- **SaaS:** صريح عبر `?a={workspace_slug}` (Organization.slug). fail-closed إن كان الـslug غير صالح أو المؤسسة غير نشطة. **ممنوع استخدام "أول مستأجر" أو tenant_id ثابت.** (subdomain/custom-domain: تُضاف لاحقًا بنفس مبدأ الحلّ الصريح.)
- **Dedicated/Self-hosted:** يُسمح ببلا slug فقط عند وجود مؤسسة وحيدة موثّقة.
- مصدر الحلّ يُسجَّل على الطلب (`tenant_resolution_source`)، وكل الملفات/الطلبات مرتبطة بالمستأجر المحلول فقط. تغيير الـslug داخل الطلب لا يفتح مؤسسة أخرى (المستأجر مثبَّت على السجل).

## الوظائف والذيول (Queue/Cache)
- الوظائف المُصفَّفة تحفظ tenant_id وتعيد تهيئة السياق. مفاتيح الكاش معزولة بالمستأجر.
