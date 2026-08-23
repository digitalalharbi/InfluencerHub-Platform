<?php

namespace App\Domain\Automation;

use App\Domain\Automation\Engine\AutomationEngine;
use Illuminate\Support\Facades\Log;

/**
 * نقطة إطلاق الأتمتة من سير العمل. تضمن تثبيت القواعد الافتراضية للمستأجر (مرة)،
 * ثم تُطلق المحفّز بأمان — فشل الأتمتة لا يُفشِل العملية الأصلية أبدًا.
 * تُستدعى من الخدمات/المتحكّمات حيث تتغيّر الحالة فعلًا — لا من React.
 */
class Automation
{
    /** @var array<int,bool> تثبيت الافتراضيات مرة لكل مستأجر في هذا الطلب */
    private static array $ensured = [];

    public static function fire(string $trigger, array $context, int $tenantId, ?string $eventKey = null): void
    {
        try {
            if (! isset(self::$ensured[$tenantId])) {
                app(DefaultAutomationRules::class)->ensure($tenantId);
                self::$ensured[$tenantId] = true;
            }
            app(AutomationEngine::class)->fire($trigger, $context, $tenantId, $eventKey);
        } catch (\Throwable $e) {
            // الأتمتة طبقة مساعدة — لا تُفشِل التحوّل الأساسي أبدًا.
            Log::warning('automation fire failed', ['trigger' => $trigger, 'error' => $e->getMessage()]);
        }
    }

    /** للاختبارات — يُصفّر ذاكرة التثبيت. */
    public static function flushEnsured(): void
    {
        self::$ensured = [];
    }
}
