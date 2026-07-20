# حالة معاينة الواجهة — InfluencerHub V2

> يُحدَّث باستمرار. الحالة: **Browser Verified** = جُرّبت فعليًا في متصفّح Claude. تشغيل محلي: `php artisan serve --port=8010` ثم `/login`.
> حسابات المعاينة: `php artisan preview:seed` (كلمة المرور من `PREVIEW_PASSWORD` أو الافتراضي المحلي؛ التفاصيل في `.preview-accounts.local.md` غير المتتبَّع).

## Phase 3 — CRM (مبنية)

| الوحدة | المسار | الأدوار | المصدر | العمليات | Desktop | Mobile | RTL | Playwright | Browser |
|--------|--------|---------|--------|----------|:------:|:-----:|:---:|:---------:|:-------:|
| تسجيل الدخول | `/login` | الجميع | PostgreSQL (جلسة) | دخول/خروج | ✅ | ✅ | ✅ | ✅ (6) | ✅ |
| لوحة الوكالة | `/app` | فريق الوكالة | PostgreSQL | إحصاءات + أحدث العملاء | ✅ | ✅ | ✅ | ✅ | ✅ |
| العملاء | `/app/clients` | فريق الوكالة | PostgreSQL | قائمة/بحث/تصفية/إنشاء/أرشفة | ✅ | ✅ | ✅ | ✅ (10) | ✅ |
| تفاصيل العميل | `/app/clients/{id}` | فريق الوكالة | PostgreSQL | 6 تبويبات تفاعلية | ✅ | ✅ | ✅ | ✅ | ✅ |
| العلامات (وكالة) | `/app/brands` | فريق الوكالة | PostgreSQL | قائمة/بحث | ✅ | ✅ | ✅ | ✅ | ✅ |
| العلامات (بالعميل) | تبويب «العلامات» | admin/ops/campaign | PostgreSQL | إضافة/عرض | ✅ | ✅ | ✅ | ✅ (31،32) | ✅ |
| جهات الاتصال | تبويب «جهات الاتصال» | admin/ops/campaign | PostgreSQL | إضافة/عرض | ✅ | ✅ | ✅ | ✅ (35) | ✅ |
| المستندات | تبويب «المستندات» | admin/ops/finance | قرص خاص + PostgreSQL | رفع/تنزيل مُدقّق | ✅ | ✅ | ✅ | ✅ | ✅ |
| أعضاء فريق العميل | تبويب «أعضاء الفريق» | admin/ops | PostgreSQL | دعوة (رمز مرة واحدة)/عرض | ✅ | ✅ | ✅ | ✅ (33) | ✅ |
| الحقول المخصّصة | تبويب «حقول مخصّصة» | فريق الوكالة | PostgreSQL | تعريف/ضبط قيمة | ✅ | ✅ | ✅ | ✅ (34) | ✅ |
| مركز المعاينة | `/app/preview` | فريق الوكالة (تطوير) | ثابت | حالة الوحدات | ✅ | ✅ | ✅ | ✅ (36،37) | ✅ |

**حالات مغطّاة:** Loading/Empty/Error عبر رسائل النجاح والأخطاء وحالات «لا بيانات»؛ Validation عبر Form Requests؛ الصلاحيات عبر Policies (viewer/influencer يُمنعان). لا localStorage، لا بيانات Demo — كل البيانات من PostgreSQL.

## Phase 4 — المبدعون (مبنية، تظهر في القائمة تحت مجموعة «المبدعون»)

