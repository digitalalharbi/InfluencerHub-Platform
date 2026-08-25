<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Support\PlatformPreviewToken;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Middleware\PortalPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * معاينة بوّابة للقراءة فقط لمالك المنصّة (§P3). مصفوفة الأمان: منحة مزوّرة/منتهية/
 * ببوّابة مختلفة/بمالك مختلف/بمستأجر عابر/بكيان خاطئ ⇒ محظورة؛ الكتابة محظورة؛ لا
 * كتابة جلسة (عزل متعدّد النوافذ)؛ الهوية مزدوجة (الفاعل المدقَّق = المالك). غير المالك
 * حاملًا منحة ⇒ محظور. البدء/الإنهاء مُدقَّقان بالمالك فاعلًا.
 */
class PlatformPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // طلبات HTTP تترك TenantContext الساكن متّسخًا؛ بلا تصفير يسرّب BelongsToTenant
        // مستأجرًا قديمًا في create ⇒ انتهاك مفتاح أجنبي بين الاختبارات.
        TenantContext::reset();
    }

    private function owner(): User
    {
        // is_system_admin/is_platform_owner محروسان من الإسناد الجماعي (بتصميم) — forceFill.
        $u = User::create(['name' => 'Platform Owner', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->forceFill(['is_system_admin' => true, 'is_platform_owner' => true])->save();
        return $u;
    }

    private function tenantOrg(): array
    {
        $t = Tenant::create(['name' => 'T', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'Org', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        return [$t, $org];
    }

    /** مستخدم وكالة حقيقي (عضوية بدور غير-بوّابة). @return array{User,Tenant,Organization} */
    private function agencyTarget(): array
    {
        [$t, $org] = $this->tenantOrg();
        $u = User::create(['name' => 'Agency User', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        TenantContext::withBypass(fn () => OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']));
        return [$u, $t, $org];
    }

    private function token(User $owner, User $target, Tenant $t, string $portal, int $entity, ?int $org, ?int $nowTs = null): string
    {
        return PlatformPreviewToken::issue((int) $owner->id, (int) $target->id, (int) $t->id, $portal, $entity, $org, $nowTs ?? now()->timestamp);
    }

    // ── التحقّق من المنحة ───────────────────────────────────────────────

    public function test_non_owner_with_preview_token_is_forbidden(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();
        $token = $this->token($owner, $target, $t, 'agency', $org->id, $org->id);

        // مستخدم عاديّ يحمل منحة صالحة ⇒ 403 (المنحة لا تُرقّي أحدًا).
        $intruder = User::create(['name' => 'X', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $this->actingAs($intruder)->get("/beta?_pv={$token}")->assertForbidden();
    }

    public function test_forged_token_is_forbidden(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();
        $token = $this->token($owner, $target, $t, 'agency', $org->id, $org->id) . 'tampered';

        $this->actingAs($owner)->get("/beta?_pv={$token}")->assertForbidden();
    }

    public function test_expired_token_is_forbidden(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();
        // أُصدرت قبل ساعة ⇒ exp في الماضي.
        $token = $this->token($owner, $target, $t, 'agency', $org->id, $org->id, now()->timestamp - 3600);

        $this->actingAs($owner)->get("/beta?_pv={$token}")->assertForbidden();
    }

    public function test_owner_mismatch_token_is_forbidden(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $ownerA = $this->owner();
        $ownerB = $this->owner();
        // منحة صادرة باسم المالك B لكن يستعملها المالك A ⇒ 403.
        $token = $this->token($ownerB, $target, $t, 'agency', $org->id, $org->id);

        $this->actingAs($ownerA)->get("/beta?_pv={$token}")->assertForbidden();
    }

    public function test_portal_mismatch_is_forbidden(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();
        // منحة بوّابة العميل تُستعمل على مسار الوكالة (/beta = platform_preview:agency) ⇒ 403.
        $token = $this->token($owner, $target, $t, 'client', $org->id, null);

        $this->actingAs($owner)->get("/beta?_pv={$token}")->assertForbidden();
    }

    public function test_cross_tenant_or_wrong_entity_tuple_is_forbidden(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        [$tOther] = $this->tenantOrg();
        $owner = $this->owner();

        // كيان خاطئ (لا رابط للمستخدم) ⇒ isContextEligible=false ⇒ 403.
        $bad = $this->token($owner, $target, $t, 'agency', $org->id + 9999, $org->id + 9999);
        $this->actingAs($owner)->get("/beta?_pv={$bad}")->assertForbidden();

        // مستأجر عابر ⇒ 403.
        $cross = $this->token($owner, $target, $tOther, 'agency', $org->id, $org->id);
        $this->actingAs($owner)->get("/beta?_pv={$cross}")->assertForbidden();
    }

    public function test_inactive_target_is_forbidden(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $target->forceFill(['is_active' => false])->save();
        $owner = $this->owner();
        $token = $this->token($owner, $target, $t, 'agency', $org->id, $org->id);

        $this->actingAs($owner)->get("/beta?_pv={$token}")->assertForbidden();
    }

    // ── القراءة فقط ────────────────────────────────────────────────────

    public function test_write_method_during_preview_is_forbidden(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();
        $token = $this->token($owner, $target, $t, 'agency', $org->id, $org->id);

        // POST داخل مساحة الوكالة أثناء المعاينة ⇒ 403 قبل أي تنفيذ (§9).
        $this->actingAs($owner)->post("/app/exports/schedules?_pv={$token}", [])->assertForbidden();
    }

    // ── المسار السعيد + الهوية المزدوجة ─────────────────────────────────

    public function test_owner_preview_renders_as_target_with_dual_identity(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();
        $token = $this->token($owner, $target, $t, 'agency', $org->id, $org->id);

        $this->actingAs($owner)->get("/beta?_pv={$token}")
            ->assertSuccessful()
            ->assertInertia(fn (Assert $p) => $p
                ->where('preview.active', true)
                ->where('preview.targetName', $target->name)
                ->where('preview.portal', 'agency')
                ->where('auth.user.name', $target->name));   // الفاعل الظاهر = الهدف
    }

    // ── عزل الجلسة (متعدّد النوافذ) ─────────────────────────────────────

    public function test_client_preview_does_not_write_session_active_client(): void
    {
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        $u = User::create(['name' => 'Client User', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $client = TenantContext::withBypass(function () use ($t, $u) {
            $c = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-P', 'display_name' => 'عميل', 'status' => 'active']);
            ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active']);
            return $c;
        });
        $token = $this->token($owner, $u, $t, 'client', $client->id, null);

        $this->actingAs($owner)->get("/client?_pv={$token}");

        // الحدّ الحرِج: المعاينة لا تكتب active_client_id في الجلسة المشتركة.
        $this->assertNull(session('active_client_id'));
    }

    // ── البدء/الإنهاء والتدقيق ──────────────────────────────────────────

    public function test_start_redirects_into_portal_and_audits_owner_as_actor(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();

        $res = $this->actingAs($owner)->get("/platform/preview/{$t->id}/agency/{$target->id}/{$org->id}?organization={$org->id}");
        $res->assertRedirect();
        $this->assertStringContainsString('/app?_pv=', $res->headers->get('Location'));

        $log = AuditLog::withoutGlobalScopes()->where('action', 'platform.preview.start')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $owner->id, (int) $log->user_id);   // الفاعل = المالك
    }

    public function test_start_with_ineligible_user_is_not_available(): void
    {
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        // مستخدم بلا أي سياق في هذا المستأجر ⇒ 404 NOT_AVAILABLE.
        $stranger = User::create(['name' => 'S', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        $this->actingAs($owner)->get("/platform/preview/{$t->id}/agency/{$stranger->id}/999?organization=999")->assertNotFound();
    }

    public function test_exit_audits_and_returns_to_platform(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();
        $token = $this->token($owner, $target, $t, 'agency', $org->id, $org->id);

        $this->actingAs($owner)->get('/platform/preview/exit?token=' . $token)
            ->assertRedirect('/platform');

        $this->assertNotNull(AuditLog::withoutGlobalScopes()->where('action', 'platform.preview.exit')->first());
    }

    public function test_non_owner_cannot_start_preview(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $plain = User::create(['name' => 'P', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        // مجموعة /platform محميّة بـ platform_owner ⇒ غير المالك 403.
        $this->actingAs($plain)->get("/platform/preview/{$t->id}/agency/{$target->id}/{$org->id}?organization={$org->id}")->assertForbidden();
    }

    // ── §1 السياق الدقيق: لا first() نيابةً عن المالك ───────────────────

    private function twoClients(Tenant $t, User $u): array
    {
        return TenantContext::withBypass(function () use ($t, $u) {
            $a = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-A', 'display_name' => 'Client A', 'status' => 'active']);
            $b = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-B', 'display_name' => 'Client B', 'status' => 'active']);
            foreach ([$a, $b] as $c) {
                ClientMember::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'user_id' => $u->id, 'role' => 'client_admin', 'status' => 'active']);
            }
            return [$a, $b];
        });
    }

    public function test_same_user_two_clients_each_button_opens_the_correct_client(): void
    {
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        $u = User::create(['name' => 'Dual Client', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        [$a, $b] = $this->twoClients($t, $u);

        foreach ([[$a, 'Client A'], [$b, 'Client B']] as [$client, $name]) {
            $res = $this->actingAs($owner)->get("/platform/preview/{$t->id}/client/{$u->id}/{$client->id}");
            $res->assertRedirect();
            $loc = $res->headers->get('Location');
            // المنحة تحمل الكيان المختار بالضبط — لا الأوّل.
            $claims = PlatformPreviewToken::verify(Str::after($loc, '_pv='), now()->timestamp);
            $this->assertSame((int) $client->id, $claims['entity']);
            // وعند فتح البوّابة يرى العميل الصحيح فعلًا.
            $this->actingAs($owner)->get($loc)->assertSuccessful()
                ->assertInertia(fn (Assert $p) => $p->where('client.name', $name));
        }
    }

    public function test_same_user_two_orgs_each_start_carries_the_correct_org(): void
    {
        $t = Tenant::create(['name' => 'MultiOrg', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $owner = $this->owner();
        $u = User::create(['name' => 'Dual Org', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        [$oa, $ob] = TenantContext::withBypass(function () use ($t, $u) {
            $oa = Organization::create(['tenant_id' => $t->id, 'name' => 'Org A', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $ob = Organization::create(['tenant_id' => $t->id, 'name' => 'Org B', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            foreach ([$oa, $ob] as $o) {
                OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $o->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
            }
            return [$oa, $ob];
        });

        foreach ([$oa, $ob] as $org) {
            $res = $this->actingAs($owner)->get("/platform/preview/{$t->id}/agency/{$u->id}/{$org->id}?organization={$org->id}");
            $res->assertRedirect();
            $claims = PlatformPreviewToken::verify(Str::after($res->headers->get('Location'), '_pv='), now()->timestamp);
            $this->assertSame((int) $org->id, $claims['entity']);
            $this->assertSame((int) $org->id, $claims['org']);   // الرباعية الدقيقة كما اختار المالك
        }

        // وعند فتح الوكالة بمنحة المؤسسة A يرى مساحة عمل A لا B.
        $token = $this->token($owner, $u, $t, 'agency', $oa->id, $oa->id);
        $this->actingAs($owner)->get("/beta?_pv={$token}")->assertSuccessful()
            ->assertInertia(fn (Assert $p) => $p->where('workspace', 'Org A'));
    }

    public function test_modified_entity_or_org_in_start_is_blocked(): void
    {
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        $u = User::create(['name' => 'C', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        [$a] = $this->twoClients($t, $u);

        // كيان لا يخصّ المستخدم ⇒ 404. مؤسسة مزوّرة على بوّابة عميل ⇒ 404.
        $this->actingAs($owner)->get("/platform/preview/{$t->id}/client/{$u->id}/" . ($a->id + 9999))->assertNotFound();
        $this->actingAs($owner)->get("/platform/preview/{$t->id}/client/{$u->id}/{$a->id}?organization=4242")->assertNotFound();
    }

    // ── §2 استعادة هوية المالك بعد التنفيذ مُحكَمة النطاق ────────────────

    public function test_owner_identity_is_restored_after_preview_middleware_returns(): void
    {
        [$target, $t, $org] = $this->agencyTarget();
        $owner = $this->owner();
        $token = $this->token($owner, $target, $t, 'agency', $org->id, $org->id);

        $req = Request::create('/beta', 'GET', ['_pv' => $token]);
        $req->setUserResolver(fn () => $owner);
        Auth::setUser($owner);

        $insideId = null;
        (new PortalPreview())->handle($req, function () use (&$insideId) {
            $insideId = Auth::id();   // داخل المتحكّم: الفاعل = الهدف
            return new \Illuminate\Http\Response('ok');
        }, 'agency');

        $this->assertSame((int) $target->id, $insideId);            // الهوية الفاعلة = الهدف
        $this->assertSame((int) $owner->id, (int) Auth::id());      // وبعد العودة: المالك مُستعاد
    }

    // ── §3 تكافؤ السياق: عاديّ مقابل معاينة ─────────────────────────────

    public function test_client_context_parity_normal_vs_preview_and_zero_session_writes(): void
    {
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        $u = User::create(['name' => 'Parity Client', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        [$a] = $this->twoClients($t, $u);

        // عاديّ: المستخدم الحقيقي يرى عميله، وتُكتب الجلسة.
        $this->actingAs($u)->get('/client')->assertSuccessful()
            ->assertInertia(fn (Assert $p) => $p->where('client.name', 'Client A'));
        $this->assertSame((int) $a->id, session('active_client_id'));

        session()->forget('active_client_id');

        // معاينة: نفس العميل، لكن بلا كتابة جلسة، وclientMembership مضبوط (صفحة الحساب
        // تقرأ الدور فتفشل لو كان null) ⇒ 200 يثبت التكافؤ.
        $res = $this->actingAs($owner)->get("/platform/preview/{$t->id}/client/{$u->id}/{$a->id}");
        $loc = $res->headers->get('Location');
        $this->actingAs($owner)->get($loc)->assertSuccessful()
            ->assertInertia(fn (Assert $p) => $p->where('client.name', 'Client A'));
        $this->assertNull(session('active_client_id'));   // صفر كتابة جلسة

        $token = Str::after($loc, '_pv=');
        $this->actingAs($owner)->get("/client/account?_pv={$token}")->assertSuccessful();   // clientMembership غير null
    }

    // ── §4 حظر أي تحوّر من داخل معاينة + استمرار جلسة المالك ─────────────

    public function test_preview_logout_and_switch_and_account_mutations_are_blocked(): void
    {
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        $u = User::create(['name' => 'Blockee', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        [$a] = $this->twoClients($t, $u);
        $token = $this->token($owner, $u, $t, 'client', $a->id, null);

        // خروج/تبديل/تغيير كلمة مرور من داخل معاينة ⇒ 403 قبل أي تحوّر — الحارس العالميّ.
        $this->actingAs($owner)->post("/client/logout?_pv={$token}")->assertForbidden();
        $this->actingAs($owner)->post("/client/switch?_pv={$token}", ['client_id' => $a->id])->assertForbidden();
        $this->actingAs($owner)->post("/logout?_pv={$token}")->assertForbidden();

        // وجلسة المالك سليمة: مسار مالك-فقط لا يزال 200 (المتحكّم لم يُنفَّذ فلم يُسجَّل خروج).
        $this->actingAs($owner)->get('/platform')->assertSuccessful();
    }

    public function test_partner_context_parity_and_zero_session_writes(): void
    {
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        $u = User::create(['name' => 'Partner', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $agency = TenantContext::withBypass(function () use ($t, $u) {
            $a = \App\Domain\Partners\Models\ExternalAgency::create(['tenant_id' => $t->id, 'agency_number' => 'EA-P', 'name' => 'شريك', 'status' => 'approved']);
            \App\Domain\Partners\Models\ExternalAgencyMember::create(['tenant_id' => $t->id, 'external_agency_id' => $a->id, 'user_id' => $u->id, 'role' => 'external_agency_admin', 'status' => 'active']);
            return $a;
        });

        // عاديّ: تُكتب الجلسة.
        $this->actingAs($u)->get('/partner')->assertSuccessful();
        $this->assertSame((int) $agency->id, session('active_agency_id'));
        session()->forget('active_agency_id');

        // معاينة: نفس البوّابة، بلا كتابة active_agency_id.
        $token = $this->token($owner, $u, $t, 'partner', $agency->id, null);
        $this->actingAs($owner)->get("/partner?_pv={$token}")->assertSuccessful()
            ->assertInertia(fn (Assert $p) => $p->where('preview.active', true)->where('auth.user.name', $u->name));
        $this->assertNull(session('active_agency_id'));
    }

    public function test_creator_context_parity_normal_vs_preview(): void
    {
        // القاعدة القانونية الوحيدة portalEligible مُبدَّلة بمزيّف (يستهلكها الحارس
        // والحلّال وisContextEligible معًا) — فيتطابق العاديّ والمعاينة.
        $this->mock(\App\Domain\Creators\Services\CreatorEntitlementService::class, function ($m) {
            $m->shouldReceive('portalEligible')->andReturn(true);
        });
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        $u = User::create(['name' => 'Creator', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $creator = TenantContext::withBypass(fn () => \App\Domain\Creators\Models\Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-P', 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active', 'user_id' => $u->id]));

        $this->actingAs($u)->get('/creator')->assertSuccessful();   // عاديّ

        $token = $this->token($owner, $u, $t, 'creator', $creator->id, null);
        $this->actingAs($owner)->get("/creator?_pv={$token}")->assertSuccessful()   // معاينة كالهدف
            ->assertInertia(fn (Assert $p) => $p->where('preview.active', true)->where('auth.user.name', $u->name));
    }

    public function test_preview_navigation_continuity_keeps_pv_active(): void
    {
        [$t] = $this->tenantOrg();
        $owner = $this->owner();
        $u = User::create(['name' => 'Nav', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        [$a] = $this->twoClients($t, $u);
        $token = $this->token($owner, $u, $t, 'client', $a->id, null);

        // تنقّل: لوحة → حملات → حساب، والمعاينة نشطة في كلٍّ (preview.active=true).
        foreach (['/client', '/client/campaigns', '/client/account'] as $path) {
            $this->actingAs($owner)->get("{$path}?_pv={$token}")->assertSuccessful()
                ->assertInertia(fn (Assert $p) => $p->where('preview.active', true)->where('preview.portal', 'client'));
        }
    }
}
