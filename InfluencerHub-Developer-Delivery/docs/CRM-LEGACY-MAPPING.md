# CRM Legacy Mapping (V2)

المرجع: `docs/legacy/LEGACY-DATA-MAPPING.md` + `LEGACY-VERIFICATION-REPORT.md`. لا يُفقد أي حقل قديم.

| حقل قديم (customers) | V2 | ملاحظة |
|---|---|---|
| name (اسم السجل التجاري) | clients.legal_name | |
| brand | brands.name (كيان مستقل belongs to client) | فصل العلامة عن العميل |
| coordinator | clients.account_manager_id (ref user) أو client_contacts (primary) | منسّق الحساب |
| activityType | clients.sector | نشاط/قطاع |
| crNumber | clients.commercial_registration_number | |
| vatNumber | clients.tax_number | |
| isVatRegistered | clients.vat_registered (boolean) | |
| isComplete | يُشتق من اكتمال الحقول (لا عمود) — أو client_custom_fields | قرار: محسوب لاحقًا |
| kyc_documents | client_documents (Phase 3 المتبقّي: تخزين خاص) | ملفات خاصة موقّعة |
| display name | clients.display_name | |
| status (active/...) | clients.status (lead/qualified/active/inactive/suspended/archived) | + client_status_history append-only |

**حقول قديمة بلا مطابقة مباشرة:** تُنشأ كحقل منظّم أو `client_custom_fields` (نوع text/number/date/boolean/select/...). لا فقدان — القرار موثّق هنا. **الترحيل** عبر أوامر `legacy:import` (Phase 14) بـDry-run + validation + rollback.
