# PWA Architecture — InfluencerHub

بنية تطبيق الويب التقدّمي (Progressive Web App) لمنصّة InfluencerHub.

الهدف: تجربة قابلة للتثبيت وشبيهة بتطبيق أصلي على الجوال وسطح المكتب، **دون** المساس بالأمان أو تخزين أي بيانات حسّاسة/مالية/مصادَق عليها.

---

## 1. المكوّنات

| الملف | الغرض |
|------|-------|
| `public/manifest.webmanifest` | تعريف التطبيق (الاسم، الأيقونات، `display: standalone`، الألوان، RTL/ar). |
| `public/sw.js` | Service Worker محافظ: يخزّن الأصول الثابتة فقط + بديل offline. |
| `public/offline.html` | صفحة بديلة تظهر عند انقطاع الشبكة أثناء التنقّل. |
| `public/icons/ih-icon.svg` | أيقونة `any` (متجهية، شفافة الحواف). |
| `public/icons/ih-maskable.svg` | أيقونة `maskable` (منطقة أمان للأندرويد/الاختصارات). |
| `resources/js/app.js` | تسجيل الـ Service Worker بعد تحميل الصفحة + حدث تحديث. |
| `<head>` في التخطيطات | `link rel=manifest`, `theme-color`, `apple-touch-icon`, `apple-mobile-web-app-*`, `viewport-fit=cover`. |

---

## 2. قاعدة الأمان الحاكمة (الأهم)

> **Service Worker لا يخزّن ولا يعترض أي بيانات مصادَق عليها أو حسّاسة أو مالية إطلاقًا.**

استراتيجية التخزين حسب نوع الطلب:

| نوع الطلب | الاستراتيجية | يُخزَّن؟ |
|-----------|--------------|---------|
| أصول البناء `/build/*` (CSS/JS مبصومة) | Cache-first | ✅ (ثابتة، غير حسّاسة) |
| الأيقونات `/icons/*`، `manifest`, `offline.html` | Cache-first (precache) | ✅ |
| خطوط Google (`fonts.googleapis/gstatic`) | Cache-first | ✅ |
| تنقّل الصفحات (navigate) `/app`, `/client`, ... | Network-first → عند الفشل: `offline.html` | ❌ **لا يُخزَّن HTML المصادق** |
| `POST/PUT/PATCH/DELETE` وأي غير-GET | تمرير مباشر للشبكة | ❌ |
| `/api/*` وكل ما سواه | تمرير مباشر (network passthrough) | ❌ |

**التحقّق الفعلي** (من المتصفح بعد زيارة لوحة التحكم المصادَقة):

```json
cacheNames: ["ih-static-ih-v1"]
cachedUrls: [
  "/offline.html",
  "/icons/ih-icon.svg",
  "/manifest.webmanifest"
]
```

لاحظ أن `/app` (صفحة مصادَقة زُرناها للتو) **غير موجودة** في الكاش — وهذا هو السلوك المقصود.

---

## 3. البصمة والإصدار (Versioning)

- ثابت `VERSION = 'ih-v1'` يُشتق منه اسم الكاش `ih-static-ih-v1`.
- عند رفع الإصدار، يحذف `activate` كل كاشات `ih-static-*` القديمة تلقائيًا.
- أصول Vite مبصومة بالهاش (`app-<hash>.js`)، فلا تعارُض بين النسخ.

## 4. تدفّق التحديث (Update Flow)

1. `app.js` يسجّل `/sw.js` بعد حدث `load`.
2. عند اكتشاف نسخة جديدة (`updatefound` + `statechange === 'installed'` مع وجود controller) يُطلق حدث `ih:update-available`.
3. يمكن ربط شريط "توفّرت نسخة جديدة — تحديث" بهذا الحدث لاحقًا (بلا `window.confirm`).
4. `SKIP_WAITING` مدعوم عبر `postMessage` لتفعيل النسخة الجديدة فورًا عند الطلب.

## 5. الخصوصية بين المستخدمين

- بما أنه لا يُخزَّن أي محتوى مصادَق، لا يوجد تسريب بيانات بين حسابين على نفس الجهاز.
- الكاش يقتصر على أصول عامة (CSS/JS/أيقونات/خطوط) مشتركة وغير سرّية.
- عند تسجيل الخروج لا حاجة لمسح بيانات حسّاسة من الـ SW (لأنها غير مخزّنة أصلًا)؛ الجلسة تُدار عبر كوكيز الخادم كالمعتاد.

## 6. القابلية للتثبيت (Installability)

- `display: standalone` + `display_override: ["standalone","minimal-ui"]`.
- `start_url: /app`، `scope: /`.
- أيقونتان (`any` + `maskable`) بصيغة SVG.
- `theme_color: #6252E5` (بنفسجي الهوية)، `background_color: #080D1A` (حبر داكن).
- `lang: ar`, `dir: rtl`.

## 7. iOS / Safari (WebKit)

- `apple-mobile-web-app-capable: yes` + `apple-mobile-web-app-status-bar-style: black-translucent`.
- `apple-touch-icon` مُعرّف.
- `viewport-fit=cover` + استخدام `env(safe-area-inset-*)` في CSS يمنع تداخل المحتوى مع النوتش وشريط الإيماءات.

## 8. حدود مقصودة (Non-Goals)

- لا وضع offline كامل للبيانات التشغيلية (PostgreSQL هو مصدر الحقيقة الوحيد).
- لا مزامنة خلفية (Background Sync) للبيانات المالية.
- لا Push Notifications في هذه المرحلة (تُضاف لاحقًا مع موافقة صريحة وبنية اشتراك آمنة).

---

## 9. التحقّق

- ✅ الـ SW يسجّل بنطاق `/` ويتحكّم بالصفحة.
- ✅ الكاش يقتصر على 3 أصول عامة فقط (offline/icon/manifest) — لا صفحات مصادَقة.
- ✅ `manifest` و`theme-color` مكتشفان في `<head>`.
- ✅ لا أخطاء Console عند التسجيل.
- ✅ التطبيق يعمل طبيعيًا بعد تفعيل الـ SW (لا تعطيل، التسجيل اختياري داخل `try/catch`).
