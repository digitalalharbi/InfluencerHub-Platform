# Usage Metering (Phase 2)

`UsageMeterService` — ذرّي، idempotent، معزول بالمستأجر، واعٍ بالدورة (شهرية).

## العمليات
- `currentUsage / remaining / isAllowed`
- `consume($org,$feature,$amount,$idempotencyKey,$actor)` — داخل transaction: `lockForUpdate` على صف `usage_aggregates`، فحص الحد، إدراج `usage_records` (unique على idempotency_key يمنع العدّ المزدوج)، زيادة التجميع. يرمي `EntitlementLimitExceeded` عند التجاوز.
- `release` — سجل سالب + إنقاص.
- `recalculate` — إعادة بناء التجميع من مجموع السجلات (مصدر الحقيقة).

## الضمانات (مُختبَرة)
- **Idempotency**: نفس المفتاح لا يُعدّ مرتين.
- **Atomic/Concurrency**: قفل صف التجميع.
- **Isolation**: مفاتيح unique لكل (organization, feature, period).
- **Period reset**: التجميع مفتاحه `period_start` (شهر)؛ دورة جديدة = صف جديد = تصفير تلقائي.
