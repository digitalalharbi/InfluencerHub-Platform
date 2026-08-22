<?php

namespace App\Console\Commands;

use App\Domain\AdminPool\Models\PoolCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Storage};

/**
 * استيراد قاعدة مبدعي مدير النظام من ملف JSON منظَّف.
 *
 * لا يقرأ Excel ولا يحوي بيانات في الكود: يقرأ ملفًّا منظَّفًا سلفًا (خارج git)
 * تُستبعَد منه الحقول المحظورة قبل وصولها إليه. قابل للتكرار على أي بيئة بوضع
 * الملف في storage/app/private/pool/pool.json ثم تشغيل الأمر.
 */
class ImportCreatorPool extends Command
{
    protected $signature = 'pool:import {--file=pool/pool.json} {--fresh : يمسح القاعدة قبل الاستيراد}';

    protected $description = 'استيراد قاعدة مبدعي مدير النظام من JSON منظَّف';

    private const ALLOWED = [
        'name', 'phone', 'platform', 'account_url', 'followers', 'tier', 'gender',
        'categories', 'price_post_minor', 'price_coverage_minor', 'cost_post_minor', 'cost_coverage_minor', 'shows_face',
        'region', 'city', 'rating', 'likes', 'store', 'source_type',
    ];

    public function handle(): int
    {
        $rel = $this->option('file');
        if (! Storage::disk('local')->exists($rel)) {
            $this->error("الملف غير موجود: storage/app/{$rel}");

            return self::FAILURE;
        }

        $rows = json_decode(Storage::disk('local')->get($rel), true);
        if (! is_array($rows)) {
            $this->error('JSON غير صالح.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            PoolCreator::query()->delete();
            $this->warn('مُسحت القاعدة قبل الاستيراد.');
        }

        $now = now();
        $imported = 0;
        $chunks = array_chunk($rows, 500);
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($chunks as $chunk) {
            $payload = [];
            foreach ($chunk as $r) {
                // حارس صلب: لا يمرّ إلا المسموح، ويُرفض أي مفتاح محظور تسلّل
                $clean = array_intersect_key($r, array_flip(self::ALLOWED));
                if (empty($clean['name']) || empty($clean['platform']) || empty($clean['account_url'])) {
                    continue;
                }
                $clean['categories'] = isset($clean['categories'])
                    ? json_encode($clean['categories'], JSON_UNESCAPED_UNICODE) : null;
                $clean['source_type'] ??= 'celebrity';
                $clean['imported_at'] = $now;
                $clean['created_at'] = $now;
                $clean['updated_at'] = $now;
                // إزالة التكرار داخل الدفعة: upsert لا يقبل صفّين بنفس المفتاح
                $payload[$clean['platform'] . '|' . $clean['account_url']] = $clean;
            }
            $payload = array_values($payload);
            if ($payload) {
                // upsert على (platform, account_url): إعادة الاستيراد تُحدّث لا تُكرّر
                DB::table('admin_creator_pool')->upsert(
                    $payload,
                    ['platform', 'account_url'],
                    ['name', 'phone', 'followers', 'tier', 'gender', 'categories',
                        'price_post_minor', 'price_coverage_minor', 'cost_post_minor', 'cost_coverage_minor', 'shows_face',
                        'region', 'city', 'rating', 'likes', 'store', 'source_type', 'imported_at', 'updated_at'],
                );
                $imported += count($payload);
            }
            $bar->advance(count($chunk));
        }
        $bar->finish();
        $this->newLine(2);
        $this->info("استُورد/حُدّث: {$imported} مبدعًا. الإجمالي في القاعدة: " . PoolCreator::count());

        return self::SUCCESS;
    }
}
