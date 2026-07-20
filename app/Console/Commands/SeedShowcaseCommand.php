<?php

namespace App\Console\Commands;

use App\Support\Showcase\ShowcaseBuilder;
use Illuminate\Console\Command;

/**
 * توليد بيئة العرض التجريبية المترابطة (محلي/اختبار فقط، ممنوع في الإنتاج).
 * Idempotent: يعيد الضبط ثم يبني على مستأجر مستقل (slug=showcase).
 */
class SeedShowcaseCommand extends Command
{
    protected $signature = 'preview:seed-showcase';
    protected $description = 'توليد بيانات عرض تجريبية مترابطة (Showcase) — غير إنتاجي';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('ممنوع في الإنتاج.');
            return self::FAILURE;
        }
        $this->info('توليد بيئة العرض التجريبية…');
        $summary = (new ShowcaseBuilder())->build();
        $this->info('تم. الملخّص:');
        foreach ($summary as $k => $v) {
            $this->line(sprintf('  %-16s %s', $k, $v));
        }
        $this->line('بيانات الدخول في storage/app/private/showcase-credentials.txt (غير متتبَّعة).');
        return self::SUCCESS;
    }
}
