<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Exceptions\{EntitlementLimitExceeded, InvalidSubscriptionTransition};
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement, AddOn, OrganizationAddOn, Subscription};
use App\Domain\Billing\Providers\FakeBillingProvider;
use App\Domain\Billing\Services\{EntitlementService, UsageMeterService, SubscriptionService};
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 2 — SaaS Billing: خطط/نسخ/اشتراكات/entitlements/usage (PostgreSQL). */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); config(['influencerhub.deployment_mode' => 'saas']); parent::tearDown(); }

    private function org(): Organization
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $o = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        TenantContext::reset();
        return $o;
    }

    /** خطة + نسخة + entitlements. */
    private function planVersion(array $entitlements): PlanVersion
    {
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'Plan', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        foreach ($entitlements as $key => $spec) {
            PlanEntitlement::create([
                'plan_version_id' => $v->id, 'feature_key' => $key,
                'value' => $spec['value'] ?? null, 'is_unlimited' => $spec['unlimited'] ?? false,
            ]);
        }
        return $v;
    }

    private function subscribe(Organization $org, PlanVersion $v, array $attrs = []): Subscription
    {
        return (new CreateSubscription)->handle($org, $v, $attrs);
    }

    // ---------- Plans & versioning ----------
    public function test_creating_plan_version_and_entitlements(): void
    {
        $v = $this->planVersion(['users.max' => ['value' => 5]]);
        $this->assertDatabaseHas('plan_entitlements', ['plan_version_id' => $v->id, 'feature_key' => 'users.max', 'value' => 5]);
    }

    public function test_cannot_edit_locked_historical_version(): void
    {
        $org = $this->org();
        $v = $this->planVersion(['users.max' => ['value' => 5]]);
        $this->subscribe($org, $v); // يقفل النسخة
        $this->assertTrue($v->fresh()->is_locked);
        $ent = PlanEntitlement::where('plan_version_id', $v->id)->first();
        $this->expectException(\RuntimeException::class);
        $ent->update(['value' => 999]); // ممنوع على نسخة مقفلة
    }

    // ---------- Subscription state machine ----------
    public function test_subscription_lifecycle_transitions(): void
    {
        $org = $this->org();
        $v = $this->planVersion([]);
        $sub = $this->subscribe($org, $v); // trialing
        $svc = new SubscriptionService;
        TenantContext::bypass(true);
        $this->assertEquals('active', $svc->transition($sub, 'active')->status);
        $this->assertEquals('paused', $svc->transition($sub, 'paused')->status);
        $this->assertEquals('active', $svc->transition($sub, 'active')->status);
        $this->assertEquals('cancelled', $svc->transition($sub, 'cancelled')->status);
        $this->expectException(InvalidSubscriptionTransition::class);
        $svc->transition($sub, 'active'); // من cancelled: ممنوع
    }

    // ---------- Entitlements ----------
    public function test_boolean_numeric_unlimited_entitlements(): void
    {
        $org = $this->org();
        $v = $this->planVersion([
            'white_label.enabled' => ['value' => 1],
            'campaigns.active.max' => ['value' => 3],
            'storage.gb' => ['unlimited' => true],
        ]);
        $this->subscribe($org, $v);
        $e = app(EntitlementService::class);
        TenantContext::bypass(true);
        $this->assertTrue($e->allows($org, 'white_label.enabled'));
        $this->assertEquals(3, $e->limit($org, 'campaigns.active.max'));
        $this->assertNull($e->limit($org, 'storage.gb')); // unlimited
        $this->assertFalse($e->allows($org, 'custom_domain.enabled')); // غير مذكورة
    }

    public function test_addon_and_enterprise_override(): void
    {
        $org = $this->org();
        $v = $this->planVersion(['creators.max' => ['value' => 10]]);
        $sub = $this->subscribe($org, $v);
        $e = app(EntitlementService::class);
        TenantContext::bypass(true);
        $this->assertEquals(10, $e->limit($org, 'creators.max'));

        // Add-on: +5 مؤثرين
        $addon = AddOn::create(['key' => 'extra_creators', 'label' => '+5', 'feature_key' => 'creators.max', 'grant_value' => 5]);
        OrganizationAddOn::create(['tenant_id' => $org->tenant_id, 'organization_id' => $org->id, 'add_on_id' => $addon->id, 'quantity' => 1, 'status' => 'active']);
        $this->assertEquals(15, $e->limit($org, 'creators.max'));

        // Enterprise override: unlimited
        $sub->update(['overrides' => ['creators.max' => 'unlimited']]);
        $this->assertNull($e->limit($org, 'creators.max'));
    }

    public function test_no_subscription_means_no_paid_features_in_saas(): void
    {
        $org = $this->org();
        $e = app(EntitlementService::class);
        TenantContext::bypass(true);
        $this->assertFalse($e->allows($org, 'advanced_analytics.enabled'));
        $this->assertEquals(0, $e->limit($org, 'creators.max'));
    }

    // ---------- Usage metering ----------
    public function test_usage_consume_reject_and_idempotency(): void
    {
        $org = $this->org();
        $v = $this->planVersion(['exports.monthly.max' => ['value' => 2]]);
        $this->subscribe($org, $v);
        $m = app(UsageMeterService::class);
        TenantContext::bypass(true);

        $m->consume($org, 'exports.monthly.max', 1, 'idem-1');
        $this->assertEquals(1, $m->currentUsage($org, 'exports.monthly.max'));
        // idempotency: نفس المفتاح لا يُعدّ مرتين
        $m->consume($org, 'exports.monthly.max', 1, 'idem-1');
        $this->assertEquals(1, $m->currentUsage($org, 'exports.monthly.max'));

        $m->consume($org, 'exports.monthly.max', 1, 'idem-2');
        $this->assertEquals(2, $m->currentUsage($org, 'exports.monthly.max'));
        $this->assertEquals(0, $m->remaining($org, 'exports.monthly.max'));

        // تجاوز الحد يُرفض
        $this->expectException(EntitlementLimitExceeded::class);
        $m->consume($org, 'exports.monthly.max', 1, 'idem-3');
    }

    public function test_usage_isolated_between_organizations(): void
    {
        $orgA = $this->org();
        $orgB = $this->org();
        foreach ([$orgA, $orgB] as $o) { $this->subscribe($o, $this->planVersion(['api.requests.monthly.max' => ['value' => 100]])); }
        $m = app(UsageMeterService::class);
        TenantContext::bypass(true);
        $m->consume($orgA, 'api.requests.monthly.max', 40);
        $this->assertEquals(40, $m->currentUsage($orgA, 'api.requests.monthly.max'));
        $this->assertEquals(0, $m->currentUsage($orgB, 'api.requests.monthly.max')); // معزول
    }

    public function test_release_and_recalculate(): void
    {
        $org = $this->org();
        $this->subscribe($org, $this->planVersion(['automation.runs.monthly.max' => ['value' => 100]]));
        $m = app(UsageMeterService::class);
        TenantContext::bypass(true);
        $m->consume($org, 'automation.runs.monthly.max', 10, 'a');
        $m->release($org, 'automation.runs.monthly.max', 4, 'r');
        $this->assertEquals(6, $m->currentUsage($org, 'automation.runs.monthly.max'));
        $this->assertEquals(6, $m->recalculate($org, 'automation.runs.monthly.max'));
    }

    // ---------- Deployment modes ----------
    public function test_self_hosted_mode_is_unlimited_by_default(): void
    {
        config(['influencerhub.deployment_mode' => 'self_hosted']);
        $org = $this->org();
        $e = app(EntitlementService::class);
        TenantContext::bypass(true);
        $this->assertTrue($e->allows($org, 'advanced_analytics.enabled'));
        $this->assertNull($e->limit($org, 'creators.max')); // unlimited
    }

    public function test_dedicated_mode_uses_subscription_or_overrides(): void
    {
        config(['influencerhub.deployment_mode' => 'dedicated']);
        $org = $this->org();
        $v = $this->planVersion(['users.max' => ['value' => 50]]);
        $this->subscribe($org, $v);
        $e = app(EntitlementService::class);
        TenantContext::bypass(true);
        $this->assertEquals(50, $e->limit($org, 'users.max'));
    }

    // ---------- Provider ----------
    public function test_fake_provider_is_not_live(): void
    {
        $p = new FakeBillingProvider;
        $this->assertFalse($p->isLive());
        $this->assertEquals('fake', $p->key());
    }
}
