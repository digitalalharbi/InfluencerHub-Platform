<?php

namespace App\Console\Commands;

use App\Support\Showcase\ShowcaseBuilder;
use Illuminate\Console\Command;

/**
 * حذف بيئة العرض التجريبية بالكامل (المستأجر Cascade + مستخدمو العرض).
 * محلي/اختبار فقط، ممنوع في الإنتاج.
 */
class ResetShowcaseCommand extends Command
{
    protected $signature = 'preview:reset-showcase';
    protected $description = 'حذف بيانات العرض التجريبية (Showcase) بالكامل — غير إنتاجي';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('ممنوع في الإنتاج.');
            return self::FAILURE;
        }
        (new ShowcaseBuilder())->reset();
        $this->info('تم حذف بيئة العرض التجريبية.');
        return self::SUCCESS;
    }
}
