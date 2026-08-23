<?php

namespace Tests\Feature;

use App\Domain\Integrations\Jobs\SyncProviderJob;
use App\Domain\Integrations\Models\{IntegrationConnection, IntegrationSyncRun};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * مركز التحكّم بالتكاملات — دمج السجلّ الثابت بحالة الاتّصال الفعلية، «زامن الآن»
 * يُدرِج وظيفة بالطابور (لا تزامن في الطلب)، ويمنع التكرار، وصفحة التفاصيل تعرض السجلّ.
 */
class IntegrationControlCenterTest extends TestCase
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

    private function connect(int $tenantId, string $status = 'connected'): IntegrationConnection
    {
        return TenantContext::withBypass(fn () => IntegrationConnection::create([
            'tenant_id' => $tenantId, 'provider' => 'tiktok', 'environment' => 'production',
            'status' => $status, 'health' => 'healthy', 'external_account_name' => '@brand', 'access_token' => 'x',
        ]));
    }

    public function test_index_shows_real_connection_state(): void
    {
        [$t, $u] = $this->agency();
        $this->connect($t->id);
        $this->actingAs($u)->get('/app/integrations')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Integrations/Index')
                ->where('platforms', fn ($rows) => collect($rows)->contains(fn ($x) => $x['key'] === 'tiktok' && $x['connection'] !== null && $x['connection']['connected'] === true)));
    }

    public function test_sync_now_enqueues_job_and_prevents_duplicates(): void
    {
        Bus::fake();
        [$t, $u] = $this->agency();
        $this->connect($t->id);

        $this->actingAs($u)->post('/app/integrations/tiktok/sync')->assertRedirect()->assertSessionHas('ok');
        Bus::assertDispatched(SyncProviderJob::class);

        // مزامنة جارية → لا تُدرَج ثانية
        TenantContext::withBypass(fn () => IntegrationSyncRun::create([
            'tenant_id' => $t->id, 'connection_id' => IntegrationConnection::where('tenant_id', $t->id)->first()->id,
            'provider' => 'tiktok', 'type' => 'manual', 'status' => 'running', 'started_at' => now(),
        ]));
        Bus::fake();
        $this->actingAs($u)->post('/app/integrations/tiktok/sync')->assertRedirect();
        Bus::assertNotDispatched(SyncProviderJob::class);
    }

    public function test_sync_now_rejected_when_not_connected(): void
    {
        [$t, $u] = $this->agency();
        $this->connect($t->id, 'disconnected');
        $this->actingAs($u)->post('/app/integrations/tiktok/sync')->assertRedirect()->assertSessionHasErrors('sync');
    }

    public function test_detail_shows_sync_history(): void
    {
        [$t, $u] = $this->agency();
        $conn = $this->connect($t->id);
        TenantContext::withBypass(fn () => IntegrationSyncRun::create([
            'tenant_id' => $t->id, 'connection_id' => $conn->id, 'provider' => 'tiktok', 'type' => 'manual',
            'status' => 'success', 'fetched' => 12, 'created' => 5, 'started_at' => now()->subMinute(), 'completed_at' => now(),
        ]));

        $this->actingAs($u)->get('/app/integrations/tiktok')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Integrations/Show')->has('runs', 1)->where('runs.0.fetched', 12));
    }

    public function test_sync_now_requires_admin(): void
    {
        [$t, $viewer] = $this->agency('viewer');
        $this->connect($t->id);
        $this->actingAs($viewer)->post('/app/integrations/tiktok/sync')->assertForbidden();
    }
}
