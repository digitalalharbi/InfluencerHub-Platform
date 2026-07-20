# موقع المشروع — InfluencerHub

> هذا الملف يخصّ **InfluencerHub** وحده. لا يُدمج ولا يُقارَن بأي مشروع آخر
> (بما فيه Code X). أي ملف أو Commit أو متطلّب من مشروع آخر لا يخصّ هذا المستودع.

آخر تحقّق: 2026-07-19.

## الهوية

| العنصر | القيمة |
|---|---|
| المسار الفعلي | `/Users/mohammedalharbimacbook/Desktop/influencerhub-v2` |
| جذر Git | `/Users/mohammedalharbimacbook/Desktop/influencerhub-v2` |
| الفرع | `main` |
| آخر Commit | `a1d51e5` — رحلة: العقد يُصدَر من التعاون فيرث ما تقرّر بدل إعادة إدخاله |
| حالة الشجرة | نظيفة |
| رابط المعاينة | `http://127.0.0.1:8010` |

## أمر التشغيل

```bash
cd /Users/mohammedalharbimacbook/Desktop/influencerhub-v2
php artisan serve --host=127.0.0.1 --port=8010
```

الواجهة تُبنى قبل المعاينة (الخادم يخدم `public/build` لا Vite dev):

```bash
npm run build          # أو: npm run dev للتطوير الحيّ
npx tsc --noEmit       # يجب أن يكون نظيفًا
php artisan test       # مجموعة واحدة فقط في كل مرّة
```

### ⚠️ تنبيهان تشغيليّان مثبتان بالتجربة

1. **المسارات الجديدة لا تظهر حتى يُعاد تشغيل الخادم.** خادم `:8010` يخدم
   opcache قديمًا فيردّ 404 على مسار أُضيف للتوّ. `php artisan route:clear`
   لا يكفي — أعِد تشغيل `artisan serve`. (وقع فعليًّا عند إضافة `issue-contract`.)
2. **لا تُشغّل مجموعتَي اختبار معًا.** كلتاهما تهاجر `influencerhub_testing`
   فيقع deadlock في PostgreSQL وتظهر إخفاقات وهمية متغيّرة.

## البيئة

| العنصر | القيمة |
|---|---|
| Laravel | 12.64 · PHP 8.4 |
| قاعدة البيانات | PostgreSQL — `influencerhub` على `127.0.0.1:5432` |
| قاعدة الاختبار | `influencerhub_testing` |
| الواجهة | Inertia + React + TypeScript + Vite |
| اللغة | عربي أوّلًا · RTL |

## حسابات التجربة (محلّية فقط — ليست اعتمادات إنتاج)

مستأجر الرحلة الحالية = **14**. كلمة المرور الموحّدة: `<كلمة-مرور-محلّية>`.

| الدور | البريد |
|---|---|
| مالك الوكالة | `owner1784445049342@example.com` (كلمة المرور `<كلمة-مرور-محلّية>`) |
| مدير حملات | `cm@demo.test` |
| مالية | `fin@demo.test` |
| جهة اتصال العميل | `client@demo.test` |
| المبدعة ريم العتيبي | `reem@demo.test` |

**تنبيه:** حساب `reem@demo.test` أُنشئ يدويًّا لفكّ انسداد الرحلة، لا عبر مسار
منتَج. المسار الدائم (دعوة المبدع) لم يُبنَ بعد — انظر `CONTINUATION-STATE.md`.
