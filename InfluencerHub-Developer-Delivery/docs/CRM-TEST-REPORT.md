# CRM Test Report (Phase 3)

PostgreSQL. **CrmClientTest (8) + CrmClientApiTest (4) = 12** تمرّ:
- إنشاء تحت الحد يستهلك · lead لا يُحسب · **رفض تجاوز customers.max + rollback (لا استهلاك)** · archive يحرّر idempotent · restore يعيد الاستهلاك · recalculate يصلح الانحراف · brands لا تستهلك · عزل مستأجر (نموذج).
- API: إنشاء 201 · تجاوز → 422 · عزل HTTP (IDOR→404) · 401.
- **التزامن الحقيقي** (Phase 2): مؤكَّد على PostgreSQL (billing:consume ×2 → 1 نجاح + 1 رفض، used=1).

**متبقٍّ (موثّق في CONTINUATION-STATE):** members/documents/custom_fields، Policies تفصيلية لكل دور، Browser E2E، وبقية الـ30 اختبارًا.

---

## تحديث الإكمال — Phase 3 مكتملة (2026-07-16)

**الإجمالي: 112 اختبار خلفي (284 تأكيدًا) + 30 اختبار Playwright — كلها ناجحة.** التفصيل:

| المجموعة | العدد | يغطّي |
|----------|------|-------|
| ClientMemberTest | 7 | Hash الرمز، رفض المنتهية/المُستخدَمة، لا عضوية مكرّرة، أدوار البوابة، الحالات |
| ClientDocumentTest | 7 | قرص خاص، checksum، MIME allowlist، تنزيل مُدقّق، IDOR عبر المستأجرين + خلط المعرّفات |
| CustomFieldTest | 8 | تحقّق لكل نوع (11)، خيارات select/multiselect مُلزِمة، required، upsert |
| CrmPolicyTest | 14 | مصفوفة 12 دورًا (view/create/delete/managePortal) + تجاوز system_admin |
| AuditLogHardeningTest | 6 | حقول جديدة + منع UPDATE/DELETE على مستوى التطبيق **و PostgreSQL Trigger فعلي** |
| CrmApiEndpointsTest | 6 | brands/contacts/members/custom-fields عبر HTTP + 403 (viewer/influencer) |
| CrmWebUiTest | 7 | بوابة الدخول، عرض من قاعدة البيانات، إنشاء عبر نموذج، عزل المستأجر، viewer 403 |
| LegacyImportTest | 6 | CSV/JSON، mapping مخصّص، dry-run، dedup، rollback دفعة |
| ConcurrentClientCreationTest | 1 | تزامن فعلي: عمليتان مستقلّتان، customers.max=1 → SUCCESS+REJECTED |
| Playwright E2E | 30 | auth(6)، clients(10)، isolation+IDOR(4)، RBAC(4)، entitlement(2)، UI/RTL(4) |

**تشغيل:** `php artisan test` · `npx playwright test`. راجع `PHASE-3-GATE.md` لمعايير القبول.
