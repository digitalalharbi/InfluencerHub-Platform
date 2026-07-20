# بوابة قبول Phase 3 — CRM (العملاء والعلامات وبوابة الفريق)

> الحالة: **مكتملة ومختبَرة** · التاريخ: 2026-07-16 · Laravel 12 / PHP 8.4 / PostgreSQL / Redis / Sanctum

## الملخّص التنفيذي
اكتملت المرحلة الثالثة بالكامل: نواة CRM + بوابة فريق العميل + المستندات الخاصة + الحقول المخصّصة +
السياسات + تصلّب سجل التدقيق + واجهة API كاملة + واجهة ويب (Blade/Alpine، RTL، تصميم قديم) +
30 سيناريو Playwright + مستورد قديم. **112 اختبار خلفي (284 تأكيدًا) + 30 اختبار متصفّح — كلها ناجحة.**

## معايير القبول (Acceptance Gate)

| # | المعيار | الحالة | الدليل |
|---|---------|--------|--------|
| 1 | إزالة أمر إداري يعدّل/يستهلك Usage دون صلاحية+تدقيق | ✅ | حُذف `ConsumeUsageCommand`؛ التزامن يُختبر عبر عمليات مستقلّة |
| 2 | `client_members` + بوابة الفريق (أدوار/حالات/دعوات) | ✅ | `ClientMemberTest` (7) — Hash للرمز، رفض المنتهية/المُستخدَمة، لا عضوية مكرّرة |
| 3 | تخزين الرمز Hash لا خام؛ لا تجديد للدعوة المنتهية | ✅ | `token_hash = sha256`؛ `isPending()` |
| 4 | client_admin لا يعيّن system_admin/agency_admin | ✅ | تحقّق `ClientMemberRole::values()` |
| 5 | `client_documents` على قرص خاص + MIME allowlist + checksum | ✅ | `ClientDocumentTest` (7) — لا اسم أصلي في المسار، sha256 |
| 6 | لا روابط عامة دائمة؛ تنزيل مُصادَق ومُدقّق؛ منع IDOR | ✅ | تنزيل عبر Controller؛ 404 عبر المستأجرين + خلط المعرّفات |
| 7 | الحقول المخصّصة (11 نوعًا) على client+brand | ✅ | `CustomFieldTest` (8) — تحقّق لكل نوع، خيارات مُلزِمة |
| 8 | سياسات لكل الأدوار (اختبار 12 دورًا) | ✅ | `CrmPolicyTest` (14) — مصفوفة قدرات + تجاوز system_admin |
| 9 | تصلّب سجل التدقيق (حقول + مناعة فعلية) | ✅ | `AuditLogHardeningTest` (6) — **Trigger فعلي في PostgreSQL** يمنع UPDATE/DELETE |
| 10 | API كامل (brands/contacts/members/documents/custom-fields) | ✅ | `CrmApiEndpointsTest` (6)؛ 8 Controllers؛ 403 للأدوار الممنوعة |
| 11 | واجهة CRM (Blade/Alpine/Vite، RTL، بلا localStorage/وهمي) | ✅ | `CrmWebUiTest` (7)؛ خادم يعرض من قاعدة البيانات فقط |
| 12 | Playwright — 30 سيناريو متصفّح | ✅ | 6 ملفات spec؛ 30/30 ناجحة |
| 13 | اختبار إنشاء عملاء متزامن فعلي (customers.max=1) | ✅ | `ConcurrentClientCreationTest` — عمليتان: SUCCESS+REJECTED، used≤1 |
| 14 | مستورد قديم `import:legacy-clients` (+dry-run/mapping/rollback) | ✅ | `LegacyImportTest` (6) — CSV/JSON، dedup، تراجع دفعة |

## مناعة سجل التدقيق — تصريح دقيق
المناعة **ليست ادّعاءً على مستوى التطبيق فقط**: طُبِّق Trigger فعلي في PostgreSQL
(`audit_logs_block_mutation` + `trg_audit_logs_no_update/no_delete`) يرفع استثناء عند أي
`UPDATE`/`DELETE`. مُتحقَّق منه باختبار يتجاوز Eloquent عبر `DB::table()` الخام ويؤكّد
`QueryException`، وباختبار يقرأ `pg_trigger` للتأكد من وجود المُشغّلَين فعليًا.

## المكوّنات
- **الهجرات:** 26 · **نماذج CRM:** 13 · **أكشنات CRM:** 11 · **Controllers API:** 8
- **جداول جديدة في المرحلة:** client_members, client_member_invitations, client_member_status_history,
  client_documents, custom_field_definitions/options/values, import_batches (+ حقول تصلّب audit_logs)

## الاختبارات
- **خلفي (PHPUnit على PostgreSQL):** 112 اختبار / 284 تأكيدًا — ناجحة.
- **متصفّح (Playwright/Chromium):** 30 سيناريو — ناجحة (قاعدة `influencerhub_e2e` معزولة، بذور حتمية).
- **تشغيل:** `php artisan test` · `npx playwright test` (يهيّئ الخادم والبذور تلقائيًا).

## ملاحظات أمنية مثبتة
- عزل المستأجر fail-closed على الويب والـAPI (404 عبر المستأجرين، وليس 403 كاشفًا).
- تنزيل مستند عبر مستأجر آخر يُرجع 401/404 (تحقّق مزدوج curl + Playwright) — لا 200.
- `is_system_admin` غير قابل للـmass-assignment (منع تصعيد الامتياز)؛ تجاوز الأدمن يُسجَّل.

## البوابة: **مفتوحة للانتقال إلى Phase 4 (المبدعون وبوابة UGC).**
