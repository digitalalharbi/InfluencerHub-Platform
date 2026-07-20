# دليل الأمان — InfluencerHub V2

## عزل المستأجر
- `TenantScope` (fail-closed: بلا سياق → لا نتائج) + `BelongsToTenant`. `SetTenantContext` قبل SubstituteBindings.
- Route-Model-Binding محكوم بالسياق → مستأجر آخر = 404 (لا 403 كاشف).

## سجل التدقيق
- Append-only على مستوى التطبيق + **Trigger PostgreSQL فعلي** يمنع UPDATE/DELETE.

## بوابة طلبات الانضمام (عامة)
- **الوصول:** المرجع العام وحده لا يكفي. يلزم **رمز وصول منفصل** (`access_token`، يُخزَّن sha256 فقط، انتهاء 30 يومًا، قابل للإلغاء/التدوير) أو **جلسة متقدّم** مُنشأة بعد التحقق. كل عملية حسّاسة (ملفات/مالية/إرسال) محروسة. محاولات الوصول الفاشلة تُسجَّل في `creator_application_access_attempts`.
- **الاستعادة:** عبر البريد (`/join/recover`) — رسالة موحّدة لا تكشف وجود الطلب؛ تُدوِّر الرمز؛ محدودة المعدّل.
- **OTP:** sha256 فقط، 10 دقائق، 5 محاولات، cooldown 60ث، عبر الطابور، لا يُعرض في الإنتاج. SMS بلا مزوّد = `waiting_for_credentials`.
- **الملفات:** قرص خاص، MIME allowlist لكل فئة، منع الامتدادات التنفيذية، حدّ حجم من `config`، checksum، أسماء مولّدة، مسار معزول tenant/application، تنزيل مُصرَّح ومُدقّق، IDOR-safe (المستند يجب أن يخص الطلب). حدود التخزين عبر `creator_storage.gb`.
- **حلّ المستأجر:** صريح عبر `?a={slug}` (SaaS)، fail-closed، **لا "أول مستأجر"**. dedicated/self_hosted: مؤسسة وحيدة موثّقة. subdomain/custom-domain لاحقًا.
- **نقل الملفات عند القبول:** post-commit عبر `FinalizeCreatorFilesJob` (لا نعتبره Fully Atomic؛ بل *Database-transactional + idempotent post-commit finalization*): pending→copying→completed/failed، تحقّق checksum، الأصل يبقى، إعادة محاولة آمنة، `creators:reconcile-files`.

## Rate Limiting مركّب
- ليس IP وحده. حدود مستقلّة لكل عملية بمفاتيح مركّبة (IP + email hash + reference): `join-start`, `join-otp`, `join-recover`, `join-op`. الرسائل موحّدة لا تكشف بريدًا/طلبًا.

## بوابة المبدع
- `EnsureCreator` يحلّ المبدع من `user_id`، سياق من المبدع نفسه (IDOR-safe)، يمنع الدخول إن `creator_portal.enabled` غير مفعّلة. حقول محمية (اعتماد/تحقق/حالة/tenant_id/الاستهلاك) غير قابلة للتعديل من المبدع. IBAN مشفّر (Crypt) + آخر 4 فقط.

## بيئة المعاينة
- `preview:seed`/`e2e:seed` ترفض الإنتاج. Preview Center يعيد 404 في الإنتاج. لا كلمات مرور في Git (`.preview-accounts.local.md` غير متتبَّع). `DatabaseSeeder` الإنتاجي: كتالوجات مرجعية فقط، لا بيانات تجريبية.
