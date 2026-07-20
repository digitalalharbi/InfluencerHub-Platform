<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\{Creator, CreatorApplication, CreatorCapability};
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * قدرات صانع المحتوى تُجمَع ولا تُقصي بعضها.
 *
 * العمود القديم `creators.type` كان نصًّا واحدًا، ووجود قيمة «both» فيه هو
 * الدليل على قصوره: من ينشر ويُنتج UGC معًا لا يُجبَر على حسابين ولا على
 * اختيار واحد. هذه الاختبارات تمنع العودة إلى النوع الواحد.
 */
class CreatorCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function creator(string $type = 'influencer'): Creator
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id, 'type' => $type,
            'display_name' => 'م', 'status' => 'active']);
        TenantContext::set($t->id);

        return $c;
    }

    public function test_creator_holds_several_capabilities_at_once(): void
    {
        $c = $this->creator();
        foreach (['influencer', 'ugc', 'voiceover'] as $cap) {
            CreatorCapability::create(['tenant_id' => $c->tenant_id, 'creator_id' => $c->id, 'capability' => $cap]);
        }

        $c->load('capabilities');
        $this->assertTrue($c->hasCapability('influencer'));
        $this->assertTrue($c->hasCapability('ugc'));
        $this->assertTrue($c->hasCapability('voiceover'));
        $this->assertFalse($c->hasCapability('livestream'));
        $this->assertCount(3, $c->capabilityKeys());
    }

    public function test_same_capability_cannot_be_recorded_twice(): void
    {
        $c = $this->creator();
        CreatorCapability::create(['tenant_id' => $c->tenant_id, 'creator_id' => $c->id, 'capability' => 'ugc']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        CreatorCapability::create(['tenant_id' => $c->tenant_id, 'creator_id' => $c->id, 'capability' => 'ugc']);
    }

    /**
     * صانع أُنشئ قبل التطبيع لا ينكسر: تُقرأ قدراته من العمود القديم.
     * @dataProvider legacyTypes
     */
    public function test_legacy_type_still_answers_capability_questions(string $type, bool $influencer, bool $ugc): void
    {
        $c = $this->creator($type);

        $this->assertSame($influencer, $c->hasCapability('influencer'));
        $this->assertSame($ugc, $c->hasCapability('ugc'));
    }

    public static function legacyTypes(): array
    {
        return [
            'influencer' => ['influencer', true, false],
            'ugc_creator' => ['ugc_creator', false, true],
            'both' => ['both', true, true],
        ];
    }

    public function test_disabled_capability_does_not_count(): void
    {
        $c = $this->creator('ugc_creator');
        CreatorCapability::create(['tenant_id' => $c->tenant_id, 'creator_id' => $c->id,
            'capability' => 'ugc', 'is_enabled' => false]);

        $c->load('capabilities');
        $this->assertFalse($c->hasCapability('ugc'), 'قدرة معطّلة حُسبت مفعّلة');
    }

    // ===== مسار الكتابة: التسجيل والتحديث =====

    /** مستأجر + مؤسسة بـslug معروف لبوابة الانضمام العامة. */
    private function agencyWithSlug(string $slug, array $features = ['ugc_creator.enabled']): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => $slug, 'type' => 'agency', 'status' => 'active']);
        // قدرة UGC محكومة بحقّ الخطة: بلا اشتراك مفعِّل لها يُرفض التقديم،
        // وهو سلوك صحيح لا عيب في الاختبار — فتُمنح الوكالة الحقّ صراحةً.
        $plan = \App\Domain\Billing\Models\Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = \App\Domain\Billing\Models\PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        foreach ($features as $feature) {
            \App\Domain\Billing\Models\PlanEntitlement::create([
                'plan_version_id' => $pv->id, 'feature_key' => $feature, 'value' => 1,
            ]);
        }
        (new \App\Domain\Billing\Actions\CreateSubscription)->handle($org, $pv);
        TenantContext::reset();

        return [$t, $org];
    }

    /** الحقّ غائب ⇒ التقديم بقدرة UGC مرفوض. الحارس نفسه محروس. */
    public function test_ugc_capability_is_refused_when_the_plan_does_not_grant_it(): void
    {
        $this->agencyWithSlug('agency-nougc', []);

        $this->post('/join/creator?a=agency-nougc', [
            'capabilities' => ['ugc'],
            'full_name' => 'بلا حقّ', 'email' => 'nougc@ex.com', 'phone' => '+966500000000',
            'country_code' => 'SA', 'terms' => '1', 'privacy' => '1',
        ]);

        TenantContext::bypass(true);
        $this->assertSame(0, CreatorApplication::where('email', 'nougc@ex.com')->count());
        TenantContext::reset();
    }

    /** وكالة بمستخدم مسؤول (لمسارات /app). */
    private function agencyUser(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أ', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id,
            'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $org, $u];
    }

    /**
     * جوهر التطبيع: المتقدّم يصرّح بعدّة قدرات في طلب واحد وتُحفظ كلها.
     * لو حُفظت واحدة فقط لعدنا إلى العمود القديم باسم جديد.
     */
    public function test_onboarding_with_several_capabilities_persists_every_one(): void
    {
        [$t] = $this->agencyWithSlug('agency-caps');

        $this->post('/join/creator?a=agency-caps', [
            'capabilities' => ['influencer', 'ugc', 'voiceover'],
            'full_name' => 'لمى', 'email' => 'lama@ex.com', 'phone' => '+966500000000',
            'country_code' => 'SA', 'terms' => '1', 'privacy' => '1',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $app = CreatorApplication::where('email', 'lama@ex.com')->first();
        TenantContext::reset();

        $this->assertNotNull($app, 'لم يُنشأ الطلب');
        $this->assertEqualsCanonicalizing(['influencer', 'ugc', 'voiceover'], $app->capabilities);
        // كتابة مزدوجة: النص القديم يبقى متّسقًا مع القدرات لا متجمّدًا
        $this->assertSame('both', $app->account_type);
    }

    /** الخادم لا يثق بالواجهة: خانات الاختيار قد تُرسَل فارغة. */
    public function test_onboarding_rejects_zero_capabilities(): void
    {
        $this->agencyWithSlug('agency-zero');

        $this->post('/join/creator?a=agency-zero', [
            'capabilities' => [],
            'full_name' => 'فارغ', 'email' => 'empty@ex.com', 'phone' => '+966500000000',
            'country_code' => 'SA', 'terms' => '1', 'privacy' => '1',
        ])->assertSessionHasErrors('capabilities');

        TenantContext::bypass(true);
        $this->assertSame(0, CreatorApplication::where('email', 'empty@ex.com')->count(), 'حُفظ طلب بلا قدرات');
        TenantContext::reset();
    }

    /** حذف الحقل بالكامل ليس التفافًا على الشرط. */
    public function test_onboarding_rejects_missing_capabilities_field(): void
    {
        $this->agencyWithSlug('agency-missing');

        $this->post('/join/creator?a=agency-missing', [
            'full_name' => 'ناقص', 'email' => 'missing@ex.com', 'phone' => '+966500000000',
            'country_code' => 'SA', 'terms' => '1', 'privacy' => '1',
        ])->assertSessionHasErrors('capabilities');
    }

    /** الإضافة من جهة الوكالة تكتب صفوف القدرات لا نصًّا واحدًا. */
    public function test_agency_create_writes_capability_rows(): void
    {
        [$t, , $u] = $this->agencyUser();

        $this->actingAs($u)->post('/app/creators', [
            'display_name' => 'ريم', 'capabilities' => ['ugc', 'editor'], 'status' => 'prospect',
        ])->assertRedirect('/app/creators');

        TenantContext::bypass(true);
        $c = Creator::where('tenant_id', $t->id)->where('display_name', 'ريم')->first();
        TenantContext::reset();

        $this->assertNotNull($c);
        TenantContext::set($t->id);
        $this->assertEqualsCanonicalizing(['ugc', 'editor'], $c->load('capabilities')->capabilityKeys());
    }

    public function test_agency_create_rejects_zero_capabilities(): void
    {
        [, , $u] = $this->agencyUser();

        $this->actingAs($u)->post('/app/creators', ['display_name' => 'بلا', 'capabilities' => []])
            ->assertSessionHasErrors('capabilities');
    }

    // ===== مسار القراءة: الفلترة =====

    /** الفلتر يعيد من يملك القدرة فقط، ولا يفوته من يجمعها مع غيرها. */
    public function test_agency_filter_by_capability_returns_only_matching_creators(): void
    {
        [$t, , $u] = $this->agencyUser();
        TenantContext::set($t->id);
        $mixed = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-M', 'type' => 'influencer',
            'display_name' => 'جامع', 'status' => 'active']);
        $pure = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-P', 'type' => 'influencer',
            'display_name' => 'مؤثّر فقط', 'status' => 'active']);
        CreatorCapabilityService::sync($mixed, ['influencer', 'ugc']);
        CreatorCapabilityService::sync($pure, ['influencer']);
        TenantContext::reset();

        // القدرة المشتركة تُعيد الاثنين
        $this->actingAs($u)->get('/app/creators?type=influencer')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('creators.data', 2));

        // القدرة الخاصة تُعيد الجامع وحده — «both» القديم لم يعد يعني الإقصاء
        $this->actingAs($u)->get('/app/creators?type=ugc')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('creators.data', 1)
                ->where('creators.data.0.name', 'جامع'));
    }

    /**
     * صانع بلا صفوف قدرات لا يسقط من الفلتر بصمت.
     * إسقاط صفّ بصمت أخطر من إظهار صفّ زائد، لأن أحدًا لا يلاحظه.
     */
    public function test_filter_still_finds_creators_whose_capabilities_were_never_backfilled(): void
    {
        [$t, , $u] = $this->agencyUser();
        TenantContext::bypass(true);
        Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-L', 'type' => 'both',
            'display_name' => 'قديم', 'status' => 'active']);
        TenantContext::reset();

        $this->actingAs($u)->get('/app/creators?type=ugc')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('creators.data', 1));
        $this->actingAs($u)->get('/app/creators?type=influencer')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('creators.data', 1));
    }

    /** الروابط المحفوظة من عهد العمود الواحد يجب أن تبقى عاملة. */
    public function test_legacy_type_query_string_still_filters(): void
    {
        [$t, , $u] = $this->agencyUser();
        TenantContext::set($t->id);
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-U', 'type' => 'influencer',
            'display_name' => 'صانع', 'status' => 'active']);
        CreatorCapabilityService::sync($c, ['ugc']);
        TenantContext::reset();

        $this->actingAs($u)->get('/app/creators?type=ugc_creator')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('creators.data', 1));
    }

    // ===== الكتابة المزدوجة =====

    /**
     * @dataProvider capabilityCombinations
     *
     * العمود القديم ما يزال يُقرأ كمسار احتياطي؛ لو تجمّد لكذب على قارئه.
     */
    public function test_dual_write_keeps_legacy_type_consistent(array $caps, string $expected): void
    {
        $c = $this->creator();
        CreatorCapabilityService::sync($c, $caps);

        $this->assertSame($expected, $c->fresh()->type);
    }

    public static function capabilityCombinations(): array
    {
        return [
            'مؤثّر وحده' => [['influencer'], 'influencer'],
            'UGC وحده' => [['ugc'], 'ugc_creator'],
            'الاثنان معًا' => [['influencer', 'ugc'], 'both'],
            'مؤثّر مع قدرة إنتاجية' => [['influencer', 'voiceover'], 'influencer'],
            // قدرة إنتاجية بحتة: العمود القديم لا يعرفها، وأقرب ما يصفها «ينتج ولا ينشر»
            'إنتاج بحت' => [['photographer', 'editor'], 'ugc_creator'],
        ];
    }

    /** إلغاء قدرة يحذف صفّها ويصحّح النص القديم معًا — لا يترك أحدهما متخلّفًا. */
    public function test_removing_a_capability_updates_both_writes(): void
    {
        $c = $this->creator();
        CreatorCapabilityService::sync($c, ['influencer', 'ugc']);
        $this->assertSame('both', $c->fresh()->type);

        CreatorCapabilityService::sync($c, ['ugc']);

        $fresh = $c->fresh()->load('capabilities');
        $this->assertSame(['ugc'], $fresh->capabilityKeys());
        $this->assertSame('ugc_creator', $fresh->type);
        $this->assertFalse($fresh->hasCapability('influencer'), 'قدرة أُلغيت وما زالت تُقرأ');
    }

    /** المدخل غير المعروف لا يصل إلى قاعدة البيانات حتى لو تجاوز الواجهة. */
    public function test_unknown_capability_is_dropped_before_writing(): void
    {
        $c = $this->creator();
        CreatorCapabilityService::sync($c, ['influencer', 'wizard']);

        $this->assertSame(['influencer'], $c->fresh()->load('capabilities')->capabilityKeys());
    }
}
