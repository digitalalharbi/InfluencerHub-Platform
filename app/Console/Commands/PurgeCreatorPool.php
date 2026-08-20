<?php

namespace App\Console\Commands;

use App\Domain\AdminPool\Models\PoolCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** حذف قاعدة مبدعي مدير النظام بالكامل — كِلّ سويتش قبل الاستعراض. */
class PurgeCreatorPool extends Command
{
    protected $signature = 'pool:purge {--force}';
    protected $description = 'حذف قاعدة مبدعي مدير النظام بالكامل ومسح ملفّ الاستيراد';

    public function handle(): int
    {
        $count = PoolCreator::count();
        if (! $this->option('force') && ! $this->confirm("حذف {$count} سجلًّا نهائيًّا؟")) {
            $this->info('أُلغي.');

            return self::SUCCESS;
        }
        PoolCreator::query()->delete();
        Storage::disk('local')->delete('pool/pool.json');
        $this->info("حُذف {$count} سجلًّا، ومُسح ملفّ الاستيراد.");

        return self::SUCCESS;
    }
}
