<?php

namespace App\Console\Commands;

use App\Domain\Content\Services\ContentPublishReminderService;
use Illuminate\Console\Command;

/** تذكير موعد نشر المحتوى المُجدوَل — تذكير مرّة واحدة عند اقتراب/فوات الموعد (يعمل مجدولًا). */
class ScanScheduledContentCommand extends Command
{
    protected $signature = 'content:scan-publishing';

    protected $description = 'مسح المحتوى المُجدوَل الذي اقترب موعد نشره أو فات ولم يُنشَر، وتذكير المعنيّين مرّة واحدة';

    public function handle(ContentPublishReminderService $service): int
    {
        $r = $service->scan();
        $this->info("Scheduled content: scanned={$r['scanned']} notified={$r['notified']}");

        return self::SUCCESS;
    }
}
