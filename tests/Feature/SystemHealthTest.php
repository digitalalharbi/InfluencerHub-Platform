<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Ops\Services\SystemHealthService;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * مركز صحّة النظام — فحوص حقيقية (قاعدة/طابور/مجدول/بريد/واتساب/تخزين/تكاملات/
 * ويبهوك)، حالة المجدول من نبضة فعلية، وبوابة إدارية.
 */
class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t, $role) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
            return [$t, $u];
        });
    }

    public function test_checks_are_real_and_include_core_systems(): void
    {
        $checks = collect(app(SystemHealthService::class)->checks())->keyBy('key');
        foreach (['app', 'database', 'queue', 'scheduler', 'mail', 'whatsapp', 'storage', 'integrations', 'webhooks', 'failed_jobs'] as $k) {
            $this->assertArrayHasKey($k, $checks, "فحص {$k} موجود");
        }
        $this->assertSame('ok', $checks['database']['status'], 'قاعدة البيانات تستجيب');
        $this->assertContains($checks['storage']['status'], ['ok', 'down']);
    }

    public function test_scheduler_down_without_heartbeat_then_ok_with_fresh_beat(): void
    {
        Cache::forget(SystemHealthService::HEARTBEAT_KEY);
        $sched = collect(app(SystemHealthService::class)->checks())->firstWhere('key', 'scheduler');
        $this->assertSame('down', $sched['status'], 'بلا نبضة → متوقّف');

        $this->artisan('ops:scheduler-heartbeat')->assertSuccessful();
        $sched2 = collect(app(SystemHealthService::class)->checks())->firstWhere('key', 'scheduler');
        $this->assertSame('ok', $sched2['status'], 'مع نبضة حديثة → سليم');
    }

    /**
     * انحدار: نبضة المجدول يجب ألّا تحمل withoutOverlapping — قفله (24س افتراضًا)
     * يعلَق إذا قُتِلت العملية أثناء النشر فيتخطّى كلّ نبضة لاحقة يومًا كاملًا،
     * فيظهر «المجدول متوقّف» في الإنتاج رغم عمل الحاوية.
     */
    public function test_heartbeat_schedule_has_no_overlap_mutex(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $ref = new \ReflectionProperty(\Illuminate\Console\Scheduling\Event::class, 'withoutOverlapping');
        $ref->setAccessible(true);

        $beat = collect($schedule->events())->first(fn ($e) => str_contains((string) $e->command, 'ops:scheduler-heartbeat'));
        $this->assertNotNull($beat, 'نبضة المجدول مُسجّلة في الجدولة');
        $this->assertFalse($ref->getValue($beat), 'النبضة بلا قفل تداخل (يتجنّب القفل العالق)');

        // الوظائف الساعيّة تبقى بلا تداخل لكن بصلاحية قصيرة تتعافى ذاتيًّا
        $hourly = collect($schedule->events())->first(fn ($e) => str_contains((string) $e->command, 'reports:run-scheduled'));
        $this->assertNotNull($hourly);
        $this->assertTrue($ref->getValue($hourly));
        $this->assertLessThanOrEqual(55, $hourly->expiresAt, 'قفل الوظيفة الساعيّة قصير الصلاحية');
    }

    public function test_mail_and_whatsapp_honest_when_unconfigured(): void
    {
        config(['channels.email.enabled' => false, 'channels.whatsapp.enabled' => false]);
        $checks = collect(app(SystemHealthService::class)->checks())->keyBy('key');
        $this->assertSame('not_configured', $checks['mail']['status']);
        $this->assertSame('not_configured', $checks['whatsapp']['status']);
    }

    public function test_page_renders_for_admin(): void
    {
        [, $u] = $this->agency('agency_admin');
        $this->artisan('ops:scheduler-heartbeat');
        $this->actingAs($u)->get('/app/system-health')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Ops/SystemHealth')->has('checks')->has('overall'));
    }

    public function test_page_forbidden_for_non_admin(): void
    {
        [, $viewer] = $this->agency('viewer');
        $this->actingAs($viewer)->get('/app/system-health')->assertForbidden();
    }
}