| الوحدة | المسار | الأدوار | المصدر | العمليات | Desktop | Mobile | RTL | Playwright | Browser |
|--------|--------|---------|--------|----------|:------:|:-----:|:---:|:---------:|:-------:|
| المؤثرون | `/app/creators?type=influencer` | creator_manager/admin/ops | PostgreSQL | قائمة/تصفية/إنشاء | ✅ | ✅ | ✅ | ✅ (38،40،42) | ✅ |
| صنّاع UGC | `/app/creators?type=ugc_creator` | creator_manager/admin/ops | PostgreSQL | قائمة/تصفية/إنشاء | ✅ | ✅ | ✅ | ✅ (39) | ✅ |
| كل المبدعين | `/app/creators` | creator_manager/admin/ops | PostgreSQL | قائمة/بحث/حالة/إنشاء | ✅ | ✅ | ✅ | ✅ | ✅ |
| ملف المبدع | `/app/creators/{id}` | creator_manager/admin/ops | PostgreSQL | بطاقات + منصّات | ✅ | ✅ | ✅ | ✅ (41) | ✅ |

تصفية النوع: المؤثرون تشمل `both` وتستثني UGC الصرف (والعكس) — مؤكَّد آليًا. RBAC: المشاهد يرى ولا ينشئ (403)؛ دور `influencer` لا يرى الوحدة.

## قيد البناء (تظهر في القائمة كـ«قريبًا» — ليست روابط ميتة)
المؤثرون · صنّاع UGC · طلبات الانضمام · بوابة العملاء · طلبات/منشئ/سوق الحملات · التعاونات · المهام · المحتوى والموافقات · العقود · الشحن والهدايا · المدفوعات · التقارير · الاشتراكات (Backend جاهز) · الإعدادات · التكاملات · الأتمتة · لوحة إدارة النظام.

## الاختبارات الآلية للواجهة
- **Playwright: 43 سيناريو** (auth 6، clients 10، عزل/IDOR 4، RBAC 4، حدود الخطة 2، تصميم/RTL/جوال 4، تدفقات CRM تفاعلية 7، المبدعون 6) — كلها ناجحة.
- **خلفي: 118 اختبار** (295 تأكيدًا) — ناجحة.
- تشغيل: `php artisan test` · `npx playwright test` (يهيّئ خادمًا وبذورًا معزولة تلقائيًا).

## المشكلات المتبقية
- لا شيء حاجب في Phase 3. الوحدات التالية (Phase 4+) ستظهر تدريجيًا في المعاينة أثناء بنائها.

## Phase 4 — بوابة طلبات الانضمام (سلايس 2، مبنية)

| الوحدة | المسار | الأدوار | المصدر | الحالة |
|--------|--------|---------|--------|:------:|
| بوابة الانضمام (عامة) | `/join`, `/join/creator` | عام (بلا دخول) | PostgreSQL | Browser Verified |
| متابعة الطلب + OTP | `/join/creator/{ref}/status` | عام | PostgreSQL | Browser Verified |
| إدارة الطلبات | `/app/creator-applications` | creator_manager/admin/ops | PostgreSQL | Browser Verified |
| مراجعة طلب (تبويبات+إجراءات) | `/app/creator-applications/{id}` | creator_manager/admin/ops | PostgreSQL | Browser Verified |

القبول → إنشاء الحساب: مؤكَّد حيًّا (تطبيق CR-1-0006 من طلب، نقل المنصات، استهلاك creators.max، منع القبول المزدوج). مرجع عشوائي غير متسلسل، OTP بـHash/انتهاء/محاولات، IBAN سيُشفَّر (بوابة المبدع التالية). الاختبارات: CreatorApplicationTest(9) + ApproveCreatorApplicationTest(3).

## Phase 4 — بوابة المبدع (سلايس 2د، مبنية)

| الوحدة | المسار | الأدوار | المصدر | الحالة |
|--------|--------|---------|--------|:------:|
| دخول المبدع | `/creator/login` | مبدع | PostgreSQL (جلسة) | Browser Verified |
| لوحة المبدع | `/creator/dashboard` | مبدع | PostgreSQL | Browser Verified |
| ملفي / المنصات / الخدمات / نماذج الأعمال / موثوق | `/creator/*` | مبدع | PostgreSQL | UI Ready |
| البيانات المالية (IBAN مشفّر) | `/creator/financial` | مبدع | PostgreSQL | Browser Verified |
| الإشعارات | `/creator/notifications` | مبدع | PostgreSQL | UI Ready |
| الفرص/التعاونات/العقود/المستحقات | `/creator/*` | مبدع | — | Not available yet (بلا بيانات وهمية) |

