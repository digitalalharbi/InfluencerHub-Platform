<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyInvitation, ExternalAgencyMember};
use App\Domain\Partners\Services\{ExternalAgencyWorkflowService, InvitePartnerMember};
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Phase 5 — قبول دعوة الشريك (عام مُحصّن): إنشاء حساب + تفعيل عضوية + دخول البوابة. */
class PartnerInvitationTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** يعيد [tenant, agency(approved), inviter, rawToken] لبريد معيّن. */
    private function invitedAgency(string $email = 'invitee@ex.com'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $inviter = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $inviter->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();
        $wf = app(ExternalAgencyWorkflowService::class);
        $a = $wf->createDraft($t->id, ['name' => 'شريك'], $inviter->id);
        $a = $wf->approve($wf->startReview($wf->submit($a, $inviter->id), $inviter->id), $inviter->id);
        [$inv, $raw] = app(InvitePartnerMember::class)->handle($a, $email, 'partner_admin', $inviter);
        return [$t, $a, $inviter, $raw];
    }

    public function test_show_valid_invitation(): void
    {
        [$t, $a, $inviter, $raw] = $this->invitedAgency();
        $this->get("/partner/invite/{$raw}")->assertOk()->assertSee('قبول دعوة الشريك')->assertSee('invitee@ex.com');
    }

    public function test_invalid_token_shows_error(): void
    {
        $this->invitedAgency();
        $this->get('/partner/invite/' . Str::random(48))->assertOk()->assertSee('دعوة غير صالحة');
    }

    public function test_accept_creates_account_and_activates_membership(): void
    {
        [$t, $a, $inviter, $raw] = $this->invitedAgency();
        $this->post("/partner/invite/{$raw}", [
            'name' => 'الشريك الجديد', 'password' => 'PartnerPass1', 'password_confirmation' => 'PartnerPass1',
        ])->assertRedirect('/partner/dashboard');

        TenantContext::bypass(true);
        $user = User::where('email', 'invitee@ex.com')->first();
        $member = ExternalAgencyMember::where('user_id', $user->id)->first();
        $inv = ExternalAgencyInvitation::where('external_agency_id', $a->id)->first();
        TenantContext::reset();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('PartnerPass1', $user->password));
        $this->assertEquals('active', $member->status);
        $this->assertNotNull($inv->accepted_at);           // الدعوة استُهلكت
        $this->assertAuthenticatedAs($user);               // سُجّل دخوله
    }

    public function test_accept_is_single_use(): void
    {
        [$t, $a, $inviter, $raw] = $this->invitedAgency();
        $this->post("/partner/invite/{$raw}", ['name' => 'X', 'password' => 'PartnerPass1', 'password_confirmation' => 'PartnerPass1'])->assertRedirect();
        // إعادة الاستخدام تفشل
        $this->post('/partner/logout');
        $this->post("/partner/invite/{$raw}", ['name' => 'Y', 'password' => 'PartnerPass1', 'password_confirmation' => 'PartnerPass1'])
            ->assertSessionHasErrors('invite');
    }

    public function test_weak_password_rejected(): void
    {
        [$t, $a, $inviter, $raw] = $this->invitedAgency();
        $this->post("/partner/invite/{$raw}", ['name' => 'X', 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertSessionHasErrors('password');
    }

    public function test_existing_email_must_login_first(): void
    {
        [$t, $a, $inviter, $raw] = $this->invitedAgency('taken@ex.com');
        TenantContext::bypass(true);
        User::create(['name' => 'موجود', 'email' => 'taken@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        TenantContext::reset();
        $this->post("/partner/invite/{$raw}", ['name' => 'X', 'password' => 'PartnerPass1', 'password_confirmation' => 'PartnerPass1'])
            ->assertSessionHasErrors('invite');
    }

    public function test_accepted_partner_can_reach_dashboard(): void
    {
        [$t, $a, $inviter, $raw] = $this->invitedAgency();
        $this->post("/partner/invite/{$raw}", ['name' => 'شريك', 'password' => 'PartnerPass1', 'password_confirmation' => 'PartnerPass1']);
        $this->get('/partner/dashboard')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('PartnerPortal/Dashboard')->where('base', '/partner'));
    }
}
