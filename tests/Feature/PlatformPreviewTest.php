<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Support\PlatformPreviewToken;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $res = $this->actingAs($owner)->get("/platform/preview/{$t->id}/agency/{$target->id}");
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

        $this->actingAs($owner)->get("/platform/preview/{$t->id}/agency/{$stranger->id}")->assertNotFound();
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
        [$target, $t] = $this->agencyTarget();
        $plain = User::create(['name' => 'P', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        // مجموعة /platform محميّة بـ platform_owner ⇒ غير المالك 403.
        $this->actingAs($plain)->get("/platform/preview/{$t->id}/agency/{$target->id}")->assertForbidden();
    }
}