أمن البوابة (مؤكَّد باختبارات + حيًّا): المبدع يملك ملفه فقط، لا يغيّر (الاعتماد/التحقق/حالة موثوق/الحالة المالية/tenant_id/الاستهلاك)؛ IBAN مشفّر فعليًا (Crypt) ويُعرض آخر 4 فقط؛ منع IDOR عبر السياق من المبدع نفسه؛ الوحدات اللاحقة تعرض "Not available yet". اختبارات: CreatorPortalTest(7).

## Phase 4 — إكمال (ملفات/بيانات/حدود/OTP/بوابة) — مبنية ومُتحقَّقة

| الوحدة | المسار | الحالة |
|--------|--------|:------:|
| رفع ملفات الطلب (avatar/iban/mowthooq/portfolio) | `/join/creator/{ref}` | Browser Verified |
| جمع المنصات/الخدمات/الأعمال/موثوق/المالية | `/join/creator/{ref}/status` | Browser Verified |
| تنزيل ملفات الطلب (إدارة، مُدقّق) | `/app/creator-applications/{id}` تبويب الملفات | UI Ready |
| مراجعة موثوق/المالية (اعتماد/رفض) | تبويبات موثوق/مالية | UI Ready |
| بوابة المبدع — رفع صورة + CRUD منصات/خدمات/أعمال | `/creator/*` | Browser Verified |

الحدود الخمسة مُفعَّلة ومختبَرة (ugc/portal/social/storage/monthly). OTP عبر الطابور + قوالب بريد + عقد SMS (waiting_for_credentials محليًا)، لا يُعرض الرمز في الإنتاج، cooldown لإعادة الإرسال. الملفات خاصّة (MIME allowlist، منع تنفيذي، checksum، مسار معزول، تنزيل مُدقّق، IDOR-safe). IBAN مشفّر فعليًا (Crypt) ويُعرض آخر 4 فقط.

## Phase 4 — بوابة التصحيح النهائية (fix/harden)
- تأمين وصول المتقدّم (رمز منفصل + جلسة + استعادة بريد)، حلّ مستأجر صريح، إتمام ملفات post-commit، Rate limiting مركّب، حماية بيئة المعاينة.
- اختبارات جديدة: ApplicantAccessTest(9)، TenantResolutionTest(6)، FileFinalizationTest(4). **الإجمالي الخلفي 184.**

## Phase 5 — بوابة العميل (سلايس 2)
- إزالة كلمات المرور الثابتة (عشوائية/بيئية، ملف خاص 0600، اختبار يمنع الثابت).
- ملف العميل الكامل: تعديل مباشر (client_admin) + الحقول القانونية الحساسة → طلب مراجعة الوكالة (لا تُطبَّق مباشرة)؛ حقول محظورة (tenant_id/status/account_manager) محميّة؛ رفع شعار خاص.
- الملف المالي: client_finance/admin (لا float، لا بيانات هامش داخلية).
- Browser Verified: دخول العميل (كلمة مرور مُدوَّرة) → الملف (قانوني «تتطلب مراجعة») → المالي. اختبارات: ClientProfileTest(6) + NoHardcodedCredentialsTest(3). الإجمالي 200 خلفي.

