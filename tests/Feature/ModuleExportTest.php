<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تصدير الوحدات — الملفّ يطابق القائمة المُرشَّحة تمامًا (نفس الفلاتر)، وحقول
 * التواصل للمبدعين محجوبة لمن لا يملك الصلاحية. صيغ CSV/XLSX حقيقية.
 */
class ModuleExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agency(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        return TenantContext::withBypass(function () use ($t, $role) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
            return [$t, $u];
        });
    }

    private function bodyOf($response): string
    {
        ob_start();
        $response->sendContent();
        return ob_get_clean();
    }

    public function test_clients_csv_matches_active_filter(): void
    {
        [$t, $u] = $this->agency();
        TenantContext::withBypass(function () use ($t) {
            Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-A', 'display_name' => 'عميل نشط', 'type' => 'company', 'status' => 'active']);
            Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-L', 'display_name' => 'عميل مهتم', 'type' => 'company', 'status' => 'lead']);
        });

        $res = $this->actingAs($u)->get('/app/clients/export?format=csv&status=active');
        $res->assertOk();
        $csv = $this->bodyOf($res->baseResponse);

        // يحتوي النشط فقط — يطابق نفس فلتر القائمة
        $this->assertStringContainsString('عميل نشط', $csv);
        $this->assertStringNotContainsString('عميل مهتم', $csv);
        $this->assertStringContainsString('CL-A', $csv);
    }

    public function test_clients_xlsx_is_real_zip(): void
    {
        [$t, $u] = $this->agency();
        TenantContext::withBypass(fn () => Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-1', 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']));
        $res = $this->actingAs($u)->get('/app/clients/export?format=xlsx');
        $res->assertOk();
        $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $this->bodyOf($res->baseResponse));
    }

    public function test_creators_export_hides_contact_without_permission(): void
    {
        // viewer: يملك العرض لا الكتابة → لا حقول تواصل
        [$t, $viewer] = $this->agency('viewer');
        TenantContext::withBypass(fn () => Creator::create([
            'tenant_id' => $t->id, 'creator_number' => 'CR-1', 'type' => 'influencer', 'display_name' => 'مبدع',
            'status' => 'active', 'email' => 'secret@ex.com', 'phone' => '0501112222',
        ]));

        $res = $this->actingAs($viewer)->get('/app/creators/export?format=csv');
        $res->assertOk();
        $csv = $this->bodyOf($res->baseResponse);
        $this->assertStringContainsString('مبدع', $csv);
        $this->assertStringNotContainsString('secret@ex.com', $csv, 'التواصل محجوب بلا صلاحية');
        $this->assertStringNotContainsString('0501112222', $csv);
    }

    public function test_creators_export_includes_contact_with_permission(): void
    {
        [$t, $admin] = $this->agency('agency_admin'); // يملك create → تواصل
        TenantContext::withBypass(fn () => Creator::create([
            'tenant_id' => $t->id, 'creator_number' => 'CR-1', 'type' => 'influencer', 'display_name' => 'مبدع',
            'status' => 'active', 'email' => 'ok@ex.com', 'phone' => '0501112222',
        ]));
        $res = $this->actingAs($admin)->get('/app/creators/export?format=csv');
        $res->assertOk();
        $this->assertStringContainsString('ok@ex.com', $this->bodyOf($res->baseResponse));
    }

    public function test_export_is_audit_logged(): void
    {
        [$t, $u] = $this->agency();
        $this->actingAs($u)->get('/app/clients/export?format=csv')->assertOk();
        TenantContext::bypass(true);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $t->id, 'action' => 'export.generated']);
        TenantContext::reset();
    }

    public function test_export_guest_redirected(): void
    {
        $this->get('/app/clients/export')->assertRedirect('/login');
    }
}
