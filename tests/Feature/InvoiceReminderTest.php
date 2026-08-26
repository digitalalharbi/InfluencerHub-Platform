<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\Notification;
use App\Domain\CRM\Models\Client;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Services\InvoiceReminderService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * متابعة الفواتير المتأخّرة: تذكير مرّة واحدة عند التجاوز، بلا تكرار عند إعادة المسح
 * (idempotency/لا سبام)، ولا إشعار لغير المتأخّر أو المدفوع. مبنيّ على due_date الحقيقيّة.
 */
class InvoiceReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'محاسب', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $u];
    }

    private function invoice(Tenant $t, User $owner, string $status, string $dueDate): Invoice
    {
        return TenantContext::withTenant($t->id, function () use ($t, $owner, $status, $dueDate) {
            $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.Str::random(4), 'display_name' => 'شركة نماء', 'type' => 'company', 'status' => 'active']);

            return Invoice::create([
                'tenant_id' => $t->id, 'invoice_number' => 'INV-'.Str::random(4), 'client_id' => $client->id,
                'status' => $status, 'currency' => 'SAR', 'total_minor' => 100000,
                'issue_date' => now()->subDays(40)->toDateString(), 'due_date' => $dueDate,
                'issued_at' => now()->subDays(40), 'created_by' => $owner->id,
            ]);
        });
    }

    private function overdueNotifs(int $tenantId): int
    {
        return TenantContext::withBypass(fn () => Notification::where('tenant_id', $tenantId)->where('type', 'invoice.overdue')->count());
    }

    public function test_overdue_issued_invoice_notifies_once(): void
    {
        [$t, $owner] = $this->world();
        $inv = $this->invoice($t, $owner, 'issued', now()->subDays(3)->toDateString());

        $r = app(InvoiceReminderService::class)->scan();

        $this->assertSame(1, $r['notified']);
        $this->assertGreaterThan(0, $this->overdueNotifs($t->id));
        $this->assertNotNull($inv->fresh()->overdue_notified_at, 'العلامة تُضبط (منع التكرار)');
    }

    public function test_rescan_is_idempotent_no_duplicate(): void
    {
        [$t, $owner] = $this->world();
        $this->invoice($t, $owner, 'issued', now()->subDays(3)->toDateString());

        app(InvoiceReminderService::class)->scan();
        $after1 = $this->overdueNotifs($t->id);
        $r2 = app(InvoiceReminderService::class)->scan(); // إعادة المسح
        $after2 = $this->overdueNotifs($t->id);

        $this->assertSame(0, $r2['notified'], 'إعادة المسح لا تُنتج تذكيرًا ثانيًا');
        $this->assertSame($after1, $after2, 'لا إشعارات مكرّرة');
    }

    public function test_not_yet_due_invoice_is_not_notified(): void
    {
        [$t, $owner] = $this->world();
        $this->invoice($t, $owner, 'issued', now()->addDays(5)->toDateString());

        $r = app(InvoiceReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->overdueNotifs($t->id));
    }

    public function test_paid_invoice_is_not_notified_even_if_past_due(): void
    {
        [$t, $owner] = $this->world();
        $this->invoice($t, $owner, 'paid', now()->subDays(10)->toDateString());

        $r = app(InvoiceReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->overdueNotifs($t->id));
    }
}