## Phase 5 — بوابة العميل (سلايس 3: عناوين + مستندات)
- **العناوين** `/client/addresses`: CRUD + افتراضي فريد لكل نوع + أرشفة/استعادة؛ client_id من العميل النشِط (لا يُثق بالنموذج)؛ IDOR-safe. Browser Verified (أول عنوان: شحن/افتراضي).
- **المستندات الخاصة** `/client/documents`: رفع خاص (MIME/تنفيذي/checksum/versioning)؛ العميل يرى client_visible فقط (agency_internal محجوب + تنزيله 403)؛ تنزيل مُدقّق + Access Log؛ مراجعة الوكالة (approved/changes_requested/rejected)؛ IDOR-safe. Browser Verified (رفع عقد v1 بانتظار المراجعة).
- اختبارات: ClientAddressTest(7) + ClientPortalDocumentTest(8). الإجمالي **215 خلفي**.

## Phase 5 — بوابة العميل (سلايس 4: سير عمل العلامات)
- **العلامات (العميل)** `/client/brands`: إنشاء مسودة، تعديل (مسموح فقط في draft/changes_requested)، إرسال للمراجعة (يُنشئ إصدارًا)، لافتة سبب «طلب الوكالة تعديلًا» تظهر للعميل، إعادة الإرسال تُنشئ إصدارًا جديدًا (v2). IDOR-safe (علامة العميل النشِط فقط).
- **مراجعة العلامات (الوكالة)** `/app/brand-reviews`: قائمة حسب الحالة + تفصيل + سجل قرارات append-only + **إجراءات بالأحداث** (بدء مراجعة/اعتماد/طلب تعديل/تعليق) — لا Dropdown حالة يدوي. آلة حالة: draft→submitted→under_review→approved/changes_requested؛ approved→suspended/archived.
- Browser Verified — الدورة الكاملة: العميل ينشئ «أديداس الرياضية» (مسودة) → إرسال (v1) → الوكالة تبدأ المراجعة → تطلب تعديلًا بسبب ظاهر للعميل → العميل يرى اللافتة ويعدّل ويعيد الإرسال (v2) → الوكالة تعتمد (معتمدة). سجل القرارات يعرض قراري v1 (مطلوب تعديل) وv2 (معتمدة).
- اختبارات: BrandWorkflowTest(6) — مسار كامل، تعديل→إصدار جديد، قفل بعد الإرسال، منع انتقال غير صالح، منع اعتماد مزدوج، عزل + IDOR عبر HTTP. الإجمالي **221 خلفي**.

## Phase 5 — بوابة العميل (سلايس 5: مراجعات الوكالة)
- **مراجعات العملاء (الوكالة)** `/app/client-reviews` — لوحة موحّدة بتبويبين:
  - **تعديلات قانونية**: طلبات تعديل الحقول الحساسة (الاسم القانوني/السجل/الضريبي) من العميل تُعرض قديم→جديد؛ **اعتماد وتطبيق** (يطبّق البيانات على العميل فعلًا) أو **رفض** بسبب إلزامي (لا يُطبَّق). تخويل عبر ClientPolicy::managePortal.
  - **مستندات بانتظار المراجعة**: تنزيل مُدقّق + قرار (اعتماد/طلب تعديل/رفض) مع سبب إلزامي للتعديل/الرفض. تخويل عبر manageDocuments.
- عزل: Route model binding fail-closed (طلب مستأجر آخر → 404)؛ دور viewer لا يعتمد (403).
- Browser Verified: اعتماد طلب قانوني (طُبّقت legal_name/CR/tax على العميل — مؤكَّد في قاعدة البيانات) + مراجعة مستند (سُجّل القرار).
- اختبارات: AgencyClientReviewTest(7) — تطبيق/رفض/سبب إلزامي/قرار مستند/تخويل viewer/عزل مستأجر. الإجمالي **228 خلفي**.

