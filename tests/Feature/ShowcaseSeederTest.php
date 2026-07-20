<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\Payout;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Showcase\ShowcaseBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * بيئة العرض التجريبية: بناء مترابط + Idempotency + رفض الإنتاج + تكامل مالي + عزل المستأجر.
 */
class ShowcaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function showcaseTenant(): Tenant
    {
        TenantContext::bypass(true);
        $t = Tenant::withoutGlobalScopes()->where('slug', ShowcaseBuilder::TENANT_SLUG)->firstOrFail();
        TenantContext::reset();
        return $t;
    }

    private function tenantCount(string $model, int $tid): int
    {
        TenantContext::bypass(true);
        $n = $model::withoutGlobalScopes()->where('tenant_id', $tid)->count();
        TenantContext::reset();
        return $n;
    }

    public function test_production_is_rejected_by_command_and_seeder(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->artisan('preview:seed-showcase')->assertExitCode(1);
        TenantContext::bypass(true);
        $this->assertDatabaseMissing('tenants', ['slug' => ShowcaseBuilder::TENANT_SLUG]);
        TenantContext::reset();
    }

    public function test_builds_interconnected_idempotent_showcase(): void
    {
        (new ShowcaseBuilder())->build();
        $t = $this->showcaseTenant();
        $tid = $t->id;

        // الحد الأدنى للأحجام (حسب التوجيه)
        $this->assertSame(15, $this->tenantCount(Client::class, $tid));
        $this->assertSame(160, $this->tenantCount(Creator::class, $tid));
        $this->assertGreaterThanOrEqual(24, $this->tenantCount(Campaign::class, $tid));
        $this->assertGreaterThanOrEqual(80, $this->tenantCount(Collaboration::class, $tid));
        $this->assertGreaterThanOrEqual(100, $this->tenantCount(ContentItem::class, $tid));
        $this->assertGreaterThanOrEqual(50, $this->tenantCount(Payout::class, $tid));
        $this->assertGreaterThanOrEqual(25, $this->tenantCount(Brand::class, $tid));

        // 120 مؤثر + 40 UGC
        TenantContext::bypass(true);
        $this->assertSame(120, Creator::withoutGlobalScopes()->where('tenant_id', $tid)->where('type', 'influencer')->count());
        $this->assertSame(40, Creator::withoutGlobalScopes()->where('tenant_id', $tid)->where('type', 'ugc_creator')->count());

        // ترابط: لا سجلات يتيمة
        $this->assertSame(0, Campaign::withoutGlobalScopes()->where('tenant_id', $tid)->whereNull('client_id')->count());
        $this->assertSame(0, Collaboration::withoutGlobalScopes()->where('tenant_id', $tid)->whereNull('creator_id')->count());
        $this->assertSame(0, Collaboration::withoutGlobalScopes()->where('tenant_id', $tid)->whereNull('campaign_id')->count());
        $this->assertSame(0, Payout::withoutGlobalScopes()->where('tenant_id', $tid)->whereNull('creator_id')->count());

        // تكامل مالي: الإيراد > التكلفة والربح موجب
        $revenue = (int) Campaign::withoutGlobalScopes()->where('tenant_id', $tid)->sum('budget_minor');
        $cost = (int) Collaboration::withoutGlobalScopes()->where('tenant_id', $tid)->sum('fee_minor');
        TenantContext::reset();
        $this->assertGreaterThan(0, $revenue);
        $this->assertGreaterThan($cost, $revenue, 'يجب أن يتجاوز الإيراد التكلفة (هامش موجب)');

        // Idempotency: إعادة البناء لا تكرّر ولا تغيّر العدّ
        $clientsBefore = $this->tenantCount(Client::class, $tid);
        (new ShowcaseBuilder())->build();
        $t2 = $this->showcaseTenant();
        $this->assertSame(15, $this->tenantCount(Client::class, $t2->id), 'إعادة البناء يجب ألا تكرّر العملاء');
        $this->assertSame(160, $this->tenantCount(Creator::class, $t2->id));
        $this->assertSame(15, $clientsBefore);
    }

    public function test_tenant_isolation(): void
    {
        // مستأجر آخر لا يتأثر ببناء العرض
        TenantContext::bypass(true);
        $other = Tenant::create(['name' => 'آخر', 'slug' => 'other-x', 'deployment_mode' => 'saas', 'status' => 'active']);
        $org = \App\Domain\Tenancy\Models\Organization::create(['tenant_id' => $other->id, 'name' => 'o', 'slug' => 'other-org', 'type' => 'agency']);
        Client::create(['tenant_id' => $other->id, 'client_number' => 'CL-' . $other->id . '-0001', 'display_name' => 'عميل آخر', 'status' => 'active']);
        TenantContext::reset();

        (new ShowcaseBuilder())->build();
        $showcase = $this->showcaseTenant();

        // عميل المستأجر الآخر ما زال واحدًا فقط، وبيانات العرض معزولة في مستأجرها
        $this->assertSame(1, $this->tenantCount(Client::class, $other->id));
        $this->assertSame(15, $this->tenantCount(Client::class, $showcase->id));
        $this->assertNotSame($other->id, $showcase->id);
    }
}
