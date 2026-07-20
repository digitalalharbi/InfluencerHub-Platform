<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\{Creator, CreatorInvitation};
use App\Domain\Creators\Services\CreatorInvitationService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * دعوة صانع المحتوى وربط حسابه.
 *
 * العيب الذي تغلقه: المسار الوحيد لإنشاء حساب صانع محتوى كان قبول طلب انضمام
 * عامّ، فمن تضيفه الوكالة بنفسها لا يستطيع الدخول أبدًا — 166 من 168.
 */
class CreatorInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:Tenant,1:Organization,2:User,3:Creator} */
    private function ctx(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $ag = User::create(['name' => 'A', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $ag->id, 'role' => 'agency_admin', 'status' => 'active']);
        // بوابة المبدع مشروطة باستحقاق الخطة
        $plan = \App\Domain\Billing\Models\Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = \App\Domain\Billing\Models\PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        \App\Domain\Billing\Models\PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_portal.enabled', 'value' => 1]);
        (new \App\Domain\Billing\Actions\CreateSubscription)->handle($org, $pv);
        // سجلّ حقيقي غير مرتبط — لا حساب يدوي
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . Str::random(5), 'type' => 'influencer',
            'display_name' => 'ريم', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $org, $ag, $cr];
    }

    private function svc(): CreatorInvitationService { return app(CreatorInvitationService::class); }
    private function fresh($m) { TenantContext::bypass(true); $f = $m->fresh(); TenantContext::reset(); return $f; }

    // ===== الرحلة الكاملة =====

    /** دعوة ← تحقّق بريد ← تحقّق جوال ← كلمة مرور ← ربط ← بوابة مفعّلة. */
    public function test_the_full_journey_links_a_user_to_an_existing_creator(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $this->assertNull($cr->user_id, 'السجلّ يجب أن يبدأ بلا حساب');

        [$inv, $raw] = $this->svc()->invite($cr, 'reem@ex.com', '+966500000001', $ag);
        $inv = $this->fresh($inv);

        $this->svc()->verifyEmail($inv, $inv->email_code);
        $inv = $this->fresh($inv);
        $this->svc()->verifyPhone($inv, $inv->phone_code);
        $inv = $this->fresh($inv);

        $user = $this->svc()->accept($inv, 'StrongPass1');

        $cr = $this->fresh($cr);
        $this->assertSame($user->id, (int) $cr->user_id, 'لم يُربط الحساب بالسجلّ');
        $this->assertNotNull($this->fresh($inv)->accepted_at);

        // البوابة مفعّلة: عضوية بدور مشتقّ من القدرات
        TenantContext::bypass(true);
        $this->assertDatabaseHas('organization_memberships', [
            'tenant_id' => $t->id, 'user_id' => $user->id, 'role' => 'influencer', 'status' => 'active',
        ]);
        TenantContext::reset();

        // ويستطيع الدخول فعلًا
        $this->assertTrue(Hash::check('StrongPass1', $user->password));
    }

    /** الرمز الخام لا يُخزَّن — Hash فقط. */
    public function test_the_raw_token_is_never_stored(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        [$inv, $raw] = $this->svc()->invite($cr, 'reem@ex.com', null, $ag);

        TenantContext::bypass(true);
        $this->assertDatabaseMissing('creator_invitations', ['token_hash' => $raw]);
        $this->assertDatabaseHas('creator_invitations', ['token_hash' => hash('sha256', $raw)]);
        TenantContext::reset();
        $this->assertNotNull($this->svc()->findByToken($raw));
    }

    // ===== منع التكرار والاستعمال المزدوج =====

    /** الدعوة تُستخدم مرّة واحدة. */
    public function test_an_accepted_invitation_cannot_be_used_twice(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $inv = $this->verified($cr, $ag);
        $this->svc()->accept($inv, 'StrongPass1');

        $this->expectException(\RuntimeException::class);
        $this->svc()->accept($this->fresh($inv), 'Another1');
    }

    /** سجلّ مرتبط لا يُدعى ثانيةً — وهو ما يمنع ازدواج الحسابات. */
    public function test_a_linked_creator_cannot_be_invited_again(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $inv = $this->verified($cr, $ag);
        $this->svc()->accept($inv, 'StrongPass1');

        $this->expectException(\RuntimeException::class);
        $this->svc()->invite($this->fresh($cr), 'other@ex.com', null, $ag);
    }

    /** دعوتان حيّتان لسجلّ واحد = رابطان صالحان — يُمنع. */
    public function test_a_second_live_invitation_is_refused(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $this->svc()->invite($cr, 'reem@ex.com', null, $ag);

        $this->expectException(\RuntimeException::class);
        $this->svc()->invite($this->fresh($cr), 'reem@ex.com', null, $ag);
    }

    // ===== الصلاحية والإلغاء وإعادة الإرسال =====

    /** المنتهية تُرفض بسبب مفهوم لا برسالة مبهمة. */
    public function test_an_expired_invitation_is_refused_with_its_reason(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $inv = $this->verified($cr, $ag);
        TenantContext::bypass(true);
        $inv->update(['expires_at' => now()->subDay()]);
        TenantContext::reset();

        try {
            $this->svc()->accept($this->fresh($inv), 'StrongPass1');
            $this->fail('قُبلت دعوة منتهية');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('انتهت صلاحية', $e->getMessage());
        }
    }

    public function test_a_revoked_invitation_is_refused(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $inv = $this->verified($cr, $ag);
        $this->svc()->revoke($inv, $ag, 'أُرسلت لعنوان خاطئ');

        $this->expectException(\RuntimeException::class);
        $this->svc()->accept($this->fresh($inv), 'StrongPass1');
    }

    /** إعادة الإرسال تُبطل الرمز القديم — وإلا بقي رابطان صالحين. */
    public function test_resending_invalidates_the_previous_token(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        [$inv, $first] = $this->svc()->invite($cr, 'reem@ex.com', null, $ag);
        TenantContext::bypass(true);
        $inv->update(['last_sent_at' => now()->subMinutes(10)]);
        TenantContext::reset();

        [, $second] = $this->svc()->resend($this->fresh($inv), $ag);

        $this->assertNull($this->svc()->findByToken($first), 'الرمز القديم بقي صالحًا');
        $this->assertNotNull($this->svc()->findByToken($second));
    }

    /** حدّ إعادة الإرسال يمنع استعمالها قناة إزعاج. */
    public function test_resending_is_rate_limited(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        [$inv] = $this->svc()->invite($cr, 'reem@ex.com', null, $ag);

        // فورًا بعد الإرسال: مهلة تهدئة
        try {
            $this->svc()->resend($this->fresh($inv), $ag);
            $this->fail('أُعيد الإرسال بلا تهدئة');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('انتظر', $e->getMessage());
        }

        // وبعد بلوغ الحدّ الأعلى
        TenantContext::bypass(true);
        $inv->update(['sent_count' => CreatorInvitationService::MAX_SENDS, 'last_sent_at' => now()->subHour()]);
        TenantContext::reset();

        $this->expectException(\RuntimeException::class);
        $this->svc()->resend($this->fresh($inv), $ag);
    }

    // ===== التحقّق شرط =====

    /** كلمة المرور لا تُنشأ قبل تحقّق البريد والجوال. */
    public function test_password_cannot_be_set_before_both_verifications(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        [$inv] = $this->svc()->invite($cr, 'reem@ex.com', '+966500000001', $ag);
        $inv = $this->fresh($inv);
        $this->svc()->verifyEmail($inv, $inv->email_code);   // الجوال لم يُتحقّق

        $this->expectException(\RuntimeException::class);
        $this->svc()->accept($this->fresh($inv), 'StrongPass1');
    }

    public function test_a_wrong_verification_code_is_refused(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        [$inv] = $this->svc()->invite($cr, 'reem@ex.com', null, $ag);

        $this->expectException(\RuntimeException::class);
        $this->svc()->verifyEmail($this->fresh($inv), '000000');
    }

    // ===== العزل والأثر =====

    /** الدعوة لا تعبر المستأجرين. */
    public function test_invitations_are_tenant_isolated(): void
    {
        [$t1, , $ag1, $cr1] = $this->ctx();
        $this->svc()->invite($cr1, 'a@ex.com', null, $ag1);
        [$t2] = $this->ctx();

        TenantContext::set($t2->id);
        $this->assertSame(0, CreatorInvitation::count(), 'تسرّبت دعوة بين المستأجرين');
        TenantContext::reset();
    }

    /** كل خطوة تترك أثرًا. */
    public function test_the_journey_is_audited(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $inv = $this->verified($cr, $ag);
        $this->svc()->accept($inv, 'StrongPass1');

        foreach (['creator_invitation.sent', 'creator_invitation.accepted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['tenant_id' => $t->id, 'action' => $action]);
        }
    }

    /** وقبول الدعوة يُبلَّغ به من أرسلها. */
    public function test_acceptance_notifies_the_inviter(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $inv = $this->verified($cr, $ag);
        $this->svc()->accept($inv, 'StrongPass1');

        TenantContext::bypass(true);
        $n = \App\Domain\Communications\Models\Notification::where('tenant_id', $t->id)
            ->where('user_id', $ag->id)->where('type', 'creator_invitation.accepted')->first();
        TenantContext::reset();

        $this->assertNotNull($n, 'فُعِّلت البوابة ولم يعلم بها من دعا');
    }

    /** دعوة مُتحقَّق منها بالكامل وجاهزة للقبول. */
    private function verified(Creator $cr, User $ag): CreatorInvitation
    {
        [$inv] = $this->svc()->invite($cr, 'reem@ex.com', '+966500000001', $ag);
        $inv = $this->fresh($inv);
        $this->svc()->verifyEmail($inv, $inv->email_code);
        $inv = $this->fresh($inv);
        $this->svc()->verifyPhone($inv, $inv->phone_code);

        return $this->fresh($inv);
    }

    // ===== طبقة HTTP: الرحلة العامة =====

    /** الرحلة كاملة عبر HTTP على سجلّ حقيقي — تنتهي بجلسة مبدع فعّالة. */
    public function test_the_public_journey_works_end_to_end_over_http(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        [$inv, $raw] = $this->svc()->invite($cr, 'reem@ex.com', '+966500000001', $ag);
        $inv = $this->fresh($inv);

        $this->get("/creator/invitation/{$raw}")->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
                ->component('Public/InvitationAccept')
                ->where('emailVerified', false)->where('needsPhone', true));

        $this->post("/creator/invitation/{$raw}/verify-email", ['code' => $inv->email_code])->assertRedirect();
        $this->post("/creator/invitation/{$raw}/verify-phone", ['code' => $this->fresh($inv)->phone_code])->assertRedirect();
        $this->post("/creator/invitation/{$raw}/accept",
            ['password' => 'StrongPass1', 'password_confirmation' => 'StrongPass1'])
            ->assertRedirect('/creator/dashboard');

        $this->assertNotNull($this->fresh($cr)->user_id, 'لم يُربط الحساب');
        $this->assertAuthenticated();
    }

    /** رابط مجهول أو مُستهلَك يعرض صفحة سبب لا خطأ خام. */
    public function test_an_unusable_link_explains_itself(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();

        $this->get('/creator/invitation/' . Str::random(48))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p->component('Public/InvitationInvalid'));

        [$inv, $raw] = $this->svc()->invite($cr, 'reem@ex.com', null, $ag);
        $this->svc()->revoke($this->fresh($inv), $ag);

        $this->get("/creator/invitation/{$raw}")->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
                ->component('Public/InvitationInvalid')
                ->where('reason', fn ($r) => str_contains($r, 'أُلغيت')));
    }

    /** تخمين الرمز محدود بالمعدّل — 6 خانات بلا حدّ تُستنفَد. */
    public function test_verification_codes_are_rate_limited(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        [, $raw] = $this->svc()->invite($cr, 'reem@ex.com', null, $ag);

        for ($i = 0; $i < 6; $i++) {
            $this->post("/creator/invitation/{$raw}/verify-email", ['code' => '000000']);
        }

        $this->post("/creator/invitation/{$raw}/verify-email", ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'محاولات كثيرة — انتظر دقيقة ثم أعِد المحاولة.']);
    }

    /** وكالة أخرى لا تدعو صانع محتوى ليس في مساحتها. */
    public function test_an_agent_cannot_invite_a_creator_of_another_tenant(): void
    {
        [$t1, , , $cr1] = $this->ctx();
        [, , $ag2] = $this->ctx();

        $this->actingAs($ag2)->post("/app/creators/{$cr1->id}/invite", ['email' => 'x@ex.com'])
            ->assertNotFound();

        TenantContext::bypass(true);
        $this->assertSame(0, CreatorInvitation::where('creator_id', $cr1->id)->count());
        TenantContext::reset();
    }

    /** إلغاء الدعوة من واجهة الوكالة يُثبِت فعلًا. */
    public function test_agency_can_revoke_over_http(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        [$inv] = $this->svc()->invite($cr, 'reem@ex.com', null, $ag);

        $this->actingAs($ag)->post("/app/creator-invitations/{$inv->id}/revoke")
            ->assertRedirect();

        $this->assertNotNull($this->fresh($inv)->revoked_at, 'لم يُثبَت الإلغاء');
    }

    /**
     * رابط الدعوة يُعرض مرّة واحدة بعد الإنشاء.
     *
     * الرمز لا يُخزَّن خامًا، فإن لم يصل الواجهة في تلك اللحظة ضاع نهائيًّا
     * ولم يبقَ للوكالة سبيل لإرساله — الدعوة تُنشأ ولا تُستعمل.
     */
    public function test_the_one_time_link_reaches_the_page_after_inviting(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();

        $this->actingAs($ag)
            ->post("/app/creators/{$cr->id}/invite", ['email' => 'reem@ex.com'])
            ->assertRedirect()
            ->assertSessionHas('invitation_link', fn ($v) => str_contains($v, '/creator/invitation/'));

        // ويصل فعلًا إلى الصفحة عبر الفلاش المُشارَك
        $this->actingAs($ag)->get("/app/creators/{$cr->id}")->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $p) => $p
                ->where('access.state', 'pending')
                ->where('access.invitation.sentCount', 1));
    }

    /** وحالة الوصول تُشتقّ صحيحةً في كل مرحلة. */
    public function test_access_state_tracks_the_journey(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();

        $state = fn () => $this->actingAs($ag)->get("/app/creators/{$cr->id}")
            ->viewData('page')['props']['access']['state'];

        $this->assertSame('unlinked', $state());

        [$inv] = $this->svc()->invite($cr, 'reem@ex.com', '+966500000001', $ag);
        $this->assertSame('pending', $state());

        $inv = $this->fresh($inv);
        $this->svc()->verifyEmail($inv, $inv->email_code);
        $this->assertSame('email_verified', $state());

        $inv = $this->fresh($inv);
        $this->svc()->verifyPhone($inv, $inv->phone_code);
        $this->assertSame('phone_verified', $state());

        $this->svc()->accept($this->fresh($inv), 'StrongPass1');
        $this->assertSame('active', $state());
    }

    /**
     * حدّ البوابات: العضوية وحدها كانت تفتح لوحة الوكالة.
     *
     * تفعيل حساب صانع المحتوى يمنحه عضوية مؤسسة بدور بوابته ليعمل نطاق
     * المستأجر — و`EnsureAgencyMember` كان يكتفي بوجود السياق، فيرى صانع
     * المحتوى لوحة الوكالة وعملاءها وماليّتها كاملةً.
     */
    public function test_an_activated_creator_cannot_open_the_agency_workspace(): void
    {
        [$t, $org, $ag, $cr] = $this->ctx();
        $inv = $this->verified($cr, $ag);
        $user = $this->svc()->accept($inv, 'StrongPass1');

        $this->actingAs($user)->get('/app')->assertForbidden();
        $this->actingAs($user)->get('/app/clients')->assertForbidden();

        // وبوابته تفتح له
        $this->actingAs($user)->get('/creator/dashboard')->assertOk();
    }
}
