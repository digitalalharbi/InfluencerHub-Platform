# Test Report (V2 — Phase 0–2)

قاعدة الاختبار: **PostgreSQL** (`influencerhub_testing`)، لا SQLite.

## النتيجة: **33 passed (81 assertions)**
- TenancyIdentityTest (5): عزل مستأجر (fail-closed)، throw-بلا-سياق، multi-workspace roles، system_admin bypass.
- TenantHttpIsolationTest (7): IDOR (read/update/delete→404)، route binding، fail-closed بلا عضوية، create scoped، Queue يحفظ tenant_id، Cache معزول، 401.
- InvitationTest (4): valid/expired/accepted/revoked.
- BillingTest (12): versioning+lock، lifecycle state machine، boolean/numeric/unlimited، add-on/enterprise override، no-sub=no-paid، consume/reject/idempotency، per-org isolation، release/recalc، self_hosted/dedicated، fake-not-live.
- BillingApiTest (3): billing endpoints tenant-scoped، system_admin gate، 401.
- Example (2).

بوابات: `migrate:fresh --seed` نظيف · `composer validate` valid · `composer audit` نظيف · لا أسرار في Git · لا بيانات تجريبية.
