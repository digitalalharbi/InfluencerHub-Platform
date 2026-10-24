<?php

namespace Tests\Perf;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * قياس أساس الأداء (PERF-01) — للقراءة فقط (GET)، بلا RefreshDatabase، على قاعدة التطوير.
 * يُشغَّل يدويًّا: DB_DATABASE=influencerhub php artisan test --filter BaselineTest
 * يقيس لكل مسار: حجم استجابة Inertia، حجم كل prop مشترك (الترجمات/الإشعارات/…)،
 * عدد استعلامات SQL، زمنها الكلّي، وأبطأ 5 استعلامات. لا يكتب شيئًا.
 */
class BaselineTest extends TestCase
{
    private const ROUTES = [
        '/app',
        '/app/creators',
        '/app/campaigns',
        '/app/reports',
        '/app/payouts',
        '/app/automation',
    ];

    public function test_measure_baseline(): void
    {
        // أداة قياس تطوير فقط — تُشغَّل على قاعدة التطوير المبذورة:
        //   DB_DATABASE=influencerhub php artisan test --filter BaselineTest
        // تتخطّى نفسها في CI/قاعدة الاختبار الفارغة (لا مستخدم وكالة) فلا تُسقِط الجناح.
        $user = User::where('email', 'admin@a.test')->first();
        if (! $user) {
            $this->markTestSkipped('BaselineTest: لا مستخدم admin@a.test (شغّلها على قاعدة التطوير).');
        }
        $this->actingAs($user);

        $rows = [];
        foreach (self::ROUTES as $path) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $t0 = microtime(true);
            $res = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->inertiaVersion()])->get($path);
            $ms = (microtime(true) - $t0) * 1000;
            $log = DB::getQueryLog();
            DB::disableQueryLog();

            $status = $res->getStatusCode();
            $body = $res->getContent();
            $bytes = strlen($body);
            $json = json_decode($body, true);
            $props = is_array($json) && isset($json['props']) ? $json['props'] : [];

            // حجم كل prop مشترك (أكبر المساهمين)
            $propSizes = [];
            foreach ($props as $k => $v) {
                $propSizes[$k] = strlen(json_encode($v, JSON_UNESCAPED_UNICODE));
            }
            arsort($propSizes);

            // حجم كل مجموعة ترجمة
            $trSizes = [];
            if (isset($props['translations']) && is_array($props['translations'])) {
                foreach ($props['translations'] as $ns => $arr) {
                    $trSizes[$ns] = strlen(json_encode($arr, JSON_UNESCAPED_UNICODE));
                }
                arsort($trSizes);
            }

            $qtime = array_sum(array_column($log, 'time'));
            usort($log, fn ($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
            $slow = array_slice(array_map(fn ($q) => round($q['time'], 1).'ms  '.$this->squash($q['query']), $log), 0, 5);

            $rows[] = [
                'path' => $path,
                'status' => $status,
                'ms' => round($ms, 1),
                'bytes' => $bytes,
                'queries' => count($log),
                'qtime' => round($qtime, 1),
                'topProps' => array_slice($propSizes, 0, 6, true),
                'translations_total' => array_sum($trSizes),
                'translations_ns' => count($trSizes),
                'topTranslations' => array_slice($trSizes, 0, 5, true),
                'slow' => $slow,
            ];
        }

        $out = "\n================ PERF BASELINE (before) ================\n";
        foreach ($rows as $r) {
            $out .= sprintf(
                "\n%-18s  status=%d  %sms  %s bytes  queries=%d  qtime=%sms\n",
                $r['path'], $r['status'], $r['ms'], number_format($r['bytes']), $r['queries'], $r['qtime']
            );
            $out .= "  translations: {$r['translations_ns']} namespaces, ".number_format($r['translations_total'])." bytes\n";
            $out .= '  top props: ';
            foreach ($r['topProps'] as $k => $b) {
                $out .= "$k=".number_format($b).'  ';
            }
            $out .= "\n  top translation namespaces: ";
            foreach ($r['topTranslations'] as $k => $b) {
                $out .= "$k=".number_format($b).'  ';
            }
            $out .= "\n  slowest: \n";
            foreach ($r['slow'] as $s) {
                $out .= "    $s\n";
            }
        }
        $out .= "\n=======================================================\n";
        fwrite(STDERR, $out);

        $this->assertTrue(true);
    }

    private function inertiaVersion(): string
    {
        $mw = new \App\Http\Middleware\HandleInertiaRequests;

        return (string) $mw->version(request());
    }

    private function squash(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', $sql);

        return strlen($sql) > 90 ? substr($sql, 0, 90).'…' : $sql;
    }
}
