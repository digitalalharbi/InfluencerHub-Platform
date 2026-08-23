<?php

namespace App\Domain\Ops\Services;

use App\Domain\Integrations\Models\{IntegrationConnection, IntegrationWebhookEvent};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\{Cache, DB, Storage};

/**
 * فحوص صحّة تشغيلية حقيقية — لا نقاط خضراء زخرفية. كل حالة من فحص فعلي:
 * قاعدة/طابور/مجدول/بريد/واتساب/تخزين/تكاملات/ويبهوك/وظائف فاشلة.
 * لا تكشف أسرارًا. الحالات: ok|warn|down|unknown|not_configured.
 */
class SystemHealthService
{
    public const HEARTBEAT_KEY = 'ops:scheduler:heartbeat';

    /** @return array<int,array<string,mixed>> */
    public function checks(?int $tenantId = null): array
    {
        return [
            $this->app(),
            $this->database(),
            $this->queue(),
            $this->scheduler(),
            $this->failedJobs(),
            $this->mail(),
            $this->whatsapp(),
            $this->storage(),
            $this->integrations($tenantId),
            $this->webhooks(),
        ];
    }

    private function check(string $key, string $label, string $status, string $detail, array $metrics = []): array
    {
        return compact('key', 'label', 'status', 'detail', 'metrics');
    }

    private function app(): array
    {
        return $this->check('app', 'التطبيق', 'ok', 'يعمل', ['version' => config('app.env')]);
    }

    private function database(): array
    {
        try {
            $start = microtime(true);
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $start) * 1000);

