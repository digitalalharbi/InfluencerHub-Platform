<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember};
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * أمان الحساب متاح لكل مستخدم مُصادَق في كل بوابة.
 *
 * قبل هذا كان مقصورًا على بوابة العميل: لم يكن بوسع المبدع ولا الشريك ولا
 * موظّف الوكالة تغيير كلمة مروره أو إنهاء جلسة مسروقة — وهي حاجة أمنية
 * لا ميزة بوابة. هذه الاختبارات تمنع عودة الفجوة.
 */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
    }

    /** موظّف وكالة بدور غير إداري — الأمان ليس حكرًا على المدير. */
    private function agencyStaff(string $role = 'campaign_manager'): User
    {
        $t = $this->tenant();
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'موظّف', 'email' => Str::random(6) . '@ex.com', 'password' => Hash::make('OldPass123'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();

        return $u;
    }

    private function creatorUser(): User
    {
        $t = $this->tenant();
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        // بوابة المبدع محكومة بحقّ الخطة — بلا اشتراك مفعِّل لها يُرفض الدخول
        $plan = \App\Domain\Billing\Models\Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = \App\Domain\Billing\Models\PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        \App\Domain\Billing\Models\PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new \App\Domain\Billing\Actions\CreateSubscription)->handle($org, $pv);
        $u = User::create(['name' => 'مبدع', 'email' => Str::random(6) . '@ex.com', 'password' => Hash::make('OldPass123'), 'is_active' => true]);
        Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer',
            'display_name' => 'مبدع', 'status' => 'active', 'user_id' => $u->id]);
        TenantContext::reset();

        return $u;
    }

    private function clientUser(): User
    {
        $t = $this->tenant();
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => Hash::make('OldPass123'), 'is_active' => true]);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'ع', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();

        return $u;
    }

    /** @return array<int,array{0:string,1:string}> [مسار الصفحة, جذر إجراءات الأمان] */
    public static function surfaces(): array
    {
        return [
            'agency' => ['/app/account', '/app/account'],
            'creator' => ['/creator/account', '/creator/account/settings'],
            'client' => ['/client/account', '/client/account/settings'],
        ];
    }

    private function userFor(string $page): User
    {
        return match (true) {
            str_starts_with($page, '/app') => $this->agencyStaff(),
            str_starts_with($page, '/creator') => $this->creatorUser(),
            default => $this->clientUser(),
        };
    }

    /** @dataProvider surfaces */
    public function test_security_surface_is_reachable(string $page, string $actionBase): void
    {
        $u = $this->userFor($page);
        $this->actingAs($u)->get($page)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('prefs')->has('categories')->has('sessions')->has('twoFactorEnabled'));
    }

    /** @dataProvider surfaces */
    public function test_password_change_requires_correct_current(string $page, string $actionBase): void
    {
        $u = $this->userFor($page);
        $this->actingAs($u)->post("{$actionBase}/password", [
            'current_password' => 'WrongPass123', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPass123', $u->fresh()->password), 'تغيّرت كلمة المرور رغم خطأ الحالية');
    }

    /** @dataProvider surfaces */
    public function test_password_change_succeeds_and_kills_other_sessions(string $page, string $actionBase): void
    {
        $u = $this->userFor($page);
        DB::table('sessions')->insert([
            'id' => 'stale-' . $u->id, 'user_id' => $u->id, 'ip_address' => '1.1.1.1',
            'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($u)->post("{$actionBase}/password", [
            'current_password' => 'OldPass123', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPass123', $u->fresh()->password));
        // الجلسة القديمة تموت مع تغيير كلمة المرور — وإلا بقيت الجلسة المسروقة صالحة
        $this->assertDatabaseMissing('sessions', ['id' => 'stale-' . $u->id]);
    }

    /** @dataProvider surfaces */
    public function test_weak_password_is_rejected(string $page, string $actionBase): void
    {
        $u = $this->userFor($page);
        $this->actingAs($u)->post("{$actionBase}/password", [
            'current_password' => 'OldPass123', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    /** @dataProvider surfaces */
    public function test_revoke_other_sessions_keeps_current(string $page, string $actionBase): void
    {
        $u = $this->userFor($page);
        DB::table('sessions')->insert([
            'id' => 'other-' . $u->id, 'user_id' => $u->id, 'ip_address' => '2.2.2.2',
            'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($u)->post("{$actionBase}/sessions/revoke-others")->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-' . $u->id]);
    }

    /** @dataProvider surfaces */
    public function test_guest_cannot_reach_security_surface(string $page, string $actionBase): void
    {
        $this->get($page)->assertRedirect();
    }

    /** لا يُغيّر أحد كلمة مرور غيره: الإجراء يعمل على المستخدم المُصادَق فقط. */
    public function test_password_change_only_affects_acting_user(): void
    {
        $a = $this->agencyStaff();
        $b = $this->agencyStaff();

        $this->actingAs($a)->post('/app/account/password', [
            'current_password' => 'OldPass123', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('NewPass123', $a->fresh()->password));
        $this->assertTrue(Hash::check('OldPass123', $b->fresh()->password), 'تأثّر مستخدم آخر');
    }

    /** إنهاء الجلسات لا يمسّ جلسات مستخدم آخر. */
    public function test_revoke_sessions_does_not_touch_other_users(): void
    {
        $a = $this->agencyStaff();
        $b = $this->agencyStaff();
        DB::table('sessions')->insert([
            ['id' => 'a-old', 'user_id' => $a->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->timestamp],
            ['id' => 'b-live', 'user_id' => $b->id, 'ip_address' => '3.3.3.3', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($a)->post('/app/account/sessions/revoke-others')->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'a-old']);
        $this->assertDatabaseHas('sessions', ['id' => 'b-live']);
    }

    /** بوابة الشريك تصل إلى أمان الحساب أيضًا (عبر مساحة الوكالة المضيفة). */
    public function test_partner_member_has_a_password_path(): void
    {
        $t = $this->tenant();
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'شريك', 'email' => Str::random(6) . '@ex.com', 'password' => Hash::make('OldPass123'), 'is_active' => true]);
        $a = ExternalAgency::create(['tenant_id' => $t->id, 'agency_number' => 'PA-' . $t->id, 'name' => 'ش', 'status' => 'approved']);
        ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $a->id, 'user_id' => $u->id,
            'role' => 'partner_admin', 'status' => 'active', 'accepted_at' => now()]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'viewer', 'status' => 'active']);
        TenantContext::reset();

        $this->actingAs($u)->post('/app/account/password', [
            'current_password' => 'OldPass123', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPass123', $u->fresh()->password));
    }
}
