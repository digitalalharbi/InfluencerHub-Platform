<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// محرّك SLA — يعمل كل ساعة (تذكيرات + رصد تجاوزات طلبات الخدمة)
use Illuminate\Support\Facades\Schedule;
Schedule::command('sla:scan')->hourly()->withoutOverlapping();
