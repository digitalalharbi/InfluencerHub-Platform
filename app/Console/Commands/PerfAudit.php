<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * قياس أداء الصفحات على بيانات فعلية: زمن الخادم، عدد الاستعلامات،
 * تكرار الاستعلام نفسه (مؤشّر N+1)، وحجم حمولة Inertia.
 *
 * أداة تطوير: تُرفض في الإنتاج. تمرّ الطلبات عبر الـkernel كاملًا
 * (وسائط + سياسات) فالأرقام واقعية لا تقديرية.
 */
class PerfAudit extends Command
{
    protected $signature = 'perf:audit {--user= : بريد المستخدم} {--paths= : مسارات مفصولة بفواصل} {--json= : ملف لحفظ النتيجة}';

    protected $description = 'قياس زمن/استعلامات/حمولة الصفحات (تطوير فقط)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('غير متاح في الإنتاج.');

            return self::FAILURE;
        }

        $email = (string) $this->option('user');
        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("لا مستخدم بالبريد: {$email}");

            return self::FAILURE;
        }

        $paths = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('paths')))));
        if (! $paths) {
            $this->error('مرّر --paths');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($paths as $path) {
            $rows[] = $this->measure($user, $path);
        }

        $this->table(
            ['المسار', 'الحالة', 'ms', 'استعلامات', 'مكرّرة', 'أبطأ ms', 'حمولة KB'],
            array_map(fn (array $r) => [
                $r['path'], $r['status'], $r['ms'], $r['queries'], $r['duplicated'], $r['slowestMs'], $r['payloadKb'],
            ], $rows),
        );

        foreach ($rows as $r) {
            if ($r['duplicated'] > 0) {
                $this->warn("N+1 محتمل في {$r['path']}: {$r['duplicated']} استعلامًا مكرّرًا");
                foreach (array_slice($r['repeats'], 0, 6) as $line) $this->line('    ' . $line);
            }
        }

        if ($file = $this->option('json')) {
            file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("حُفظت النتيجة في {$file}");
        }

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function measure(User $user, string $path): array
    {
        // كل قياس يبدأ بلا سياق — الطلب المُحاكى يبني سياقه بوسائطه كطلب حقيقي.
        // والسياق يعود بعده: `reset()` كان يمسح ما بناه القياس السابق ولا يستعيد.
        return TenantContext::withTenant(null, function () use ($user, $path) {
        Auth::login($user);

        $queries = [];
        DB::flushQueryLog();
        DB::listen(function ($q) use (&$queries) {
            // نطبّع الأرقام لكشف نفس الاستعلام بمعاملات مختلفة (نمط N+1)
            $queries[] = ['sql' => preg_replace('/\d+/', '?', $q->sql), 'time' => $q->time];
        });

        $request = Request::create($path, 'GET');
        $request->headers->set('Accept', 'text/html');

        $start = microtime(true);
        $response = app()->handle($request);
        $ms = (int) round((microtime(true) - $start) * 1000);

        $body = $response->getContent();
        $payloadKb = (int) round(strlen((string) $body) / 1024);

        $counts = array_count_values(array_column($queries, 'sql'));
        arsort($counts);
        $duplicated = array_sum(array_map(fn ($n) => max(0, $n - 1), $counts));
        $top = key($counts);
        $topCount = current($counts) ?: 0;
        $repeats = [];
        foreach ($counts as $sql => $n) {
            if ($n > 1) $repeats[] = "×{$n}  " . mb_substr((string) $sql, 0, 100);
        }

        return [
            'path' => $path,
            'status' => $response->getStatusCode(),
            'ms' => $ms,
            'queries' => count($queries),
            'duplicated' => $duplicated,
            'slowestMs' => (int) round(max([0, ...array_column($queries, 'time')])),
            'payloadKb' => $payloadKb,
            'topDuplicate' => $topCount > 1 ? mb_substr((string) $top, 0, 90) . " (×{$topCount})" : '—',
            'repeats' => $repeats,
        ];
        });
    }
}