            return $this->check('database', 'قاعدة البيانات', $ms < 500 ? 'ok' : 'warn', "استجابت خلال {$ms}ms", ['latency_ms' => $ms]);
        } catch (\Throwable $e) {
            return $this->check('database', 'قاعدة البيانات', 'down', 'تعذّر الاتصال');
        }
    }

    private function queue(): array
    {
        try {
            $pending = DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('created_at');
            $oldestMin = $oldest ? (int) round((now()->timestamp - (int) $oldest) / 60) : null;
            $status = ($oldestMin !== null && $oldestMin > 15) ? 'warn' : 'ok';

            return $this->check('queue', 'الطابور', $status,
                $pending === 0 ? 'لا وظائف معلّقة' : "{$pending} معلّقة" . ($oldestMin ? " (أقدمها {$oldestMin} د)" : ''),
                ['pending' => $pending, 'oldest_minutes' => $oldestMin]);
        } catch (\Throwable $e) {
            return $this->check('queue', 'الطابور', 'unknown', 'تعذّر القياس');
        }
    }

    private function failedJobs(): array
    {
        try {
            $failed = DB::table('failed_jobs')->count();
            $recent = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

            return $this->check('failed_jobs', 'الوظائف الفاشلة', $recent > 0 ? 'warn' : ($failed > 0 ? 'warn' : 'ok'),
                $failed === 0 ? 'لا فشل' : "{$failed} فاشلة ({$recent} خلال 24س)", ['total' => $failed, 'recent' => $recent]);
        } catch (\Throwable $e) {
            return $this->check('failed_jobs', 'الوظائف الفاشلة', 'unknown', 'تعذّر القياس');
        }
    }

    private function scheduler(): array
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);
        if (! $beat) {
            return $this->check('scheduler', 'المجدول', 'down', 'لا نبضة — تحقّق من تشغيل schedule:run', []);
        }
        $ageMin = (int) round((now()->timestamp - (int) $beat) / 60);

        return $this->check('scheduler', 'المجدول', $ageMin <= 5 ? 'ok' : 'down',
            $ageMin <= 5 ? "آخر نبضة قبل {$ageMin} د" : "النبضة قديمة ({$ageMin} د) — قد يكون المجدول متوقفًا",
            ['last_heartbeat_minutes' => $ageMin]);
    }

    private function mail(): array
    {
        $enabled = (bool) config('channels.email.enabled');
        $mailer = (string) config('mail.default');
        if (! $enabled) {
            return $this->check('mail', 'البريد', 'not_configured', 'قناة البريد غير مُفعّلة (CHANNEL_EMAIL_ENABLED)', ['provider' => $mailer]);
        }
        $status = in_array($mailer, ['log', 'array'], true) ? 'warn' : 'ok';

        return $this->check('mail', 'البريد', $status,
            $status === 'ok' ? "مُفعّل عبر {$mailer}" : "مُفعّل لكن المزوّد {$mailer} لا يُسلّم فعليًّا", ['provider' => $mailer]);
    }

    private function whatsapp(): array
    {
        $enabled = (bool) config('channels.whatsapp.enabled');
        $creds = filled(config('channels.whatsapp.phone_number_id')) && filled(config('channels.whatsapp.access_token'));
        $webhook = filled(config('channels.whatsapp.verify_token')) && filled(config('channels.whatsapp.app_secret'));
        if (! $enabled || ! $creds) {
            return $this->check('whatsapp', 'واتساب', 'not_configured', 'يتطلّب بيانات اعتماد Meta Cloud API', ['webhook_ready' => $webhook]);
        }

        return $this->check('whatsapp', 'واتساب', $webhook ? 'ok' : 'warn',
            $webhook ? 'مُهيّأ (إرسال + ويبهوك)' : 'مُهيّأ للإرسال؛ الويبهوك ناقص', ['webhook_ready' => $webhook]);
    }

    private function storage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default'));
            $probe = 'ops/health-' . now()->timestamp . '.txt';
            $disk->put($probe, 'ok');
            $ok = $disk->exists($probe);
            $disk->delete($probe);

            return $this->check('storage', 'التخزين', $ok ? 'ok' : 'down', $ok ? 'قابل للكتابة' : 'غير قابل للكتابة');
        } catch (\Throwable $e) {
            return $this->check('storage', 'التخزين', 'down', 'تعذّر الوصول');
        }
    }

    private function integrations(?int $tenantId): array
    {
        try {
            $q = IntegrationConnection::query();
            if ($tenantId) {
                $q = TenantContext::withBypass(fn () => IntegrationConnection::where('tenant_id', $tenantId));
            } else {
                $q = TenantContext::withBypass(fn () => IntegrationConnection::query());
            }
            $rows = $q->get(['status', 'health']);
            $total = $rows->count();
            $errors = $rows->where('health', 'error')->count();
            $connected = $rows->whereIn('status', IntegrationConnection::CONNECTED_STATUSES)->count();
            $status = $total === 0 ? 'not_configured' : ($errors > 0 ? 'warn' : 'ok');

            return $this->check('integrations', 'التكاملات', $status,
                $total === 0 ? 'لا اتّصالات مُهيّأة بعد' : "{$connected}/{$total} موصولة" . ($errors ? " · {$errors} بأخطاء" : ''),
                ['total' => $total, 'connected' => $connected, 'errors' => $errors]);
        } catch (\Throwable $e) {
            return $this->check('integrations', 'التكاملات', 'unknown', 'تعذّر القياس');
        }
    }

    private function webhooks(): array
    {
        try {
            $recent = TenantContext::withBypass(fn () => IntegrationWebhookEvent::where('received_at', '>=', now()->subDay())->count());
            $failed = TenantContext::withBypass(fn () => IntegrationWebhookEvent::where('status', 'failed')->where('received_at', '>=', now()->subDay())->count());

            return $this->check('webhooks', 'الويبهوك', $failed > 0 ? 'warn' : 'ok',
                $recent === 0 ? 'لا أحداث خلال 24س' : "{$recent} حدثًا خلال 24س" . ($failed ? " · {$failed} فشل" : ''),
                ['recent' => $recent, 'failed' => $failed]);
        } catch (\Throwable $e) {
            return $this->check('webhooks', 'الويبهوك', 'unknown', 'تعذّر القياس');
        }
    }
}
