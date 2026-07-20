<?php
namespace App\Console\Commands;
use App\Domain\Creators\Jobs\FinalizeCreatorFilesJob;
use App\Domain\Creators\Models\CreatorApplicationDocument;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;
/** يعيد جدولة إتمام نقل الملفات غير المكتملة (pending/failed/copying). */
class ReconcileCreatorFilesCommand extends Command {
    protected $signature = 'creators:reconcile-files';
    protected $description = 'مصالحة نقل ملفات المبدعين غير المكتملة';
    public function handle(): int {
        $appIds = TenantContext::withBypass(fn () => CreatorApplicationDocument::withoutGlobalScopes()
            ->whereIn('transfer_status', ['pending', 'failed', 'copying'])
            ->whereNotNull('application_id')->distinct()->pluck('application_id'));
        foreach ($appIds as $id) { FinalizeCreatorFilesJob::dispatchSync($id); }
        $this->info('أُعيدت جدولة ' . $appIds->count() . ' طلبًا لإتمام الملفات.');
        return self::SUCCESS;
    }
}