## Phase 5 — بوابة العميل (سلايس 6: إدارة الفريق)
- **الفريق (العميل)** `/client/team` — client_admin فقط:
  - قائمة الأعضاء (اسم/بريد/دور/حالة) + شارة «أنت» للمستخدم الحالي.
  - دعوة عضو (بريد + دور بوابة عميل فقط) — رمز الدعوة يُعرض **مرة واحدة** (نُخزّن sha256)؛ منع تعيين أدوار الوكالة/النظام.
  - تغيير الدور، تعليق/تفعيل، إزالة (revoke) + قائمة الدعوات المعلّقة.
  - **حماية آخر مدير**: لا يمكن خفض/تعليق/إزالة آخر client_admin نشِط.
  - IDOR-safe (عضو العميل النشِط فقط)؛ يعيد استخدام أفعال Phase 3 (Invite/ChangeRole/ChangeStatus + سجل حالة).
- Browser Verified: دعوة عضو مالية → ظهر الرمز مرة واحدة + دخلت قائمة الدعوات المعلّقة.
- اختبارات: ClientTeamTest(9) — دعوة/رمز مخزّن مُجزّأ/منع أدوار الوكالة/تغيير دور/تعليق+تفعيل/حماية آخر مدير(×2)/سماح عند وجود مدير آخر/عزل IDOR. الإجمالي **237 خلفي**.

## Phase 5 — بوابة العميل (سلايس 7: الإشعارات)
- نظام إشعارات **محايد للمزوّد** في `app/Domain/Communications` (يخدم بوابتي العميل والمبدع والوكالة):
  - `notifications` + `notification_preferences` + `notification_delivery_attempts`.
  - `NotificationService`: in_app يُسلَّم فورًا؛ email/sms تُسجَّل كمحاولة بحالة **waiting_for_credentials** (لا تسليم وهمي)؛ تفضيلات لكل فئة؛ استعادة سياق المستأجر السابق (آمن للتداخل).
  - `ClientNotifier`: يوجّه إشعارات لأعضاء العميل النشِطين أو لمستخدم واحد.
- **وصل بالأحداث**: اعتماد/طلب تعديل علامة → أعضاء العميل؛ مراجعة مستند → أعضاء العميل؛ اعتماد/رفض تعديل قانوني → مُرسِل الطلب.
- **مركز الإشعارات (العميل)** `/client/notifications`: قائمة بفئات + وقت + مؤشّر غير مقروء + شارة عدّاد في القائمة الجانبية + «تحديد الكل كمقروء» + النقر يفتح action_url ويحدّد كمقروء. عزل مستأجر + IDOR-safe.
- Browser Verified: إشعاران (اعتماد علامة + طلب تعديل مستند) بشارة «2» → «تحديد الكل كمقروء» صفّر الشارة وعتّم البطاقات.
- اختبارات: NotificationTest(5) — حالات التسليم الصادقة/عدّاد+قراءة/استعادة السياق/وصل اعتماد العلامة عبر HTTP/عزل مستأجر. الإجمالي **242 خلفي**.

## Phase 5 — بوابة العميل (سلايس 8: الإعدادات)
- **الإعدادات (العميل)** `/client/settings`:
  - **تفضيلات الإشعارات**: مصفوفة لكل فئة (داخل التطبيق مفعّل دائمًا، بريد/SMS اختياري) — البريد/SMS بانتظار ربط مزوّد (حالة صادقة).
  - **تغيير كلمة المرور**: تحقق من الحالية (current_password) + سياسة قوة (8+، أحرف+أرقام) + تأكيد؛ التغيير يُنهي الجلسات الأخرى.
  - **الجلسات النشطة**: من مخزن الجلسات (database driver) — الجهاز/المتصفّح + IP + آخر نشاط، مع تمييز الجلسة الحالية و«إنهاء الجلسات الأخرى».
  - **المصادقة الثنائية (2FA)**: حالة صادقة «غير مُفعّلة — قريبًا» (أعمدة الجاهزية موجودة: two_factor_secret/confirmed_at).
