<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * أمر QA `preview:rotate-smoke-credential` — يدوّر كلمة مرور مدير بيئة العرض فقط،
 * ولا يمسّ أي بيانات أخرى. اختبارات تُثبت الأثر الضيّق والرفض عند اختلال أي ثابت.
 */
class RotateSmokeCredentialTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'Zx9-strong-ephemeral-password-40chars-long!!';

    protected function tearDown(): void
    {
        putenv('PRODUCTION_SMOKE_PASSWORD');   // لا نُسرّب المتغيّر بين الاختبارات
        parent::tearDown();
    }

    /** يبني هوية بيئة العرض المُصرَّح بها (قابلة لضبط الدور/الحالة لاختبار الرفض). */
    private function seedShowcase(string $role = 'agency_admin', string $status = 'active'): User
    {
        $t = Tenant::create(['name' => 'Showcase', 'slug' => 'showcase', 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'Showcase', 'slug' => 'showcase-org', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مدير العرض', 'email' => 'showcase_admin@showcase.test', 'password' => bcrypt('old-password'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => $status]);
        TenantContext::reset();
        return $u;
    }

    /** مستخدم في مستأجر حقيقي آخر — يجب ألّا يتأثّر إطلاقًا. */
    private function otherUser(): User
    {
        $t = Tenant::create(['name' => 'Real', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'Real', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'حقيقي', 'email' => 'real_admin@client.example', 'password' => bcrypt('real-password'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();
        return $u;
    }

    private function fresh(int $id): User
    {
        return User::withoutGlobalScopes()->findOrFail($id);
    }

    public function test_rotates_only_the_showcase_admin_password(): void
    {
        $admin = $this->seedShowcase();
        $other = $this->otherUser();
        $otherHashBefore = $this->fresh($other->id)->password;
        $tenantsBefore = Tenant::count();
        $usersBefore = User::withoutGlobalScopes()->count();

        putenv('PRODUCTION_SMOKE_PASSWORD=' . self::PW);
        $this->artisan('preview:rotate-smoke-credential', ['--force' => true])->assertExitCode(0);

        // كلمة مرور المدير تغيّرت إلى الجديدة، والقديمة لم تعد تعمل
        $this->assertTrue(Hash::check(self::PW, $this->fresh($admin->id)->password));
        $this->assertFalse(Hash::check('old-password', $this->fresh($admin->id)->password));
        // لا أثر على أي بيانات أخرى: المستخدم الآخر ثابت، ولا حذف/إنشاء سجلّات
        $this->assertSame($otherHashBefore, $this->fresh($other->id)->password);
        $this->assertSame($tenantsBefore, Tenant::count());
        $this->assertSame($usersBefore, User::withoutGlobalScopes()->count());
    }

    public function test_fails_without_force(): void
    {
        $admin = $this->seedShowcase();
        putenv('PRODUCTION_SMOKE_PASSWORD=' . self::PW);
        $this->artisan('preview:rotate-smoke-credential')->assertExitCode(1);
        $this->assertTrue(Hash::check('old-password', $this->fresh($admin->id)->password));
    }

    public function test_fails_when_secret_missing_or_too_short(): void
    {
        $admin = $this->seedShowcase();
        putenv('PRODUCTION_SMOKE_PASSWORD');   // غير مضبوط
        $this->artisan('preview:rotate-smoke-credential', ['--force' => true])->assertExitCode(1);
        putenv('PRODUCTION_SMOKE_PASSWORD=short');   // أقصر من 32
        $this->artisan('preview:rotate-smoke-credential', ['--force' => true])->assertExitCode(1);
        $this->assertTrue(Hash::check('old-password', $this->fresh($admin->id)->password));
    }

    public function test_fails_when_showcase_tenant_absent(): void
    {
        $this->otherUser();   // مستأجر حقيقي فقط، لا بيئة عرض
        putenv('PRODUCTION_SMOKE_PASSWORD=' . self::PW);
        $this->artisan('preview:rotate-smoke-credential', ['--force' => true])->assertExitCode(1);
    }

    public function test_fails_when_membership_role_or_status_wrong(): void
    {
        // نفس البريد والمستأجر، لكن الدور ليس agency_admin
        $admin = $this->seedShowcase(role: 'campaign_manager', status: 'active');
        putenv('PRODUCTION_SMOKE_PASSWORD=' . self::PW);
        $this->artisan('preview:rotate-smoke-credential', ['--force' => true])->assertExitCode(1);
        $this->assertTrue(Hash::check('old-password', $this->fresh($admin->id)->password));
    }

    public function test_fails_when_membership_inactive(): void
    {
        $admin = $this->seedShowcase(role: 'agency_admin', status: 'suspended');
        putenv('PRODUCTION_SMOKE_PASSWORD=' . self::PW);
        $this->artisan('preview:rotate-smoke-credential', ['--force' => true])->assertExitCode(1);
        $this->assertTrue(Hash::check('old-password', $this->fresh($admin->id)->password));
    }
}
