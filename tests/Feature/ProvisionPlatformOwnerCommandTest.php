<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_provisions_new_owner_from_env_password(): void
    {
        putenv('PLATFORM_OWNER_PASSWORD=' . self::PW);
        $this->artisan('platform:provision-owner', ['email' => 'owner@platform.test'])->assertExitCode(0);

        $u = User::withoutGlobalScopes()->where('email', 'owner@platform.test')->first();
        $this->assertNotNull($u);
        $this->assertTrue((bool) $u->is_system_admin);
        $this->assertTrue($u->is_active);
        $this->assertTrue(Hash::check(self::PW, $u->password));
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

    public function test_promotes_existing_user_idempotently(): void
    {
        $u = User::create(['name' => 'X', 'email' => 'exist@platform.test', 'password' => bcrypt('old'), 'is_active' => true]);
        putenv('PLATFORM_OWNER_PASSWORD=' . self::PW);
        $this->artisan('platform:provision-owner', ['email' => 'exist@platform.test'])->assertExitCode(0);

        $u->refresh();
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
