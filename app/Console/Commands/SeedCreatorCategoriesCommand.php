<?php
namespace App\Console\Commands;
use App\Domain\Creators\Models\CreatorCategory;
use Illuminate\Console\Command;
/** تصنيفات المبدعين العامة (tenant_id=null). قابلة للإدارة لاحقًا من لوحة النظام. */
class SeedCreatorCategoriesCommand extends Command {
    protected $signature = 'creators:seed-categories';
    protected $description = 'بذر تصنيفات المبدعين العامة';
    private const CATS = [
        ['fashion','أزياء','Fashion'], ['beauty','جمال','Beauty'], ['food','طعام','Food'], ['travel','سفر','Travel'],
        ['technology','تقنية','Technology'], ['gaming','ألعاب','Gaming'], ['education','تعليم','Education'],
        ['business','أعمال','Business'], ['sports','رياضة','Sports'], ['automotive','سيارات','Automotive'],
        ['family','عائلة','Family'], ['lifestyle','نمط حياة','Lifestyle'], ['health','صحة','Health'],
        ['entertainment','ترفيه','Entertainment'], ['other','أخرى','Other'],
    ];
    public function handle(): int {
        foreach (self::CATS as $i => [$slug, $ar, $en]) {
            CreatorCategory::updateOrCreate(['tenant_id' => null, 'slug' => $slug],
                ['name_ar' => $ar, 'name_en' => $en, 'sort_order' => $i, 'is_active' => true]);
        }
        $this->info('تصنيفات المبدعين: ' . count(self::CATS));
        return self::SUCCESS;
    }
}
