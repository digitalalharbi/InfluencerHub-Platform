<?php

namespace App\Console\Commands;

use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\AdminPool\Support\CreatorNormalizer as N;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Storage};

/**
 * استيعاب (ingest) قاعدة المؤثرين من JSON خام (حقول مسموحة فقط) عبر المطبِّع المُختبَر.
 *
 * يقرأ ملفًّا خارج git (storage/app/private/pool/raw_allowed.json) أُنتِج بمستخرِج
 * خاصّ أسقط كلّ حقل محظور (موظّف/متجر/مصدر/بنك/عنوان/شحنات). هنا يُطبَّع كلّ صفّ عبر
 * CreatorNormalizer (هاتف/متابعون/منصّة/رابط)، ثمّ يُزال التكرار حتميًّا، ثمّ upsert
 * على (platform, account_url) — فإعادة التشغيل تُحدّث لا تُكرّر.
 *
 * حارس allow-list صلب: لا يُكتَب إلا الأعمدة المسموحة. `--dry-run` يعرض الإحصاءات
 * بلا كتابة. لا يمرّ اسم مصدر/متجر تجاري إلى القاعدة أبدًا.
 */
class IngestCreatorPool extends Command
{
    protected $signature = 'pool:ingest {--file=pool/raw_allowed.json} {--dry-run : عرض الإحصاءات بلا كتابة}';

    protected $description = 'استيعاب قاعدة المؤثرين من JSON خام مع تطبيع وإزالة تكرار حتميّة';

    /** الأعمدة المسموح كتابتها (حارس ضدّ تسرّب أيّ حقل محظور). */
    private const ALLOWED = [
        'name', 'phone', 'platform', 'account_url', 'followers', 'likes', 'tier', 'gender',
        'categories', 'shows_face', 'region', 'city', 'rating',
        'price_post_minor', 'price_coverage_minor', 'cost_post_minor', 'cost_coverage_minor', 'source_type',
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

        $stats = ['read' => count($rows), 'rejected_no_platform' => 0, 'invalid_url' => 0, 'invalid_contact' => 0, 'strong_dupes_merged' => 0];
        $byIdentity = [];

        foreach ($rows as $raw) {
            $name = trim((string) ($raw['name'] ?? ''));
            $platform = N::platform($raw['account_url_raw'] ?? null, $raw['platform_hint'] ?? null);
            $url = N::accountUrl($raw['account_url_raw'] ?? null);
            if ($name === '' || $platform === null) {
                $stats['rejected_no_platform']++;
                continue;
            }
            if ($url === null) {
                $stats['invalid_url']++;
                continue; // منتج اكتشاف يحتاج رابط ملف — لا نُلفّق رابطًا
            }
            $phone = N::phone($raw['phone_raw'] ?? null);
            if (($raw['phone_raw'] ?? null) && $phone === null) {
                $stats['invalid_contact']++; // هاتف مشوّه — نُبقي السجلّ بلا هاتف
            }

            $rec = [
                'name' => mb_substr($name, 0, 190),
                'phone' => $phone,
                'platform' => $platform,
                'account_url' => $url,
                'followers' => N::count($raw['followers_raw'] ?? null),
                'likes' => N::count($raw['likes_raw'] ?? null),
                'tier' => N::tier($raw['tier_raw'] ?? null),
                'gender' => N::gender($raw['gender_raw'] ?? null),
                'categories' => N::categories($raw['categories_raw'] ?? []),
                'shows_face' => N::showsFace($raw['shows_face_raw'] ?? null),
                'region' => $this->clean($raw['region_raw'] ?? null),
                'city' => $this->clean($raw['city_raw'] ?? null),
                'rating' => N::rating($raw['rating_raw'] ?? null),
                'price_post_minor' => $this->minor($raw['sell_post_raw'] ?? null),
                'price_coverage_minor' => $this->minor($raw['sell_coverage_raw'] ?? null),
                'cost_post_minor' => $this->minor($raw['cost_post_raw'] ?? null),
                'cost_coverage_minor' => $this->minor($raw['cost_coverage_raw'] ?? null),
                'source_type' => (($raw['creator_kind'] ?? null) === 'ugc') ? 'ugc' : 'celebrity',
            ];

            $key = N::identityKey($platform, $url);
            if (isset($byIdentity[$key])) {
                $byIdentity[$key] = $this->mergeFillMissing($byIdentity[$key], $rec);
                $stats['strong_dupes_merged']++;
            } else {
                $byIdentity[$key] = $rec;
            }
        }

        $stats['accepted'] = count($byIdentity);
        $platformTotals = collect($byIdentity)->countBy('platform')->all();

        $this->report($stats, $platformTotals);

        if ($this->option('dry-run')) {
            $this->warn('تشغيل تجريبي (dry-run) — لم تُكتب أيّ بيانات.');

            return self::SUCCESS;
        }

        $now = now();
        $created = 0;
        $updated = 0;
        foreach (array_chunk(array_values($byIdentity), 500) as $chunk) {
            $payload = [];
            foreach ($chunk as $rec) {
                // حارس صلب: لا يمرّ إلا المسموح
                $clean = array_intersect_key($rec, array_flip(self::ALLOWED));
                $clean['categories'] = $clean['categories'] ? json_encode($clean['categories'], JSON_UNESCAPED_UNICODE) : null;
                $clean['shows_face'] = $rec['shows_face']; // bool|null
                $clean['imported_at'] = $now;
                $clean['created_at'] = $now;
                $clean['updated_at'] = $now;
                $payload[$clean['platform'] . '|' . $clean['account_url']] = $clean;
            }
            $existing = DB::table('admin_creator_pool')
                ->whereIn(DB::raw("platform || '|' || account_url"), array_keys($payload))
                ->count();
            $updated += $existing;
            $created += count($payload) - $existing;

            DB::table('admin_creator_pool')->upsert(
                array_values($payload),
                ['platform', 'account_url'],
                ['name', 'phone', 'followers', 'likes', 'tier', 'gender', 'categories', 'shows_face',
                    'region', 'city', 'rating', 'price_post_minor', 'price_coverage_minor',
                    'cost_post_minor', 'cost_coverage_minor', 'source_type', 'imported_at', 'updated_at'],
            );
        }

        $this->info("أُنشئ: {$created} · حُدّث: {$updated} · الإجمالي الآن: " . PoolCreator::count());

        return self::SUCCESS;
    }

