<?php

namespace Tests\Feature;

use App\Support\Health\HealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * جاهزية الخدمات تُقاس بعملية حقيقية لا بقراءة ملفّ إعداد.
 *
 * السبب: متغيّر بيئة يشير إلى Redis لا يعني أن Redis يستجيب. وقد نُشِر نظام
 * وسائقه معطّل لأن أحدًا لم يفرّق بين «مُعدّ» و«يعمل».
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_services_are_healthy(): void
    {
        $checks = HealthCheck::all();

        foreach (['database', 'cache', 'queue', 'session'] as $key) {
            $this->assertSame('ok', $checks[$key]['status'], "الخدمة {$key} غير سليمة: " . ($checks[$key]['detail'] ?? ''));
            $this->assertTrue($checks[$key]['required']);
        }

        $this->assertTrue(HealthCheck::isHealthy());
    }

    /** ما دام لا سائق يعتمد على Redis فغيابه ليس عطلًا. */
    public function test_redis_is_optional_while_no_driver_uses_it(): void
    {
        config(['cache.default' => 'database', 'queue.default' => 'database', 'session.driver' => 'database']);

        $redis = HealthCheck::all()['redis'];
        $this->assertFalse($redis['required']);
        $this->assertSame('not_in_use', $redis['status']);
        $this->assertTrue(HealthCheck::isHealthy(), 'اعتُبر غياب Redis عطلًا وهو غير مستخدَم');
    }

    /**
     * التحويل إلى Redis بمتغيّرات البيئة وحدها: لا تغيير في الكود.
     * وإن اعتُمد عليه صار فشله فشلًا إلزاميًّا لا ملاحظة.
     */
    public function test_switching_a_driver_to_redis_makes_it_required(): void
    {
        config(['queue.default' => 'redis']);

        $redis = HealthCheck::all()['redis'];
        $this->assertTrue($redis['required'], 'اعتمد الطابور على Redis ولم يُعدّ إلزاميًّا');
        $this->assertContains('queue', $redis['usedBy']);
    }

    public function test_health_command_exits_successfully(): void
    {
        $this->artisan('health:check')->assertSuccessful();
    }
}
