<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\{Cache, DB, Redis};
use Throwable;

/**
 * فحص جاهزية الخدمات — يقول ما هو قائم فعلًا لا ما هو مُعدّ في الملفّات.
 *
 * السبب: «مُعدّ» و«يعمل» ليسا شيئًا واحدًا. متغيّر بيئة يشير إلى Redis لا يعني
 * أن Redis يستجيب. كل فحص هنا يُجري عملية حقيقية (كتابة/قراءة/ping) ويُبلّغ
 * بالنتيجة، فلا تُقرأ الجاهزية من ملفّ إعداد.
 */
class HealthCheck
{
    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return [
            'database' => self::database(),
            'cache' => self::cache(),
            'queue' => self::queue(),
            'session' => self::session(),
            'redis' => self::redis(),
        ];
    }

    /** هل كل ما هو إلزامي سليم؟ Redis اختياري ما دام السائق ليس عليه. */
    public static function isHealthy(): bool
    {
        foreach (self::all() as $name => $check) {
            if ($check['required'] && $check['status'] !== 'ok') {
                return false;
            }
        }

        return true;
    }

    private static function database(): array
    {
        try {
            DB::select('select 1');

            return self::ok('قاعدة البيانات', config('database.default'), true);
        } catch (Throwable $e) {
            return self::fail('قاعدة البيانات', config('database.default'), true, $e);
        }
    }

    private static function cache(): array
    {
        $driver = config('cache.default');
        try {
            $key = 'health:' . bin2hex(random_bytes(4));
            Cache::put($key, 'ok', 10);
            $read = Cache::get($key);
            Cache::forget($key);

            return $read === 'ok'
                ? self::ok('الذاكرة المؤقّتة', $driver, true)
                : self::fail('الذاكرة المؤقّتة', $driver, true, new \RuntimeException('كُتبت القيمة ولم تُقرأ'));
        } catch (Throwable $e) {
            return self::fail('الذاكرة المؤقّتة', $driver, true, $e);
        }
    }

    private static function queue(): array
    {
        $driver = config('queue.default');
        try {
            // على سائق قاعدة البيانات نتحقّق من وجود الجدول لا من تنفيذ مهمّة
            if ($driver === 'database') {
                $pending = DB::table('jobs')->count();
                $failed = DB::table('failed_jobs')->count();

                return self::ok('الطابور', $driver, true, ['pending' => $pending, 'failed' => $failed]);
            }

            return self::ok('الطابور', $driver, true);
        } catch (Throwable $e) {
            return self::fail('الطابور', $driver, true, $e);
        }
    }

    private static function session(): array
    {
        $driver = config('session.driver');
        try {
            if ($driver === 'database') {
                DB::table(config('session.table', 'sessions'))->limit(1)->count();
            }

            return self::ok('الجلسات', $driver, true);
        } catch (Throwable $e) {
            return self::fail('الجلسات', $driver, true, $e);
        }
    }

    /**
     * Redis غير إلزامي ما لم يعتمد عليه سائق فعلي.
     * إن اعتمد عليه أحد السوائق صار فشله فشلًا للنظام لا ملاحظة جانبية.
     */
    private static function redis(): array
    {
        $usedBy = array_keys(array_filter([
            'cache' => config('cache.default') === 'redis',
            'queue' => config('queue.default') === 'redis',
            'session' => config('session.driver') === 'redis',
        ]));
        $required = $usedBy !== [];

        if (! $required) {
            return [
                'label' => 'Redis',
                'driver' => config('database.redis.client'),
                'status' => 'not_in_use',
                'required' => false,
                'detail' => 'لا سائق يعتمد عليه حاليًّا — الجلسات والطابور والذاكرة على قاعدة البيانات.',
            ];
        }

        try {
            Redis::connection()->ping();

            return self::ok('Redis', config('database.redis.client'), true, ['usedBy' => $usedBy]);
        } catch (Throwable $e) {
            return self::fail('Redis', config('database.redis.client'), true, $e, ['usedBy' => $usedBy]);
        }
    }

    private static function ok(string $label, ?string $driver, bool $required, array $extra = []): array
    {
        return ['label' => $label, 'driver' => $driver, 'status' => 'ok', 'required' => $required, 'detail' => null] + $extra;
    }

    private static function fail(string $label, ?string $driver, bool $required, Throwable $e, array $extra = []): array
    {
        return ['label' => $label, 'driver' => $driver, 'status' => 'failing', 'required' => $required,
            'detail' => $e->getMessage()] + $extra;
    }
}
