# CRM API (Phase 3)

`/api/v1` (auth:sanctum + tenant). استجابات JSON موحّدة (404 not_found، 422 entitlement_limit/validation، 401).

- `GET/POST /clients` (search q, filter status/type, pagination) · `GET/PUT/DELETE /clients/{client}` · `POST /clients/{client}/restore`.
**متبقٍّ (موثّق):** brands/contacts/members/documents/notes endpoints (المخطّط في مواصفة Phase 3)، وتصدير/فلاتر إضافية.
