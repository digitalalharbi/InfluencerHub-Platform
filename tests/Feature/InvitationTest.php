<?php
namespace Tests\Feature;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Actions\AcceptInvitation;
use App\Domain\Tenancy\Models\{Tenant, Organization, Invitation, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class InvitationTest extends TestCase {
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }
    private function invite(string $status, $expires): Invitation {
        $t = Tenant::create(['name'=>'t','slug'=>Str::random(6),'deployment_mode'=>'saas','status'=>'active']);
        TenantContext::bypass(true);
        $o = Organization::create(['tenant_id'=>$t->id,'name'=>'o','slug'=>Str::random(6),'type'=>'agency']);
        $inv = Invitation::create(['tenant_id'=>$t->id,'organization_id'=>$o->id,'email'=>'x@ex.com','role'=>'agency_employee','token'=>Str::random(40),'status'=>$status,'expires_at'=>$expires]);
        TenantContext::reset();
        return $inv;
    }
    private function user(): User { return User::create(['name'=>'U','email'=>Str::random(6).'@ex.com','password'=>'Secret123!','is_active'=>true]); }

    public function test_valid_invitation_creates_active_membership(): void {
        $inv = $this->invite('pending', now()->addDays(3)); $u = $this->user();
        $m = (new AcceptInvitation)->handle($inv, $u);
        $this->assertEquals('active', $m->status);
        $this->assertEquals('accepted', $inv->fresh()->status);
    }
    public function test_expired_invitation_is_rejected(): void {
        $inv = $this->invite('pending', now()->subDay()); $u = $this->user();
        $this->expectException(\RuntimeException::class);
        (new AcceptInvitation)->handle($inv, $u);
    }
    public function test_already_accepted_invitation_is_rejected(): void {
        $inv = $this->invite('accepted', now()->addDays(3)); $u = $this->user();
        $this->expectException(\RuntimeException::class);
        (new AcceptInvitation)->handle($inv, $u);
    }
    public function test_revoked_invitation_is_rejected(): void {
        $inv = $this->invite('revoked', now()->addDays(3)); $u = $this->user();
        $this->expectException(\RuntimeException::class);
        (new AcceptInvitation)->handle($inv, $u);
    }
}
