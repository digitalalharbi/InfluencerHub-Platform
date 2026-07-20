# دليل النشر — InfluencerHub V2

> لم يُنفَّذ نشر فعلي: البنية التحتية (خادم/نطاق/شهادات/أسرار) قرار وصول خارجي.
> هذه الخطوات مكتوبة لتُنفَّذ كما هي عند توفّرها، وكل ما يمكن التحقّق منه محليًا مُتحقَّق منه.

## 1) المتطلبات

| المكوّن | الإصدار | ملاحظة |
|---|---|---|
| PHP | 8.4 | مع `pdo_pgsql`, `mbstring`, `intl`, `gd` |
| PostgreSQL | 14+ | المخطّط يعتمد `jsonb` وفهارس جزئية |
| Node | 20+ | للبناء فقط، ليس وقت التشغيل |
| Redis | اختياري | للطابور والذاكرة المؤقتة (يُستحسن) |

## 2) متغيّرات البيئة الحرجة

```dotenv
APP_ENV=production
APP_DEBUG=false                 # حرِج: لا تُظهر أثر الأخطاء
APP_URL=https://<domain>
APP_KEY=                        # php artisan key:generate

DB_CONNECTION=pgsql
DB_HOST= DB_PORT=5432 DB_DATABASE= DB_USERNAME= DB_PASSWORD=

SESSION_DRIVER=database         # مطلوب لعرض/إنهاء الجلسات النشطة
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

QUEUE_CONNECTION=redis          # أو database
CACHE_STORE=redis

MAIL_MAILER=smtp                # بريد فعلي — لا log في الإنتاج
MAIL_HOST= MAIL_PORT= MAIL_USERNAME= MAIL_PASSWORD=

FILESYSTEM_DISK=local           # الملفات خاصة؛ لا تجعلها public
```

> **لا تضع أسرارًا في Git.** `.env` و`.env.production` مُتجاهَلان أصلًا.

## 3) خطوات النشر

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link          # فقط إن استُخدم قرص public
```

### العمليات المستمرة
```bash
# طابور المهام (إشعارات، معالجة الملفات)
php artisan queue:work --queue=default --tries=3 --max-time=3600

# المجدول (تجميعات الاستهلاك، تنبيهات SLA)
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

## 4) تحقّق بعد النشر

```bash
php artisan about                 # البيئة والاتصالات
curl -I https://<domain>/up       # فحص الصحة
php artisan route:list | wc -l    # المسارات مُحمَّلة
```

قائمة يدوية سريعة:
- [ ] `/login` يعمل، والدخول يصل إلى `/app`.
- [ ] بوابات `/client` `/creator` `/partner` تفتح لأعضائها فقط.
- [ ] عضو بوابة لا يفتح `/app` (يجب 403).
- [ ] `/app/preview` **محجوب** في الإنتاج.
- [ ] رفع مستند ثم تنزيله يعمل ولا يُنتج رابطًا عامًا.
- [ ] تغيير كلمة المرور يُنهي الجلسات الأخرى.

## 5) التراجع (Rollback)

```bash
git checkout <previous-tag>
composer install --no-dev --optimize-autoloader && npm ci && npm run build
php artisan migrate:rollback --step=1     # فقط إن كانت الهجرة الأخيرة قابلة للتراجع
php artisan config:cache && php artisan route:cache
```

> راجع الهجرة قبل التراجع: الهجرات التي تحذف أعمدة **لا** تُسترجع بياناتها.
> خذ نسخة احتياطية قبل أي `migrate` في الإنتاج.

## 6) ما يجب ضبطه قبل الإطلاق التجاري

هذه ليست خطوات نشر بل قرارات مفتوحة (راجع `PRODUCTION-CHECKLIST.md` و`EXTERNAL-BLOCKERS.md`):

1. **الفوترة**: لا مزوّد دفع حقيقي ولا فواتير ولا ledger ولا webhooks. الإطلاق التجاري يتطلّب اختيار مزوّد واعتماداته وسياسة الضريبة/الفوترة الإلكترونية.
2. **SMS**: `NullSmsSender` — رموز OTP لا تصل عبر الجوال.
3. **التكاملات الحيّة**: كل المنصّات يدوية؛ الاكتشاف الحيّ ينتظر اعتمادات.
4. **2FA**: الحالة تُعرض ولا يوجد تدفّق تفعيل.
