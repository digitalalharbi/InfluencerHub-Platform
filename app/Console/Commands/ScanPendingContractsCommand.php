<?php

namespace App\Console\Commands;

use App\Domain\Contracts\Services\ContractSignatureReminderService;
use Illuminate\Console\Command;

/** تذكير توقيع العقود المُرسَلة المعلّقة (>٧٢ ساعة بلا توقيع) — مرّة واحدة، يعمل مجدولًا. */
class ScanPendingContractsCommand extends Command
{
    protected $signature = 'contracts:scan-pending-signature';

    protected $description = 'مسح العقود المُرسَلة التي لم يوقّعها الطرف خلال المهلة، وتذكير الطرف والوكالة مرّة واحدة';

    public function handle(ContractSignatureReminderService $service): int
    {
        $r = $service->scan();
        $this->info("Pending contracts: scanned={$r['scanned']} notified={$r['notified']}");

        return self::SUCCESS;
    }
}
