# InfluencerHub Platform

منصّة إدارة التسويق مع صنّاع المحتوى: من طلب الحملة إلى تسوية المستحقّات.
**عربية أوّلًا · RTL · متعدّدة المستأجرين.**

> ابدأ من **[`READ-ME-FIRST.md`](READ-ME-FIRST.md)** — فيه الشرح الكامل.
> التوثيق التفصيلي في **[`docs/`](docs/)** (68 ملفًا).

## البنية التقنية

| العنصر | الإصدار |
|---|---|
| PHP | 8.4 (الحد الأدنى 8.2) |
| Laravel | 12 |
| قاعدة البيانات | **PostgreSQL 16 — إلزامية** |
| Node | 24 (يعمل على 20+) |
| الواجهة | Inertia 3 + React 19 + TypeScript 7 + Vite 7 |
| الاختبارات | PHPUnit + Playwright |

**PostgreSQL ليست تفضيلًا:** الشيفرة تستعمل فهارس جزئية
(`CREATE UNIQUE INDEX … WHERE`) و`ilike`. لا تعمل على MySQL ولا SQLite.

## التشغيل من الصفر

```bash
git clone https://github.com/digitalalharbi/InfluencerHub-Platform.git
cd InfluencerHub-Platform

composer install
npm ci

cp .env.example .env
php artisan key:generate

createdb influencerhub
createdb influencerhub_testing      # لازمة للاختبارات
# ثم عدّل DB_USERNAME / DB_PASSWORD في .env
php artisan migrate

npm run build
php artisan serve --host=127.0.0.1 --port=8010
```

## ما ليس في المستودع — وهذا مقصود

`vendor/` و`node_modules/` و`public/build/` تُولَّد بالأوامر أعلاه.
ملفّات القفل (`composer.lock` · `package-lock.json`) مضمَّنة، فتُثبَّت الإصدارات
نفسها بالضبط.

`.env` لا يُرفع أبدًا — يحوي مفاتيح سرّية. يُولَّد `APP_KEY` بـ`key:generate`.
