<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// محرّك SLA — يعمل كل ساعة (تذكيرات + رصد تجاوزات طلبات الخدمة).
// انتهاء صلاحية قصير للقفل (50د) حتى يتعافى قفل عالق قبل الدورة التالية.
use Illuminate\Support\Facades\Schedule;
Schedule::command('sla:scan')->hourly()->withoutOverlapping(50);

// نبضة المجدول كل دقيقة — تُثبت لصفحة صحّة النظام أنّ المجدول يعمل فعلًا.
// بلا withoutOverlapping عمدًا: النبضة كتابة لحظية عديمة التداخل، وقفل التداخل
// (صلاحيته 24س افتراضًا) يعلَق إذا قُتِلت العملية أثناء النشر فيتخطّى كلّ نبضة
// لاحقة يومًا كاملًا — وهو سبب ظهور «المجدول متوقّف» رغم عمل الحاوية.
Schedule::command('ops:scheduler-heartbeat')->everyMinute();

// التقارير المجدولة — كل ساعة؛ قفل قصير الصلاحية (50د) يتعافى ذاتيًّا.
Schedule::command('reports:run-scheduled')->hourly()->withoutOverlapping(50);

// متابعة الفواتير المتأخّرة — يوميًّا؛ تذكير مرّة واحدة لكل فاتورة (علامة overdue_notified_at
// تمنع التكرار، فإعادة التشغيل آمنة). مبنيّ على due_date الحقيقيّة لا على موعد مُختلَق.
Schedule::command('invoices:scan-overdue')->dailyAt('08:00')->withoutOverlapping(50);
