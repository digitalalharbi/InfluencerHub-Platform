<?php

namespace Tests\Feature;

use App\Console\Commands\RunScheduledReportsCommand;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Exports\ExportService;
use App\Domain\Exports\Models\{ExportJob, ScheduledReport};
use App\Domain\Exports\ReportGenerator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * التقارير المجدولة + سجلّ التصدير: الجدولة تُحسِب الموعد التالي، المشغّل يولّد
 * ملفًّا على قرص خاص ويُشعر المالك، والتنزيل مقصور على صاحبه (لا IDOR، لا رابط عام).
 */
class ScheduledReportExportJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            return [$t, $u];
        });
    }

    public function test_scheduling_a_report_sets_next_run(): void
    {
        [$t, $u] = $this->agency();

        $res = $this->actingAs($u)->post('/app/exports/schedules', [
            'name' => 'أداء العملاء الأسبوعي', 'report_type' => 'clients_report',
            'format' => 'xlsx', 'frequency' => 'weekly', 'delivery' => 'in_app',
        ]);
        $res->assertRedirect();

        $report = TenantContext::withBypass(fn () => ScheduledReport::first());
        $this->assertNotNull($report);
        $this->assertSame($u->id, $report->user_id);
        $this->assertNotNull($report->next_run_at, 'الموعد التالي يجب أن يُحسب عند الإنشاء');
        $this->assertTrue($report->enabled);
    }

    public function test_runner_generates_file_on_private_disk_and_notifies_owner(): void
    {
        Storage::fake('local');
        [$t, $u] = $this->agency();
        $report = TenantContext::withBypass(fn () => ScheduledReport::create([
            'tenant_id' => $t->id, 'user_id' => $u->id, 'report_type' => 'clients_report',
            'name' => 'تقرير', 'format' => 'csv', 'frequency' => 'daily', 'enabled' => true,
            'next_run_at' => now()->subHour(),
        ]));

        $this->artisan('reports:run-scheduled')->assertExitCode(0);

        $job = TenantContext::withBypass(fn () => ExportJob::first());
        $this->assertNotNull($job, 'وظيفة تصدير مكتملة يجب أن تُسجَّل');
        $this->assertSame('completed', $job->status);
        $this->assertSame($report->id, $job->scheduled_report_id);
        $this->assertTrue($job->isDownloadable());
        Storage::disk('local')->assertExists($job->path);

        // أُشعِر المالك برابط تنزيل آمن
        $this->assertDatabaseHas('notifications', ['user_id' => $u->id, 'type' => 'report.ready']);

        // حُسِب الموعد التالي بعد التشغيل
        $report->refresh();
        $this->assertNotNull($report->last_run_at);
        $this->assertTrue($report->next_run_at->isFuture());
    }

    public function test_owner_can_download_but_other_users_cannot(): void
    {
        Storage::fake('local');
        [$t, $u] = $this->agency();
        [$t2, $stranger] = $this->agency();

        $job = TenantContext::withBypass(fn () => ExportJob::create([
            'tenant_id' => $t->id, 'user_id' => $u->id, 'type' => 'clients_report', 'title' => 'تقرير',
            'format' => 'csv', 'status' => 'completed', 'disk' => 'local',
            'path' => 'exports/x/file.csv', 'size_bytes' => 3, 'completed_at' => now(),
            'expires_at' => now()->addDays(14),
        ]));
        Storage::disk('local')->put('exports/x/file.csv', 'a,b');

        $this->actingAs($u)->get("/app/exports/{$job->id}/download")->assertOk();
        // مستخدم آخر — فحص الملكية يمنعه (403)؛ لا وصول لملفّ غيره
        $this->actingAs($stranger)->get("/app/exports/{$job->id}/download")->assertForbidden();
    }

    public function test_expired_job_is_not_downloadable(): void
    {
        Storage::fake('local');
        [$t, $u] = $this->agency();
        $job = TenantContext::withBypass(fn () => ExportJob::create([
            'tenant_id' => $t->id, 'user_id' => $u->id, 'type' => 'clients_report', 'title' => 'قديم',
            'format' => 'csv', 'status' => 'completed', 'disk' => 'local',
            'path' => 'exports/x/old.csv', 'size_bytes' => 3, 'completed_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]));
        Storage::disk('local')->put('exports/x/old.csv', 'a,b');

        $this->actingAs($u)->get("/app/exports/{$job->id}/download")->assertStatus(410);
    }
}
