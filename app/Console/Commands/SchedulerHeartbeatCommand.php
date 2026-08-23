<?php

namespace App\Console\Commands;

use App\Domain\Ops\Services\SystemHealthService;
use Illuminate\Console\Command;

/**
 * نبضة المجدول — تُكتب كل دقيقة عبر المجدول. صفحة صحّة النظام تقرأها فتعرف إن كان
 * المجدول يعمل فعلًا على الإنتاج (لا افتراض). قِدَم النبضة = المجدول متوقّف.
 */
class SchedulerHeartbeatCommand extends Command
{
    protected $signature = 'ops:scheduler-heartbeat';
    protected $description = 'يسجّل نبضة المجدول لمراقبة الصحّة';

    public function handle(): int
    {
        // متجر مشترك (قاعدة البيانات) صراحةً حتى تراها حاوية الويب مهما كان
        // CACHE_STORE الافتراضي (file لكل حاوية لا يُرى عبرها). [[system-health]]
        SystemHealthService::heartbeatStore()->put(
            SystemHealthService::HEARTBEAT_KEY, now()->timestamp, now()->addHours(2)
        );

        return self::SUCCESS;
    }
}
