<?php

namespace App\Console\Commands;

use App\Domain\Finance\Services\InvoiceReminderService;
use Illuminate\Console\Command;

/** متابعة الفواتير المتأخّرة — تذكير مرّة واحدة عند التجاوز (يعمل مجدولًا يوميًّا). */
class ScanOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:scan-overdue';

    protected $description = 'مسح الفواتير المتأخّرة وإشعار المسؤولين مرّة واحدة (متابعة التحصيل)';

    public function handle(InvoiceReminderService $service): int
    {
        $r = $service->scan();
        $this->info("Overdue invoices: scanned={$r['scanned']} notified={$r['notified']}");

        return self::SUCCESS;
    }
}
