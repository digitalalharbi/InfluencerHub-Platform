<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\{Plan, PlanVersion, Subscription};
use App\Domain\Identity\Models\User;
use App\Domain\Onboarding\Models\SelfSignup;
use App\Domain\Onboarding\Services\SelfSignupService;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * المسار الذاتي: من بريد مجهول إلى مساحة وكالة عاملة بتجربة مجانية.
 *
 * ما يحرسه هذا الملفّ ليس وجود الصفحات بل أن الحالة تنتقل فعلًا: مستأجر
 * ومؤسسة ومالك وعضوية واشتراك — أو لا شيء إن فشل أحدها.
 */
class SelfSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function startedSignup(string $email = 'owner@ex.com'): array
    {
        return app(SelfSignupService::class)->start($email, 'agency');
    }

    /** خطة فعّالة يبدأ عليها المشترك — بلا خطة لا تجربة، وهذا سلوك مقصود. */
    private function activePlan(): PlanVersion
    {
        $plan = Plan::create(['key' => 'starter', 'name' => 'المبتدئة', 'is_active' => true]);

        return PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
    }

    public function test_start_creates_signup_without_storing_the_code_in_clear_text(): void
    {
        $this->post('/register/agency/start', ['email' => 'owner@ex.com'])->assertRedirect();

        $s = SelfSignup::where('email', 'owner@ex.com')->firstOrFail();
        $this->assertSame('email_verification_pending', $s->status);
        $this->assertNotNull($s->verification_code_hash);
        // الرمز مُجزّأ: تسريب الجدول لا يمنح أحدًا حسابًا
        $this->assertStringStartsWith('$2y$', $s->verification_code_hash);
    }

    public function test_wrong_code_is_rejected_and_counted(): void
    {
        [$signup] = $this->startedSignup();

        $this->post("/register/agency/verify/{$signup->reference}", ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($signup->fresh()->email_verified_at);
        $this->assertSame(1, $signup->fresh()->verification_attempts);
    }

    public function test_code_stops_working_after_too_many_attempts(): void
    {
        [$signup, $code] = $this->startedSignup();
        $signup->update(['verification_attempts' => 5]);

        // حتى الرمز الصحيح يُرفض بعد استنفاد المحاولات
        $this->expectException(\RuntimeException::class);
        app(SelfSignupService::class)->verify($signup->fresh(), $code);
    }

    public function test_expired_code_is_rejected(): void
    {
        [$signup, $code] = $this->startedSignup();
        $signup->update(['code_expires_at' => now()->subMinute()]);

        $this->expectException(\RuntimeException::class);
        app(SelfSignupService::class)->verify($signup->fresh(), $code);
    }

    public function test_correct_code_verifies_and_clears_the_hash(): void
    {
        [$signup, $code] = $this->startedSignup();

        $this->post("/register/agency/verify/{$signup->reference}", ['code' => $code])
            ->assertRedirect("/register/agency/setup/{$signup->reference}");

        $fresh = $signup->fresh();
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNull($fresh->verification_code_hash, 'بقي رمز مستهلَك صالحًا للاستعمال');
    }

    public function test_setup_is_unreachable_before_verification(): void
    {
        [$signup] = $this->startedSignup();

        $this->get("/register/agency/setup/{$signup->reference}")
            ->assertRedirect("/register/agency/verify/{$signup->reference}");
    }

    public function test_provisioning_creates_a_working_workspace_with_a_trial(): void
    {
        $this->activePlan();
        [$signup, $code] = $this->startedSignup();
        app(SelfSignupService::class)->verify($signup, $code);

        $this->post("/register/agency/complete/{$signup->reference}", [
            'owner_name' => 'مالك', 'organization_name' => 'وكالة الاختبار',
            'password' => 'StrongPass1', 'password_confirmation' => 'StrongPass1',
        ])->assertRedirect('/app');

        TenantContext::bypass(true);
        $fresh = $signup->fresh();
        $this->assertNotNull($fresh->created_tenant_id);
        $this->assertSame('active', $fresh->status);

        $tenant = Tenant::find($fresh->created_tenant_id);
        $this->assertSame('active', $tenant->status);

        $org = Organization::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('agency', $org->type);

        $membership = OrganizationMembership::where('organization_id', $org->id)->firstOrFail();
        $this->assertSame('agency_admin', $membership->role);

        // تجربة مجانية صريحة بلا ادّعاء دفع
        $sub = Subscription::where('organization_id', $org->id)->firstOrFail();
        $this->assertSame('trialing', $sub->status);
        $this->assertNotSame('stripe', $sub->billing_provider, 'ادّعى النظام مزوّد دفع حقيقيًّا');
        $this->assertNotNull($sub->trial_ends_at);

        $user = User::find($fresh->created_user_id);
        $this->assertTrue(Hash::check('StrongPass1', $user->password));
        // أكّد بريده في هذا المسار فلا يُطالَب به ثانية
        $this->assertNotNull($user->email_verified_at, 'بريد مؤكَّد سُجّل غير مؤكَّد');
        TenantContext::reset();

        $this->assertAuthenticatedAs($user);
    }

    public function test_workspace_cannot_be_provisioned_twice(): void
    {
        [$signup, $code] = $this->startedSignup();
        app(SelfSignupService::class)->verify($signup, $code);
        $payload = [
            'owner_name' => 'مالك', 'organization_name' => 'وكالة',
            'password' => 'StrongPass1', 'password_confirmation' => 'StrongPass1',
        ];
        $this->post("/register/agency/complete/{$signup->reference}", $payload);

        TenantContext::bypass(true);
        $tenantsBefore = Tenant::count();
        TenantContext::reset();

        $this->post("/register/agency/complete/{$signup->reference}", $payload)
            ->assertSessionHasErrors('setup');

        TenantContext::bypass(true);
        $this->assertSame($tenantsBefore, Tenant::count(), 'أُنشئ مستأجر مكرّر');
        TenantContext::reset();
    }

    public function test_existing_email_cannot_open_a_second_workspace(): void
    {
        User::create(['name' => 'قديم', 'email' => 'owner@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        [$signup, $code] = $this->startedSignup('owner@ex.com');
        app(SelfSignupService::class)->verify($signup, $code);

        $this->post("/register/agency/complete/{$signup->reference}", [
            'owner_name' => 'مالك', 'organization_name' => 'وكالة',
            'password' => 'StrongPass1', 'password_confirmation' => 'StrongPass1',
        ])->assertSessionHasErrors('setup');
    }

    public function test_weak_password_is_rejected(): void
    {
        [$signup, $code] = $this->startedSignup();
        app(SelfSignupService::class)->verify($signup, $code);

        $this->post("/register/agency/complete/{$signup->reference}", [
            'owner_name' => 'مالك', 'organization_name' => 'وكالة',
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    /** المساحة الجديدة معزولة: لا ترى بيانات مستأجر آخر. */
    public function test_new_workspace_starts_empty_and_isolated(): void
    {
        $other = Tenant::create(['name' => 'آخر', 'slug' => 'other-t', 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        \App\Domain\CRM\Models\Client::create(['tenant_id' => $other->id, 'client_number' => 'CL-X',
            'display_name' => 'عميل الغير', 'status' => 'active']);
        TenantContext::reset();

        [$signup, $code] = $this->startedSignup();
        app(SelfSignupService::class)->verify($signup, $code);
        $this->post("/register/agency/complete/{$signup->reference}", [
            'owner_name' => 'مالك', 'organization_name' => 'وكالة جديدة',
            'password' => 'StrongPass1', 'password_confirmation' => 'StrongPass1',
        ]);

        $this->get('/app/clients')->assertOk()
            ->assertDontSee('عميل الغير', false);
    }

    /**
     * بلا خطة فعّالة تُنشأ المساحة بلا اشتراك بدل أن يفشل التسجيل كلّه.
     * يُثبَّت هنا لأنه قرار: الوصول إلى المنتَج أهمّ من وجود سجلّ فوترة.
     */
    public function test_workspace_is_created_even_when_no_plan_exists(): void
    {
        [$signup, $code] = $this->startedSignup();
        app(SelfSignupService::class)->verify($signup, $code);

        $this->post("/register/agency/complete/{$signup->reference}", [
            'owner_name' => 'مالك', 'organization_name' => 'وكالة بلا خطة',
            'password' => 'StrongPass1', 'password_confirmation' => 'StrongPass1',
        ])->assertRedirect('/app');

        TenantContext::bypass(true);
        $org = Organization::where('tenant_id', $signup->fresh()->created_tenant_id)->firstOrFail();
        $this->assertSame(0, Subscription::where('organization_id', $org->id)->count());
        TenantContext::reset();
    }
}
