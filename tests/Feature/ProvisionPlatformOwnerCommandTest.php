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
 * أمر توفير مالك المنصّة (§18): كلمة المرور من البيئة/إدخال سرّي، قوّة دنيا مفروضة،
 * لا كلمة مرور في الكود، ولا تُطبع. ينشئ/يرقّي المستخدم ويضبط is_system_admin.
 */
class ProvisionPlatformOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'a-very-strong-owner-password-1234';

    protected function tearDown(): void
    {
        putenv('PLATFORM_OWNER_PASSWORD');
        parent::tearDown();
    }

    public function test_provisions_new_standalone_owner_from_env_password(): void
    {
        putenv('PLATFORM_OWNER_PASSWORD=' . self::PW);
        $this->artisan('platform:provision-owner', ['email' => 'owner@platform.test'])->assertExitCode(0);

        $u = User::withoutGlobalScopes()->where('email', 'owner@platform.test')->first();
        $this->assertNotNull($u);
        $this->assertTrue((bool) $u->is_platform_owner);   // العلامة المخصّصة
        $this->assertTrue((bool) $u->is_system_admin);      // الهرمية
        $this->assertTrue($u->is_active);
        $this->assertTrue(Hash::check(self::PW, $u->password));

        // §2: صفر روابط مستأجر — لا يظهر في أي قائمة فريق/عضو ولا يُحسب مقعدًا.
        $this->assertSame(0, OrganizationMembership::withoutGlobalScopes()->where('user_id', $u->id)->count());
        $this->assertSame(0, \App\Domain\CRM\Models\ClientMember::withoutGlobalScopes()->where('user_id', $u->id)->count());
        $this->assertSame(0, \App\Domain\Creators\Models\Creator::withoutGlobalScopes()->where('user_id', $u->id)->count());
        $this->assertSame(0, \App\Domain\Partners\Models\ExternalAgencyMember::withoutGlobalScopes()->where('user_id', $u->id)->count());
    }

    public function test_refuses_to_promote_a_tenant_linked_user(): void
    {
        // مستخدم مرتبط بمستأجر (عضوية مؤسسة) — يجب رفض ترقيته، وعدم لمس عضويته.
        $t = Tenant::create(['name' => 'A', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'A', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'Linked', 'email' => 'linked@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        putenv('PLATFORM_OWNER_PASSWORD=' . self::PW);
        $this->artisan('platform:provision-owner', ['email' => 'linked@ex.com'])->assertExitCode(1);

        $u->refresh();
        $this->assertFalse((bool) $u->is_platform_owner);   // لم يُرقَّ
        $this->assertFalse((bool) $u->is_system_admin);
        // العضوية القائمة لم تُحذف (نرفض، لا نمسح).
        $this->assertSame(1, OrganizationMembership::withoutGlobalScopes()->where('user_id', $u->id)->count());
    }

    public function test_rejects_short_password_without_creating_user(): void
    {
        putenv('PLATFORM_OWNER_PASSWORD=short');
        $this->artisan('platform:provision-owner', ['email' => 'x@platform.test'])->assertExitCode(1);
        $this->assertNull(User::withoutGlobalScopes()->where('email', 'x@platform.test')->first());
    }

    public function test_rejects_invalid_email(): void
    {
        putenv('PLATFORM_OWNER_PASSWORD=' . self::PW);
        $this->artisan('platform:provision-owner', ['email' => 'not-an-email'])->assertExitCode(1);
    }

    public function test_promotes_existing_standalone_user_idempotently(): void
    {
        // مستخدم قائم بلا أي رابط مستأجر — يجوز ترقيته.
        $u = User::create(['name' => 'X', 'email' => 'exist@platform.test', 'password' => bcrypt('old'), 'is_active' => true]);
        putenv('PLATFORM_OWNER_PASSWORD=' . self::PW);
        $this->artisan('platform:provision-owner', ['email' => 'exist@platform.test'])->assertExitCode(0);

        $u->refresh();
        $this->assertTrue((bool) $u->is_platform_owner);
        $this->assertTrue((bool) $u->is_system_admin);
        $this->assertTrue(Hash::check(self::PW, $u->password));
    }

    public function test_output_never_prints_the_password(): void
    {
        putenv('PLATFORM_OWNER_PASSWORD=' . self::PW);
        $this->artisan('platform:provision-owner', ['email' => 'owner2@platform.test'])
            ->doesntExpectOutputToContain(self::PW)
            ->assertExitCode(0);
    }
}
