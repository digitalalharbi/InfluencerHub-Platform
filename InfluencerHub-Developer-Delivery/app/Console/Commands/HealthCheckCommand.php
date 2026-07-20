<?php

namespace App\Console\Commands;

use App\Support\Health\HealthCheck;
use Illuminate\Console\Command;

/**
 * فحص الجاهزية من سطر الأوامر — يُستعمل في النشر قبل توجيه الحركة للخادم.
 * يخرج برمز فشل إن سقط فحص إلزامي، فيصلح للاستخدام في CI أو readiness probe.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'health:check {--json}';

    protected $description = 'فحص جاهزية قاعدة البيانات والذاكرة والطابور والجلسات وRedis';

    public function handle(): int
    {
        $checks = HealthCheck::all();

        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['الخدمة', 'السائق', 'الحالة', 'إلزامي', 'ملاحظة'], array_map(fn ($c) => [
                $c['label'], $c['driver'] ?? '—', $c['status'],
                $c['required'] ? 'نعم' : 'لا', $c['detail'] ?? '—',
            ], $checks));
        }

        if (! HealthCheck::isHealthy()) {
            $this->error('فحص إلزامي فاشل.');

            return self::FAILURE;
        }

        $this->info('كل الفحوص الإلزامية سليمة.');

        return self::SUCCESS;
    }
}
