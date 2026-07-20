# تقرير اختبارات المبدعين (Phase 4)

**الإجمالي: 137 اختبار خلفي (345 تأكيدًا) + 55 Playwright — كلها ناجحة.**

## خلفي (PostgreSQL)
| المجموعة | العدد | يغطّي |
|----------|------|-------|
| CreatorTest | 6 | إنشاء/ترقيم/عزل/سياسات (creator_manager يكتب، campaign يقرأ، influencer ممنوع) |
| CreatorApplicationTest | 9 | مرجع غير متسلسل، OTP(hash/خطأ/انتهاء/محاولات)، حالة append-only، مسودة HTTP، منع تكرار |
| ApproveCreatorApplicationTest | 3 | معاملة القبول، idempotent double-approve، rollback عند تجاوز creators.max |
| CreatorPortalTest | 7 | يملك ملفه فقط، حقول محمية غير قابلة للتعديل، IBAN encrypt/decrypt، IDOR، Not-available، موثوق لا يُعتمد ذاتيًا |

## Playwright (متصفّح، قاعدة معزولة)
| النطاق | السيناريوهات |
|--------|-------------|
| بوابة الانضمام العامة | 44–46 (فتح، مسودة+مرجع، OTP) |
| مراجعة الوكالة والقبول | 47–50 (قائمة، تفاصيل، قبول→مبدع، viewer 403) |
| بوابة المبدع | 51–55 (دخول، تحديث ملف، IBAN مقنّع، Not-available، غير مبدع مرفوض) |

**تشغيل:** `php artisan test` · `npx playwright test`.


## تحديث إكمال Phase 4
مجموعات إضافية: ApplicationDocumentTest(6)، CreatorEntitlementsTest(7)، JoinPortalDataTest(4)، OtpDeliveryTest(6)، CreatorPortalCrudTest(4)، + قبول ينقل الملفات(1). **الإجمالي الخلفي 165 اختبار، وPlaywright 63 سيناريو — كلها ناجحة.**

Playwright الإضافية: 56–60 (رفع صورة/رفض تنفيذي/إضافة حساب+خدمة/IBAN مقنّع/بقاء بعد إعادة تحميل)، 61–63 (CRUD بوابة: إضافة+حذف منصة/خدمة بوحدات صغرى/الوحدات المستقبلية فقط Not-available).