    private function clean(?string $v): ?string
    {
        $v = trim((string) N::latinDigits($v ?? ''));

        return ($v === '' || $v === '0' || $v === '0.0') ? null : mb_substr($v, 0, 60);
    }

    /** سعر نصّي «5000.0» → وحدات صغرى (هللات). null/0 → null. */
    private function minor(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = N::latinDigits((string) $raw);
        if (! is_numeric($s)) {
            return null;
        }
        $riyals = (float) $s;

        return $riyals > 0 ? (int) round($riyals * 100) : null;
    }

    /** يملأ القيم الغائبة من نسخة مكرّرة (الأولى تفوز، والفراغ يُكمَّل). */
    private function mergeFillMissing(array $keep, array $incoming): array
    {
        foreach ($incoming as $k => $v) {
            $empty = $keep[$k] === null || $keep[$k] === '' || $keep[$k] === [];
            if ($empty && $v !== null && $v !== '' && $v !== []) {
                $keep[$k] = $v;
            }
        }

        return $keep;
    }

    private function report(array $stats, array $platformTotals): void
    {
        $this->newLine();
        $this->info('— ملخّص الاستيعاب —');
        $this->table(['المقياس', 'العدد'], [
            ['صفوف مقروءة', $stats['read']],
            ['مقبولة (بعد إزالة التكرار)', $stats['accepted']],
            ['مرفوضة (بلا منصّة/اسم)', $stats['rejected_no_platform']],
            ['روابط غير صالحة (مُستبعَدة)', $stats['invalid_url']],
            ['هواتف مشوّهة (بلا هاتف)', $stats['invalid_contact']],
            ['تكرارات قويّة مدموجة', $stats['strong_dupes_merged']],
        ]);
        $rows = [];
        foreach ($platformTotals as $p => $c) {
            $rows[] = [$p, $c];
        }
        $this->table(['المنصّة', 'العدد'], $rows);
    }
}
