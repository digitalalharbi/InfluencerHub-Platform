<?php

namespace App\Console\Commands;

use App\Domain\Campaigns\Services\ShortlistDecisionReminderService;
use Illuminate\Console\Command;

/** تذكير قرار العميل على الترشيحات المعلّقة (>٧٢ ساعة بلا قرار) — مرّة واحدة، يعمل مجدولًا. */
class ScanPendingShortlistsCommand extends Command
{
    protected $signature = 'shortlists:scan-pending-decision';

    protected $description = 'مسح إصدارات الترشيح المُرسَلة للعميل ولم يُبتّ فيها خلال المهلة، وتذكير العميل والوكالة مرّة واحدة';

    public function handle(ShortlistDecisionReminderService $service): int
    {
        $r = $service->scan();
        $this->info("Pending shortlists: scanned={$r['scanned']} notified={$r['notified']}");

        return self::SUCCESS;
    }
}
