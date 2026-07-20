<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Content\Models\ContentItem;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** بوابة العميل React/Inertia — لوحة معزولة على العميل النشِط + عدّادات القرارات المعلّقة. */
class InertiaClientPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Client,2:Tenant} */
    private function member(string $status = 'active'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::firstOrCreate(['tenant_id' => $t->id, 'slug' => 'org-' . $t->id], ['name' => 'o', 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'عميل', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('secret12'), 'is_active' => true]);
        $client = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id, 'display_name' => 'شركة العميل', 'type' => 'company', 'status' => 'active']);
        ClientMember::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => $status, 'accepted_at' => now()]);
        TenantContext::reset();
        return [$u, $client, $t];
    }

    public function test_guest_redirected(): void
    {
        $this->get('/beta/client')->assertRedirect();
    }

    public function test_active_member_sees_dashboard(): void
    {
        [$u, $client, $t] = $this->member('active');
        TenantContext::bypass(true);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id, 'client_id' => $client->id,
            'name' => 'حملة العميل', 'status' => 'active', 'budget_minor' => 1000000, 'currency' => 'SAR']);
        ContentItem::create(['tenant_id' => $t->id, 'client_id' => $client->id, 'campaign_id' => $cm->id,
            'type' => 'post', 'title' => 'منشور', 'status' => 'client_review']);
        TenantContext::reset();

        $this->actingAs($u)->get('/beta/client')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Dashboard')
                ->where('client.name', 'شركة العميل')
                ->where('stats.activeCampaigns', 1)
                ->where('pending.0.key', 'content')
                ->where('pending.0.count', 1)
                ->has('recent', 1)
                ->where('recent.0.name', 'حملة العميل'));
    }

    public function test_suspended_membership_blocked(): void
    {
        [$u] = $this->member('suspended');
        $this->actingAs($u)->get('/beta/client')->assertForbidden();
    }

    public function test_isolated_to_active_client(): void
    {
        [$u1] = $this->member('active');
        [, $c2, $t2] = $this->member('active');
        // مستخدم العميل 1 لا يرى حملات العميل 2
        TenantContext::bypass(true);
        Campaign::create(['tenant_id' => $t2->id, 'campaign_number' => 'CM-' . $t2->id, 'client_id' => $c2->id,
            'name' => 'حملة أخرى', 'status' => 'active', 'budget_minor' => 500000, 'currency' => 'SAR']);
        TenantContext::reset();
        $this->actingAs($u1)->get('/beta/client')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stats.activeCampaigns', 0)->has('recent', 0));
    }

    /* ===== حساب المنشأة (React) — ملف/فوترة/عناوين/إعدادات ===== */

    public function test_account_page_exposes_sections_and_abilities(): void
    {
        [$u] = $this->member();
        $this->actingAs($u)->get('/beta/client/account')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ClientPortal/Account')
                ->has('client')->has('addresses')->has('prefs')->has('sessions')
                ->where('can.editProfile', true));
    }

    /** الحقول غير الحسّاسة تُطبَّق مباشرة. */
    public function test_account_updates_plain_profile_fields(): void
    {
        [$u, $c] = $this->member();
        $this->actingAs($u)->post('/beta/client/account/profile', [
            'display_name' => 'شركة العميل', 'city' => 'الدمام', 'sector' => 'تجزئة',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $fresh = Client::find($c->id);
        TenantContext::reset();
        $this->assertSame('الدمام', $fresh->city);
    }

    /** الحقول النظامية لا تُطبَّق مباشرة — تتحوّل إلى طلب تغيير للمراجعة. */
    public function test_account_sensitive_fields_become_change_request(): void
    {
        [$u, $c] = $this->member();
        $this->actingAs($u)->post('/beta/client/account/profile', [
            'display_name' => 'شركة العميل', 'tax_number' => '399999999900003',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $fresh = Client::find($c->id);
        $pending = \App\Domain\CRM\Models\ClientProfileChangeRequest::where('client_id', $c->id)->count();
        TenantContext::reset();
        $this->assertNotSame('399999999900003', $fresh->tax_number, 'الحقل الحسّاس طُبّق مباشرة بلا مراجعة');
        $this->assertGreaterThan(0, $pending, 'لم يُنشأ طلب تغيير');
    }

    public function test_account_address_lifecycle(): void
    {
        [$u, $c] = $this->member();
        $this->actingAs($u)->post('/beta/client/account/addresses', [
            'type' => 'headquarters', 'city' => 'الرياض', 'street' => 'الملك فهد',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $a = \App\Domain\CRM\Models\ClientAddress::where('client_id', $c->id)->firstOrFail();
        TenantContext::reset();

        $this->actingAs($u)->post("/beta/client/account/addresses/{$a->id}/default")->assertRedirect();
        $this->actingAs($u)->post("/beta/client/account/addresses/{$a->id}/archive")->assertRedirect();
        TenantContext::bypass(true);
        $archived = \App\Domain\CRM\Models\ClientAddress::find($a->id);
        TenantContext::reset();
        $this->assertNotNull($archived->archived_at, 'لم يُؤرشَف العنوان');
        $this->assertFalse((bool) $archived->is_default, 'المؤرشف بقي افتراضيًا');

        $this->actingAs($u)->post("/beta/client/account/addresses/{$a->id}/restore")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertNull(\App\Domain\CRM\Models\ClientAddress::find($a->id)->archived_at);
        TenantContext::reset();
    }

    /** منع IDOR: عنوان عميل آخر لا يُعدَّل. */
    public function test_account_cannot_touch_another_clients_address(): void
    {
        [$uA] = $this->member();
        [, $cB, $tB] = $this->member();
        TenantContext::bypass(true);
        $aB = \App\Domain\CRM\Models\ClientAddress::create([
            'tenant_id' => $tB->id, 'client_id' => $cB->id, 'type' => 'billing', 'city' => 'جدة',
        ]);
        TenantContext::reset();

        $this->actingAs($uA)->post("/beta/client/account/addresses/{$aB->id}/archive")->assertNotFound();
    }

    /** تغيير كلمة المرور يتطلّب الحالية الصحيحة. */
    public function test_account_password_change_requires_current(): void
    {
        [$u] = $this->member();
        $this->actingAs($u)->post('/beta/client/account/settings/password', [
            'current_password' => 'wrong-one', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_account_password_change_succeeds_with_current(): void
    {
        [$u] = $this->member();
        $this->actingAs($u)->post('/beta/client/account/settings/password', [
            'current_password' => 'secret12', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPass123', $u->fresh()->password));
    }

    public function test_account_requires_client_session(): void
    {
        $this->get('/beta/client/account')->assertRedirect();
    }
}
