<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * توفير حسابات التشغيل الداخلية: ينشئ الحسابات السبعة بأدوار/علامات صحيحة وكلمات مرور
 * عاملة، idempotent، ولا يمسّ حسابات خارجية حقيقية. القائمة البيضاء فقط.
 */
class ProvisionInternalStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function agencyTenant(): Tenant
    {
        return TenantContext::withBypass(function () {
            $t = Tenant::create(['name' => 'IH', 'slug' => 'influencerhub', 'deployment_mode' => 'saas', 'status' => 'active']);
            Organization::create(['tenant_id' => $t->id, 'name' => 'InfluencerHub', 'slug' => 'ih-org', 'type' => 'agency', 'status' => 'active']);

            return $t;
        });
    }

    public function test_provisions_seven_accounts_with_correct_flags_and_roles(): void
    {
        $this->agencyTenant();
        Artisan::call('identity:provision-staff', ['--tenant' => 'influencerhub', '--yes' => true]);

        TenantContext::withBypass(function () {
            $owner = User::where('email', 'owner@influencerhub.io')->first();
            $this->assertTrue((bool) $owner->is_platform_owner);
            $this->assertTrue((bool) $owner->is_system_admin);
            $this->assertFalse(OrganizationMembership::where('user_id', $owner->id)->exists(), 'مالك المنصّة بلا مستأجر');

            $admin = User::where('email', 'admin@influencerhub.io')->first();
            $this->assertTrue((bool) $admin->is_system_admin);
            $this->assertFalse((bool) $admin->is_platform_owner);

            $map = ['operations' => 'operations_manager', 'campaigns' => 'campaign_manager',
                'creators' => 'creator_manager', 'finance' => 'finance', 'content' => 'content_reviewer'];
            foreach ($map as $prefix => $role) {
                $u = User::where('email', "{$prefix}@influencerhub.io")->first();
                $this->assertNotNull($u, "{$prefix} أُنشئ");
                $m = OrganizationMembership::where('user_id', $u->id)->first();
                $this->assertSame($role, $m->role);
                $this->assertSame('active', $m->status);
            }
        });
    }

    public function test_generated_password_actually_authenticates(): void
    {
        $this->agencyTenant();
        Artisan::call('identity:provision-staff', ['--tenant' => 'influencerhub', '--yes' => true]);
        $out = Artisan::output();

        // نلتقط كلمة مرور «operations@» المطبوعة مرّة واحدة، ونثبت أنها تسجّل الدخول فعليًّا.
        $pw = null;
        foreach (preg_split('/\R/', $out) as $line) {
            if (str_contains($line, 'operations@influencerhub.io')) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 2) {
                    $pw = $parts[1];
                }
            }
        }
        $this->assertNotNull($pw, 'طُبعت كلمة مرور operations@ مرّة واحدة');
        $this->assertGreaterThanOrEqual(20, strlen($pw));
        $this->assertTrue(
            Auth::attempt(['email' => 'operations@influencerhub.io', 'password' => $pw]),
            'كلمة المرور المولّدة تسجّل الدخول فعليًّا'
        );
    }

    public function test_is_idempotent_and_does_not_change_existing_password(): void
    {
        $this->agencyTenant();
        Artisan::call('identity:provision-staff', ['--tenant' => 'influencerhub', '--yes' => true]);
        $out1 = Artisan::output();
        $pw = null;
        foreach (preg_split('/\R/', $out1) as $line) {
            if (str_contains($line, 'finance@influencerhub.io')) {
                $pw = preg_split('/\s+/', trim($line))[1] ?? null;
            }
        }
        // إعادة التشغيل: لا حسابات جديدة، وكلمة المرور القديمة ما زالت تعمل
        Artisan::call('identity:provision-staff', ['--tenant' => 'influencerhub', '--yes' => true]);
        $this->assertStringContainsString('لا حسابات جديدة', Artisan::output());
        $this->assertTrue(Auth::attempt(['email' => 'finance@influencerhub.io', 'password' => $pw]));
    }

    public function test_never_touches_external_real_users(): void
    {
        $t = $this->agencyTenant();
        // مستخدم عميل حقيقي بكلمة مرور معروفة
        $ext = TenantContext::withBypass(function () use ($t) {
            $u = User::create(['name' => 'عميل حقيقي', 'email' => 'buyer@nike.com', 'password' => 'known-Password-123', 'is_active' => true]);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'نايك', 'type' => 'company', 'status' => 'active']);
            ClientMember::create(['tenant_id' => $t->id, 'client_id' => $cl->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);

            return $u;
        });

        Artisan::call('identity:provision-staff', ['--tenant' => 'influencerhub', '--yes' => true]);

        // المستخدم الخارجي لم يُمسّ: كلمته الأصلية ما زالت تعمل، ولا علامات منصّة أُضيفت
        $this->assertTrue(Auth::attempt(['email' => 'buyer@nike.com', 'password' => 'known-Password-123']));
        $fresh = TenantContext::withBypass(fn () => User::find($ext->id));
        $this->assertFalse((bool) $fresh->is_system_admin);
    }

    public function test_inventory_classifies_without_mutating(): void
    {
        $this->agencyTenant();
        TenantContext::withBypass(fn () => User::create(['name' => 'showcase', 'email' => 'showcase_admin@showcase.test', 'password' => 'x', 'is_active' => true]));
        $before = TenantContext::withBypass(fn () => User::count());

        Artisan::call('identity:provision-staff', ['--inventory' => true]);
        $out = Artisan::output();

        $this->assertStringContainsString('TEST_OR_SHOWCASE', $out);
        $after = TenantContext::withBypass(fn () => User::count());
        $this->assertSame($before, $after, 'الجرد لا يُنشئ/يحذف أحدًا');
    }

    public function test_platform_accounts_only_when_no_tenant_flag(): void
    {
        $this->agencyTenant();
        Artisan::call('identity:provision-staff', ['--yes' => true]); // بلا --tenant
        TenantContext::withBypass(function () {
            $this->assertTrue(User::where('email', 'owner@influencerhub.io')->exists());
            $this->assertTrue(User::where('email', 'admin@influencerhub.io')->exists());
            $this->assertFalse(User::where('email', 'operations@influencerhub.io')->exists(), 'أدوار المستأجر تُتخطّى بلا --tenant');
        });
    }
}
