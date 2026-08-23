<?php

namespace Tests\Feature;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * بوابة دخول الوكالة (POST /login) على مستوى المنتج — لا عبر actingAs.
 *
 * تحرس ضد ثغرة صامتة كُشفت أثناء التحقق: كان الدور «مُطّلع» (viewer) — وهو دور
 * قراءة داخلي في الوكالة ممنوح صلاحيات العرض في CRM/المالية/المبدعين/لوحة العمليات —
 * مستبعَدًا من قائمة أدوار البوابة في LoginController، فيُرفض دخوله (302 → /login)
 * رغم أنه عضو وكالة صالح. بقية الاختبارات تستخدم actingAs فتتجاوز هذا الفحص، لذا
 * لم يُكشف. هذه الاختبارات تمارس المتحكّم الحقيقي وتمنع عودة الفجوة حتى بلا E2E في CI.
 */
class LoginPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function agencyUser(string $role): User
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مستخدم', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret-pw'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        TenantContext::reset();

        return $u;
    }

    /** كل أدوار بوابة الوكالة — بما فيها «مُطّلع» — تدخل وتصل إلى /app. */
    public function test_every_agency_portal_role_can_log_in(): void
    {
        foreach (Role::agencyPortalRoles() as $role) {
            $u = $this->agencyUser($role->value);

            $this->post('/login', ['email' => $u->email, 'password' => 'secret-pw'])
                ->assertRedirect('/app'); // الدور {$role->value} يجب أن يصل إلى /app

            $this->assertAuthenticatedAs($u);
            $this->post('/logout');
        }
    }

    /** انحدار مباشر: الدور «مُطّلع» تحديدًا يدخل ولا يُرتدّ إلى /login. */
    public function test_viewer_role_can_log_in_to_agency_portal(): void
    {
        $u = $this->agencyUser(Role::Viewer->value);

        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pw'])
            ->assertRedirect('/app')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($u);
    }

    /** دور بوابة أخرى (مبدع) بلا عضوية وكالة يُرفض من بوابة الوكالة. */
    public function test_non_agency_role_is_rejected_from_agency_portal(): void
    {
        $u = $this->agencyUser(Role::Influencer->value);

        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pw'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
