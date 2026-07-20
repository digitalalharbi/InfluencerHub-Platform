<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * حدود البوابات: لا يعبر مستخدم بوابةً ليست له، ولا يصل إلى بوابة الإدارة.
 *
 * كل بوابة لها وسيطها (creator/client_member/partner_member/system_admin)،
 * وهذا الاختبار يثبت أن الوسائط تُغلق فعلًا لا أن الروابط مخفيّة فقط —
 * الاعتماد على إخفاء عنصر القائمة ليس حماية.
 */
class PortalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:Organization} */
    private function org(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $o = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $o];
    }

    private function user(string $name = 'u'): User
    {
        TenantContext::bypass(true);
        $u = User::create(['name' => $name, 'email' => Str::random(6) . '@ex.com', 'password' => Hash::make('Passw0rd1'), 'is_active' => true]);
        TenantContext::reset();

        return $u;
    }

    /** موظّف وكالة لا يملك أي عضوية بوابة. */
    private function agencyOnly(): User
    {
        [$t, $o] = $this->org();
        $u = $this->user('agency');
        TenantContext::bypass(true);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $o->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return $u;
    }

    private function clientOnly(): User
    {
        [$t] = $this->org();
        $u = $this->user('client');
        TenantContext::bypass(true);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'ع', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        TenantContext::reset();

        return $u;
    }

    private function creatorOnly(): User
    {
        [$t] = $this->org();
        $u = $this->user('creator');
        TenantContext::bypass(true);
        Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => 'influencer',
            'display_name' => 'م', 'status' => 'active', 'user_id' => $u->id]);
        TenantContext::reset();

        return $u;
    }

    /** كل بوابة مغلقة أمام من ليس عضوًا فيها — لا 200 بأي حال. */
    public function test_client_member_cannot_enter_agency_or_creator_or_admin(): void
    {
        $u = $this->clientOnly();
        foreach (['/app', '/app/clients', '/creator', '/creator/account', '/partner', '/beta/admin'] as $path) {
            $res = $this->actingAs($u)->get($path);
            $this->assertNotSame(200, $res->getStatusCode(), "عبر عضو العميل إلى {$path}");
        }
    }

    public function test_creator_cannot_enter_agency_or_client_or_admin(): void
    {
        $u = $this->creatorOnly();
        foreach (['/app', '/app/payouts', '/client', '/client/account', '/partner', '/beta/admin'] as $path) {
            $res = $this->actingAs($u)->get($path);
            $this->assertNotSame(200, $res->getStatusCode(), "عبر المبدع إلى {$path}");
        }
    }

    /** موظّف الوكالة لا يدخل بوابات العملاء/المبدعين ولا لوحة النظام. */
    public function test_agency_staff_cannot_enter_portals_or_system_admin(): void
    {
        $u = $this->agencyOnly();
        foreach (['/client', '/client/account', '/creator', '/creator/account', '/partner', '/beta/admin'] as $path) {
            $res = $this->actingAs($u)->get($path);
            $this->assertNotSame(200, $res->getStatusCode(), "عبر موظّف الوكالة إلى {$path}");
        }
    }

    /** لوحة النظام محكومة بعلَم is_system_admin لا بدور المؤسسة. */
    public function test_system_admin_panel_requires_system_flag(): void
    {
        $u = $this->agencyOnly();
        $this->actingAs($u)->get('/beta/admin')->assertForbidden();

        TenantContext::bypass(true);
        $u->forceFill(['is_system_admin' => true])->save();
        TenantContext::reset();

        $this->actingAs($u->fresh())->get('/beta/admin')->assertOk();
    }

    /** الزائر لا يصل إلى أي بوابة. */
    public function test_guest_is_locked_out_everywhere(): void
    {
        foreach (['/app', '/client', '/creator', '/partner', '/beta/admin', '/app/account'] as $path) {
            $this->get($path)->assertRedirect();
        }
    }

    /** عضوية موقوفة لا تفتح البوابة (fail-closed لا fail-open). */
    public function test_suspended_client_member_is_locked_out(): void
    {
        [$t] = $this->org();
        $u = $this->user('suspended');
        TenantContext::bypass(true);
        $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-S', 'display_name' => 'ع', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'suspended', 'accepted_at' => now()]);
        TenantContext::reset();

        $res = $this->actingAs($u)->get('/client');
        $this->assertNotSame(200, $res->getStatusCode(), 'عضو موقوف دخل البوابة');
    }

    // ===== إشراف مدير النظام: قراءة لا كتابة =====

    /**
     * مدير النظام يتجاوز نطاق المستأجر ليُشرف، وكان التجاوز يشمل الكتابة.
     *
     * وقع فعليًّا في بيانات التطوير: العقد 298 (المستأجر 14) أُرسل بالفاعل 2،
     * وهو مدير نظام عضويته في المستأجر 1 وحده — سجّله التدقيق نفسه.
     */
    public function test_system_admin_may_read_a_foreign_tenant_workspace(): void
    {
        [$t, $o] = $this->org();
        $admin = $this->user('مدير');
        $admin->forceFill(['is_system_admin' => true])->save();

        $this->actingAs($admin)->get('/app')->assertOk();
    }

    /** ولا يكتب فيها: الإشراف قراءة، والرفض يقول البديل. */
    public function test_system_admin_cannot_write_into_a_foreign_tenant_workspace(): void
    {
        [$t, $o] = $this->org();
        $admin = $this->user('مدير');
        $admin->forceFill(['is_system_admin' => true])->save();

        TenantContext::bypass(true);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id,
            'display_name' => 'عميل', 'status' => 'active']);
        $contract = \App\Domain\Contracts\Models\Contract::create([
            'tenant_id' => $t->id, 'contract_number' => 'CT-' . $t->id . '-1', 'party_type' => 'client',
            'client_id' => $client->id, 'title' => 'عقد', 'value_minor' => 1000, 'currency' => 'SAR', 'status' => 'draft',
        ]);
        TenantContext::reset();

        $res = $this->actingAs($admin)->post("/app/contracts/{$contract->id}/send");
        $this->assertSame(403, $res->getStatusCode(), 'مدير النظام كتب داخل مساحة مستأجر ليست له');

        TenantContext::bypass(true);
        $this->assertSame('draft', $contract->fresh()->status, 'تغيّرت حالة العقد رغم الردّ');
        TenantContext::reset();
    }
}
