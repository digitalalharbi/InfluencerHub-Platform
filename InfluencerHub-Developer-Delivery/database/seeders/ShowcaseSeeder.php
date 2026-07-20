<?php

namespace Database\Seeders;

use App\Support\Showcase\ShowcaseBuilder;
use Illuminate\Database\Seeder;

/**
 * بذرة بيئة العرض التجريبية (Showcase). محلية/اختبار فقط — تُرفض في الإنتاج.
 * Idempotent: تعيد الضبط ثم تبني بيانات مترابطة على مستأجر مستقل (slug=showcase).
 */
class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('ShowcaseSeeder ممنوع في الإنتاج.');
            return;
        }
        $summary = (new ShowcaseBuilder())->build();
        foreach ($summary as $k => $v) {
            $this->command?->line(sprintf('  %-16s %s', $k, $v));
        }
    }
}
