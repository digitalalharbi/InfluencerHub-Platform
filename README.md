# InfluencerHub Platform

منصّة إدارة التسويق مع صنّاع المحتوى: من طلب الحملة إلى تسوية المستحقّات.
**عربية أوّلًا · RTL · متعدّدة المستأجرين.**

## البنية التقنية

| العنصر | الإصدار |
|---|---|
| PHP | 8.4 (الحد الأدنى 8.2) |
| Laravel | 12 |
| قاعدة البيانات | **PostgreSQL 16 — إلزامية** |
| Node | 24 (يعمل على 20+) |
| الواجهة | Inertia 3 + React 19 + TypeScript 7 + Vite 7 |
| الاختبارات | PHPUnit (1043 اختبارًا) + Playwright |

**PostgreSQL ليست تفضيلًا:** الشيفرة تستعمل فهارس جزئية
(`CREATE UNIQUE INDEX … WHERE`) و`ilike`. لا تعمل على MySQL ولا SQLite.

## المحتويات

| المسار | الوصف |
|---|---|
| `InfluencerHub-Developer-Delivery/` | الشيفرة المصدرية |
| `InfluencerHub-Developer-Delivery-2026-07-20.zip` | نفسها مضغوطة، مع SHA-256 |

ابدأ من `InfluencerHub-Developer-Delivery/READ-ME-FIRST.md`، والتوثيق الكامل
في `InfluencerHub-Developer-Delivery/docs/`.

## التشغيل من الصفر

```bash
cd InfluencerHub-Developer-Delivery
composer install && npm ci

cp .env.example .env
php artisan key:generate

createdb influencerhub
createdb influencerhub_testing      # لازمة للاختبارات
# ثم عدّل DB_USERNAME / DB_PASSWORD في .env
php artisan migrate

npm run build
php artisan serve --host=127.0.0.1 --port=8010
```

## الأسرار

لا يوجد `.env` في المستودع — بالتصميم. يُولَّد `APP_KEY` بـ`key:generate`.
