# واجهات المبدعين (Web) — Phase 4

> واجهات الويب بجلسة (RTL). واجهات JSON API للمبدعين تُضاف عند ربطها بالحملات (مرحلة لاحقة).

## عامة (بلا دخول)
- `GET /join`, `GET /join/creator`
- `POST /join/creator` — إنشاء مسودة (throttle 10/min)
- `GET /join/creator/{reference}/status`
- `POST /join/creator/{reference}/continue` (30/min)
- `POST /join/creator/{reference}/verify-email` (6/min)
- `POST /join/creator/{reference}/verify-phone` (6/min)
- `POST /join/creator/{reference}/submit` (10/min)

## إدارة الوكالة (auth+tenant، policy)
- `GET /app/creator-applications` (بحث/فلاتر)
- `GET /app/creator-applications/{application}` (تبويبات)
- `POST .../assign|request-completion|reject|suspend|approve|note|message`
- `GET /app/creators` (+`?type=`), `POST /app/creators`, `GET /app/creators/{creator}`

## بوابة المبدع (auth+creator)
- `GET/POST /creator/{login,dashboard,profile,platforms,services,portfolio,mowthooq,financial,notifications}`
- الاستجابات: 403 (غير مصرّح/غير مبدع)، 404 (عزل مستأجر)، redirect back مع رسائل.