- Browser Verified: الصفحة تعرض المصفوفة + نموذج كلمة المرور + 2FA + جلسات حقيقية (المتصفّح «الحالية» + جلسات curl).
- اختبارات: ClientSettingsTest(7) — عرض/عرض مع تفضيلات موجودة (idempotent)/حفظ التفضيلات/كلمة مرور خاطئة/تحديث الهاش/كلمة ضعيفة/إنهاء الجلسات مع إبقاء الحالية. الإجمالي **249 خلفي**.

## Phase 5 — بوابة الوكالة الخارجية (سلايس 9)
- **الوكالات الخارجية (الوكالة)** `/app/partner-agencies` — قبول بالأحداث (لا Dropdown): draft→submitted→under_review→approved/changes_requested؛ approved→suspended/archived. تفصيل + سجل حالة append-only + دعوة أعضاء (رمز مرة واحدة، أدوار الشريك فقط) + **روابط مُنطّقة (scoped)** بعميل/علامة بنطاقات (view_briefs/submit_content/view_reports/manage_creators/view_contracts). الربط مسموح **فقط بعد الاعتماد**؛ العلامة يجب أن تخص العميل (fail-closed 404).
- **بوابة الشريك** `/partner/*` (بوابة مستقلة عن `/app` و`/client`): `EnsurePartnerMember` fail-closed (عضوية شريك نشطة **و** وكالة معتمدة — يرفض غير المعتمد/المعلّق/المُزال)؛ لوحة تعرض **فقط** العملاء/العلامات المرتبطة بنطاقاتها؛ مبدّل وكالات (عضويات نشطة فقط)؛ عزل مستأجر + IDOR-safe.
- Browser Verified — الدورة الكاملة: الوكالة تنشئ «نجمة الإبداع» → إرسال → مراجعة → اعتماد → ربط «نايك السعودية» بـ3 نطاقات → دخول الشريك يرى فقط ذلك الربط بشاراته.
- **قبول الدعوة (عام مُحصّن)** `/partner/invite/{token}`: البحث بالـhash فقط، تحقّق صلاحية + وكالة معتمدة، إنشاء حساب المدعو بكلمة مروره + تفعيل العضوية + دخول تلقائي؛ استخدام مرة واحدة؛ بريد موجود مسبقًا → يجب تسجيل الدخول أولًا؛ Rate limiting (20/10 بالدقيقة). Browser Verified: قبول دعوة newpartner → إنشاء حساب «أحمد الشمري» ودخول اللوحة المُنطّقة.
- اختبارات: ExternalAgencyTest(11) + PartnerInvitationTest(7) — مسار كامل/انتقال غير صالح/تعديل→إعادة إرسال/قفل بعد الإرسال/دعوة برمز مُجزّأ/منع الربط قبل الاعتماد/ربط بنطاقات/العلامة تخص العميل/بوابة ترفض غير معتمد/الشريك يرى روابطه فقط/عضو معلّق ممنوع. الإجمالي **267 خلفي**.

## Phase 6 — طلبات الخدمة الخارجية (مكتملة)
- بنية `app/Domain/Requests`: service_requests + comments (داخلي/خارجي) + status_history؛ آلة حالة بالأحداث + SLA (urgent 4h/high 24h/normal 72h/low 168h).
- **العميل** `/client/requests`، **الشريك** `/partner/requests` (مقيّد بالعملاء المرتبطين، 422 لغير المرتبط)، **الوكالة** `/app/service-requests` (فلاتر+SLA+إسناد+حالة+تعليقات داخلية/خارجية).
- رؤية: الملاحظات الداخلية مخفية عن العميل/الشريك. إشعارات على تغيّر الحالة + الردود.
- Browser Verified — دورة كاملة عبر البوابات: العميل ينشئ SR-1-1 → الوكالة (SLA+ملاحظة داخلية مخفية+فرز+رد خارجي) → العميل يرى الحالة والرد الخارجي فقط + شارة إشعار.
- اختبارات: ServiceRequestTest(10). الإجمالي **277 خلفي**. Gate: docs/PHASE-6-GATE.md.

