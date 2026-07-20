# Device Test Matrix — InfluencerHub

مصفوفة اختبار المقاسات والأجهزة. مُنفّذة آليًا في `tests/e2e/16-responsive.spec.js` (Playwright).

## 1. المقاسات × البوابات (فحص: لا تمرير أفقي)

| البوابة | 375 (جوال) | 768 (لوحي) | 1280 (مكتب) | 1440 (واسع) |
|--------|:---------:|:----------:|:-----------:|:-----------:|
| الوكالة (agency) | ✅ | ✅ | ✅ | ✅ |
| العميل (client) | ✅ | ✅ | ✅ | ✅ |
| الشريك (partner) | ✅ | ✅ | ✅ | ✅ |

المسارات المفحوصة لكل بوابة (قوائم + نماذج + تقارير):
- **الوكالة:** `/app`, `/app/clients`, `/app/service-requests`, `/app/campaigns`, `/app/reports`
- **العميل:** `/client/dashboard`, `/client/brands`, `/client/requests`, `/client/documents`
- **الشريك:** `/partner/dashboard`, `/partner/requests`

كل خلية تعني: على هذا المقاس، **كل** مسارات البوابة تحقّق `scrollWidth ≤ clientWidth`.

## 2. فحوصات الجوال (390×844)

| الفحص | الحالة |
|------|:-----:|
| لا تمرير أفقي على `/app` | ✅ |
| التنقّل السفلي مرئي (3–5 روابط) | ✅ |
| الشريط العلوي للجوال مرئي | ✅ |
| درج القائمة يفتح بزر القائمة | ✅ |
| لا تمرير أفقي والدرج مفتوح | ✅ |
| خط الإدخال ≥ 16px (منع تكبير iOS) | ✅ |

## 3. النتيجة الحالية

```
15 passed (1.6m) — chromium
```

## 4. التشغيل

```bash
npx playwright test 16-responsive --reporter=list
```

يهيّئ الأمر قاعدة E2E نظيفة (`migrate:fresh` + `e2e:seed`) ويشغّل الخادم على `:8020` تلقائيًا.

## 5. التوسّع المقترح (متصفّحات إضافية)

لإضافة WebKit (Safari) و Firefox إلى المصفوفة، أضِف مشروعين في `playwright.config.js`:

```js
projects: [
  { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  { name: 'webkit',   use: { ...devices['Desktop Safari'] } },
  { name: 'firefox',  use: { ...devices['Desktop Firefox'] } },
]
```

ثم `npx playwright install webkit firefox`. سيُشغّل `16-responsive.spec.js` نفسه عبر الثلاثة.
