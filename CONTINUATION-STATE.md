# حالة الاستئناف — InfluencerHub V2

المشروع: `~/Desktop/influencerhub-v2` · فرع: `main` · Laravel 12.64 / PHP 8.4 / PostgreSQL / Redis / Sanctum

## المراحل المكتملة (مُختبَرة على PostgreSQL — 112 اختبار خلفي + 30 Playwright يمرّ)
- **Phase 0:** إقلاع + Domains(17) + تدقيق Legacy فعلي.
- **Phase 1:** Tenancy + Identity + Roles + Sanctum. عزل fail-closed عبر HTTP (IDOR/binding/queue/cache)، دعوات، multi-workspace.
- **Phase 2:** SaaS Billing (plans/versions(lock)/prices، entitlements، subscriptions(state machine)، usage atomic/idempotent، coupons/add-ons، provider contract+Fake). بوابة مراجعة اجتازت.
- **Phase 3 (مكتملة بالكامل):** CRM كامل — تفصيل أدناه. **بوابة القبول مفتوحة** (`docs/PHASE-3-GATE.md`).
- **Phase 4 (المبدعون — مكتملة):** سجل المبدعين + بوابة انضمام عامة (مسودة/OTP طابور/متابعة/ملفات خاصّة/جمع منصات-خدمات-أعمال-موثوق-مالية) + مراجعة الوكالة (تبويبات + تنزيل ملفات مُدقّق + اعتماد موثوق/مالية) + قبول ينشئ الحساب وينقل الملفات (معاملة ذرّية، idempotent، rollback) + الحدود الخمسة (ugc/portal/social/storage/monthly) + بوابة المبدع كاملة (ملف+صورة/منصات/خدمات/أعمال CRUD/موثوق/مالية IBAN مشفّر). **165 اختبار خلفي + 63 Playwright.** IBAN مشفّر Crypt، OTP طابور لا يُعرض في الإنتاج، ملفات خاصّة MIME-allowlist/منع-تنفيذي/checksum/IDOR-safe. `migrate:fresh --seed` ناجح، `composer audit` نظيف. راجع `docs/CREATOR-*.md` و`docs/UI-PREVIEW-STATUS.md`. **التالي: بوابة Phase 4 النهائية ثم Phase 5 (Client & External Agency Portal).**

## Phase 3 — ما اكتمل (كل البنود الاثنا عشر)
1. **تنظيف:** حُذف الأمر الإداري لاستهلاك Usage؛ التزامن الفعلي عبر `ConcurrentClientCreationTest`.
2. **بوابة فريق العميل:** client_members + invitations (token **hash** لا خام) + status_history (append-only)؛ أدوار بوابة(6)؛ حالات(invited/active/suspended/revoked)؛ أكشنات Invite/Accept/ChangeStatus/ChangeRole. رفض الدعوة المنتهية/المُستخدَمة؛ لا عضوية مكرّرة؛ client_admin لا يعيّن أدوار وكالة/نظام.
3. **المستندات:** client_documents على قرص خاص (`storage/app/private`)؛ MIME allowlist؛ checksum sha256؛ اسم UUID (لا اسم أصلي في المسار)؛ تنزيل عبر Controller مُصادَق ومُدقّق؛ منع IDOR (عبر المستأجرين + خلط المعرّفات) → 404/401؛ حذف ناعم مُدقّق.
4. **الحقول المخصّصة:** definitions/options/values؛ 11 نوعًا؛ تحقّق صارم لكل نوع؛ select/multiselect مُلزَمان بالخيارات؛ على client+brand.
5. **السياسات:** ClientPolicy/BrandPolicy + مصفوفة `CrmAbilities`؛ 12 دورًا مُختبَرة؛ `Gate::before` لـsystem_admin (مُدقّق).
6. **تصلّب التدقيق:** حقول old_values/new_values/user_agent/request_id/occurred_at؛ **مناعة فعلية على مستوى PostgreSQL عبر Trigger** (مُتحقَّق منه بتجاوز Eloquent الخام + قراءة pg_trigger).
7. **API كامل:** clients/brands/contacts/members/documents/custom-fields (+ notes سابقًا)؛ 8 Controllers؛ سياسات مطبّقة؛ 403 موحّد؛ 404 للعزل.
8. **واجهة CRM:** Blade+Alpine+Vite، RTL، تصميم Legacy (تيل #0d8a6f)، جلسة ويب؛ لا localStorage/بيانات وهمية — كل شيء من قاعدة البيانات (login/dashboard/clients index+create modal/client show بتبويبات). مُتحقَّق منها بالمتصفّح.
9. **Playwright:** 30 سيناريو (قاعدة `influencerhub_e2e` معزولة، `e2e:seed` حتمي، `boot.sh` يهيّئ تلقائيًا) — 30/30 ناجحة.
10. **تزامن فعلي:** `ConcurrentClientCreationTest` (عمليتان مستقلّتان، customers.max=1).
11. **مستورد قديم:** `import:legacy-clients --file --tenant --mapping --dry-run --rollback-batch` (CSV/JSON، dedup، تراجع دفعة عبر import_batches).
12. **وثائق + بوابة:** `docs/PHASE-3-GATE.md` + تحديث `CRM-TEST-REPORT.md`.

## آخر اختبار ناجح: `php artisan test` → **112 passed (284 assertions)** + `npx playwright test` → **30 passed**.

## بيانات تشغيل محلية
- تطوير: قاعدة `influencerhub` — مستخدم `owner@demo.test`  (بذور تحقّق).
- E2E: قاعدة `influencerhub_e2e` — `admin@a.test` / `viewer@a.test` / `admin@b.test`  (عبر `php artisan e2e:seed`).
- خادم محلي: `php artisan serve` (Vite مبني مسبقًا في `public/build`).

## الأمر التالي الحرفي — بدء Phase 4
```
# ابدأ Phase 4: Creators & UGC Portal (المبدعون، الملفات، عروض المحتوى/UGC، بوابة المبدع).
# نفّذ بوابة مراجعة قصيرة لـPhase 3 أولًا (تأكيد 112+30 اختبارًا، Trigger التدقيق، العزل).
# تشغيل: export PATH="/opt/homebrew/opt/php@8.4/bin:$PATH"; cd ~/Desktop/influencerhub-v2; php artisan test
```

## غير الملتزم: يُلتزم الآن (docs). ## موانع خارجية (لاحقًا): مزودو الدفع/المنصات (Phase 10/11)، VPS/نطاق (Phase 17).
