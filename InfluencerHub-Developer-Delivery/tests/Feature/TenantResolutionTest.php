<?php

namespace Tests\Feature;

use App\Domain\Creators\Services\CreatorApplicationService;
use App\Domain\Tenancy\Models\{Tenant, Organization};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Phase 4 hardening — حلّ المستأجر الصريح للبوابة العامة (fail-closed، لا "أول مستأجر"). */
class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function org(string $slug, string $mode = 'saas'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => $mode, 'status' => 'active']);
        TenantContext::bypass(true);
        $o = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => $slug, 'type' => 'agency', 'status' => 'active']);
        TenantContext::reset();
        return [$t, $o];
    }
    private function svc(): CreatorApplicationService { return app(CreatorApplicationService::class); }

    public function test_valid_slug_resolves_correct_org(): void
    {
        [$t, $o] = $this->org('agency-a');
        $ctx = $this->svc()->resolveTenantContext('agency-a');
        $this->assertNotNull($ctx);
        $this->assertEquals($t->id, $ctx[0]->id);
        $this->assertEquals('slug', $ctx[2]);
    }

    public function test_invalid_slug_fails_closed(): void
    {
        $this->org('agency-a');
        $this->assertNull($this->svc()->resolveTenantContext('does-not-exist'));
    }

    public function test_saas_without_slug_is_rejected(): void
    {
        config(['influencerhub.deployment_mode' => 'saas']);
        $this->org('agency-a');
        $this->assertNull($this->svc()->resolveTenantContext(null)); // لا "أول مستأجر"
    }

    public function test_does_not_use_first_tenant_when_multiple(): void
    {
        $this->org('agency-a');
        $this->org('agency-b');
        // بلا slug في saas → مرفوض حتى مع وجود مستأجرين
        $this->assertNull($this->svc()->resolveTenantContext(null));
        // slug محدّد يحلّ الصحيح فقط
        $this->assertEquals('agency-b', $this->svc()->resolveTenantContext('agency-b')[1]->slug);
    }

    public function test_http_saas_without_slug_is_404(): void
    {
        $this->org('agency-a');
        $this->post('/join/creator', ['capabilities' => ['influencer'], 'full_name' => 'x', 'email' => 'x@y.com', 'phone' => '+966500000000', 'terms' => '1', 'privacy' => '1'])
            ->assertNotFound(); // fail-closed
    }

    public function test_application_bound_to_resolved_tenant_only(): void
    {
        [$tA, $oA] = $this->org('agency-a');
        [$tB, $oB] = $this->org('agency-b');
        $this->post('/join/creator?a=agency-a', ['capabilities' => ['influencer'], 'full_name' => 'x', 'email' => 'a@a.com', 'phone' => '+966500000000', 'terms' => '1', 'privacy' => '1']);
        TenantContext::bypass(true);
        $app = \App\Domain\Creators\Models\CreatorApplication::where('email', 'a@a.com')->first();
        $this->assertEquals($tA->id, $app->tenant_id);      // مربوط بمستأجر A فقط
        $this->assertEquals('agency-a', $app->workspace_slug);
        TenantContext::reset();
    }
}