## Phase 7 — منشئ الحملات (مكتملة)
- بنية `app/Domain/Campaigns`: campaigns + deliverables + status_history؛ CampaignWorkflowService (حالة بالأحداث + مخرجات + تحويل من طلب). ميزانية بوحدات صغرى + "الملتزَم" = Σ(أجر×كمية).
- **الوكالة** `/app/campaigns` (إنشاء/تحويل من طلب/مخرجات/حالة draft→planning→active→paused/completed)، **العميل** `/client/campaigns` (عرض فقط، بلا أجور/مبدعين). حارس: لا تفعيل بلا مخرجات.
- Browser Verified: تحويل SR-1-1 → CM-1-1 → مخرج (ريل×3، 4,500، ملتزَم 13,500) → تخطيط → تفعيل. اختبارات CampaignTest(12). الإجمالي **289 خلفي**. Gate: docs/PHASE-7-GATE.md.

## Phase 8 — التعاونات والمطابقة (مكتملة)
- بنية `app/Domain/Collaborations`: collaborations + status_history؛ CollaborationWorkflowService (offered→accepted→in_progress→submitted→approved→completed) + CreatorMatchingService (مطابقة مفسَّرة: منصّة+فئات+وصول).
- **الوكالة** `/app/collaborations` + اقتراح/عرض من مخرَج حملة، **المبدع** `/creator/collaborations` (قبول/رفض/بدء/تسليم — فعّلنا stub سابق). إشعارات ثنائية الاتجاه. سجل append-only يميّز الفاعل.
- Browser Verified: عرض CO-1-1 (Renad) → المبدع يقبل (مقبول، إجراء «بدء التنفيذ»). اختبارات CollaborationTest(11). الإجمالي **300 خلفي**. Gate: docs/PHASE-8-GATE.md.

## Phase 9 — المحتوى والموافقات (مكتملة)
- بنية `app/Domain/Content`: content_items + approvals + status_history؛ ContentWorkflowService (draft→submitted→agency_review→client_review→approved→scheduled/published؛ إعادة التقديم تزيد الإصدار). سجل append-only + قرارات لكل مرحلة يميّز الفاعل.
- **المبدع** `/creator/content` (إنشاء/تقديم/إصدارات)، **الوكالة** `/app/content` (مراجعة/إرسال للعميل/نشر)، **العميل** `/client/content` (اعتماد/طلب تعديل، بعد client_review فقط). إشعارات للمبدع.
- Browser Verified: Renad ينشئ CN-1-1 → يقدّمه → الوكالة تبدأ المراجعة → إرسال للعميل (قرار «الوكالة موافقة» مسجّل). اختبارات ContentTest(11). الإجمالي **311 خلفي**. Gate: docs/PHASE-9-GATE.md.

## Phase 10 — العقود (مكتملة)
- بنية `app/Domain/Contracts`: contracts + status_history؛ ContractWorkflowService (draft→sent→signed→active→completed؛ +terminate/cancel). سجل append-only يميّز الفاعل.
- **الوكالة** `/app/contracts` (إنشاء/بنود/إرسال/تفعيل/إنهاء)، **المبدع** `/creator/contracts` + **العميل** `/client/contracts` (قبول داخل المنصّة بتسجيل اسم+وقت، تنويه صادق أنّه ليس توقيعًا قانونيًا خارجيًا). إشعارات ثنائية.
- Browser Verified: CT-1-1 (Renad، 13,500) → إرسال → المبدع يقبل (مقبول، سُجّل الاسم+الوقت). اختبارات ContractTest(10). الإجمالي **321 خلفي**. Gate: docs/PHASE-10-GATE.md.

