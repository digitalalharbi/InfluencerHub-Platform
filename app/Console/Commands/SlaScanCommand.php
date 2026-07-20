<?php
namespace App\Console\Commands;
use App\Domain\Automation\Services\SlaEngineService;
use Illuminate\Console\Command;
/** مسح SLA لطلبات الخدمة — تذكيرات قبل الاستحقاق ورصد التجاوزات (يعمل مجدولًا). */
class SlaScanCommand extends Command {
    protected $signature = 'sla:scan';
    protected $description = 'مسح SLA: تذكيرات ورصد تجاوزات طلبات الخدمة';
    public function handle(SlaEngineService $engine): int {
        $r = $engine->scan();
        $this->info("SLA scan: scanned={$r['scanned']} breaches={$r['breaches']} reminders={$r['reminders']}");
        return 0;
    }
}
