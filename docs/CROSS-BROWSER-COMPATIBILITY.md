# Cross-Browser Compatibility — InfluencerHub

التوافق عبر المتصفّحات. الهدف: سلوك متّسق على Chrome و Edge و Firefox و Safari (macOS + **iOS/WebKit** خصوصًا) و Chrome Android.

## 1. أهداف الدعم (browserslist)

معرّفة في `package.json`:

```
last 2 Chrome versions
last 2 Edge versions
last 2 Firefox versions
last 2 Safari versions
iOS >= 15
not dead
```

تُوجّه Vite/Tailwind لإخراج CSS/JS متوافق مع هذه الأهداف.

## 2. نقاط WebKit/iOS الحرجة (مُعالَجة)

| المسألة | المعالجة |
|--------|----------|
| ارتفاع `100vh` يشمل شريط المتصفّح | استخدام `100dvh`/`100svh` |
| تكبير الإدخال عند التركيز | خط الإدخال ≥ 16px على الجوال |
| النوتش وشريط الإيماءات | `viewport-fit=cover` + `env(safe-area-inset-*)` |
| التمرير المطّاطي | `-webkit-overflow-scrolling: touch` على الحاويات القابلة للتمرير |
| أيقونة التطبيق المثبّت | `apple-touch-icon` + `apple-mobile-web-app-*` |
| `backdrop-filter` | مدعوم مع بديل لوني صلب عند غيابه |

## 3. الخصائص المنطقية (RTL-safe)

الاعتماد على الخصائص المنطقية بدل الاتجاهية ليعمل التصميم في RTL وLTR معًا:
`margin-inline`, `padding-inline`, `inset-inline`, `border-inline-start`, `text-align:start`.
هذا يضمن اتّساق العربية (RTL) دون قواعد اتجاهية مكرّرة.

## 4. الطبقات الاحتياطية (Fallbacks)

- الألوان عبر متغيّرات CSS مع قيم صلبة أساسية.
- `gap` في fl/grid مدعوم في كل الأهداف الحالية.
- الخطوط: `IBM Plex Sans Arabic` ثم `Inter` ثم `system-ui` ثم عام.

## 5. الاختبار عبر المتصفّحات

- **آليًا (حاليًا):** Playwright/Chromium يفرض عدم التمرير الأفقي + سلوك الجوال.
- **التوسّع المقترح:** إضافة مشروعي `webkit` و`firefox` في `playwright.config.js` لتشغيل نفس `16-responsive.spec.js` عبر الثلاثة. مانع حاليًا: المقاس المتنقّل يستخدم viewport لا device preset (لتفادي إجبار worker على WebKit ضمن chromium-only).

راجع [DEVICE-TEST-MATRIX](DEVICE-TEST-MATRIX.md).