## Phase 11 — المستحقات المالية (مكتملة)
- بنية `app/Domain/Finance`: payouts + status_history؛ PayoutWorkflowService (pending→approved→scheduled→waiting_for_provider→paid/failed؛ +cancel). **صدق مالي: لا تنفيذ دفع — «مدفوع» تُسجَّل يدويًا بمرجع بعد تسوية حقيقية**. مبلغ بوحدات صغرى + لقطة IBAN last4.
- **الوكالة/المالية** `/app/payouts` (إنشاء/اعتماد/جدولة/إرسال للمزوّد/تسجيل الدفع بمرجع/فشل)، **المبدع** `/creator/payouts` (عرض ملخّص + قائمة). إشعارات للمبدع.
- Browser Verified: PY-1-1 (Renad، 13,500) → waiting_for_provider (تنويه لا تنفيذ) → تسجيل الدفع بمرجع TRX → مدفوع. اختبارات PayoutTest(10). الإجمالي **331 خلفي**. Gate: docs/PHASE-11-GATE.md.

## Phase 12 — التقارير والتحليلات (مكتملة)
- `app/Domain/Analytics/AnalyticsService`: تجميعات حقيقية مقيّدة بالمستأجر عبر كل الوحدات. **لوحة** `/app/reports` (فعّلت placeholder): بطاقات KPI + رسوم أشرطة CSS بلا مكتبة، إجماليات مالية بوحدات صغرى. **كل الأرقام فعلية — لا mock**.
- Browser Verified: اللوحة تعكس بيانات المراحل (مدفوع 13,500، تعاون مقبول، محتوى في مراجعة العميل، حملة نشطة، طلب فرز). اختبارات AnalyticsTest(4). الإجمالي **335 خلفي**. Gate: docs/PHASE-12-GATE.md.

## Phase 13 — محرّك الأتمتة/SLA (مكتملة)
- `app/Domain/Automation`: SlaEngineService + AutomationLog + أعمدة تتبّع على service_requests. قواعد dedup: تذكير قبل الاستحقاق (نافذة 12h) + رصد تجاوز → علَم+إشعار+سجلّ+تدقيق. أمر `sla:scan` مجدول كل ساعة (عزل مستأجر تام). لافتة تحذير في تفصيل الطلب.
- Browser Verified: SR-1-1 متأخّر → sla:scan (breaches=1) → لافتة «تجاوز SLA (رُصد آليًا)». اختبارات SlaEngineTest(7). الإجمالي **342 خلفي**. Gate: docs/PHASE-13-GATE.md.

## إثبات بصري (Mandatory Visual Proof) — لقطات محفوظة

اللقطات محفوظة محليًا (غير متتبَّعة) تحت `storage/app/private/development-screenshots/`.
أداة الالتقاط: `SHOWCASE_PW=… node scripts/dev-screenshots.mjs <outDir> <email> "<url>:<name>"…` (خادم dev 8010 + بيئة العرض).

| الوحدة | الرابط | الدور | اللقطات (Desktop / Mobile) | Console | الاختبارات |
|-------|-------|------|----------------------------|:------:|-----------|
| مركز قيادة الحملة (حملة نشطة) | `/app/campaigns/98` | showcase_admin | `campaign-command-center/campaigns/command-center-active-{desktop,mobile}.png` | نظيف | CampaignTest ✓ · ShowcaseSeederTest ✓ |

**مراجعة بصرية (مركز قيادة الحملة):** بطاقة «الخطوة التالية» (هوية بنفسجي/حبر أصلية، إجراء رئيسي واحد) + «رحلة الحملة» (مسار أفقي أصلي 7 مراحل، الحالية بحلقة، المكتملة ✓) + شريط مؤشرات موسّع + جدول مخرجات بمبدعين حقيقيين عبر المنصّات الست + سجل حالة. Desktop 1440 وMobile 390: لا تمرير أفقي، لا أخطاء Console. راجع [CHANGELOG-VISUAL](CHANGELOG-VISUAL.md).
