<?php

namespace App\Console\Commands;

use App\Domain\Collaborations\Services\CollaborationResponseReminderService;
use Illuminate\Console\Command;

/** تذكير ردّ المؤثر على عروض التعاون المعلّقة (>٤٨ ساعة بلا ردّ) — مرّة واحدة، يعمل مجدولًا. */
class ScanPendingCollaborationsCommand extends Command
{
    protected $signature = 'collaborations:scan-pending-response';

    protected $description = 'مسح عروض التعاون المعروضة التي لم يردّ عليها المؤثر خلال المهلة (٤٨ ساعة) وتذكيره مرّة واحدة';

    public function handle(CollaborationResponseReminderService $service): int
    {
        $r = $service->scan();
        $this->info("Pending collaborations: scanned={$r['scanned']} notified={$r['notified']}");

        return self::SUCCESS;
    }
}
