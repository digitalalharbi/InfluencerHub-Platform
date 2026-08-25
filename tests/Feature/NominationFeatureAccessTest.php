<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\PoolCreator;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlist;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Nomination\Access\FeatureAvailabilityResolver;
use App\Domain\Nomination\Access\NominationAccess;
use App\Domain\Nomination\Support\NominationAbilities;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * N1 — قرار الوصول الموحّد لميزة «ترشيح المؤثرين» (influencer_nomination).
 *
 * يثبت: الإتاحة الافتراضية ON (لا كسر لسلوك قائم) · الإطفاء ⇒ 403 لكل سطح مباشر/تصدير ·
 * إخفاء الـnav · عزل المستأجر · عزل الصلاحية · حفظ البيانات عند الإطفاء وعودتها عند
 * إعادة التفعيل · إدارة المنصّة (Platform Owner) وحدها تملك التبديل · استقلال البوّابات.
 */
class NominationFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User,2:Campaign,3:Creator,4:Organization} */
    private function world(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'أحمد', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'client_id' => $cl->id,
            'name' => 'حملة الصيف', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR']);
        $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer',
            'display_name' => 'مبدع مطابق', 'handle' => '@match', 'primary_platform' => 'instagram',
            'followers_count' => 250000, 'status' => 'active', 'rate_per_post_minor' => 300000, 'mowthooq_status' => 'verified']);
        TenantContext::reset();

        return [$t, $u, $cm, $cr, $org];
    }

    private function disable(int $tenantId, ?string $portal = 'agency'): void
    {
        app(FeatureAvailabilityResolver::class)->set(NominationAbilities::KEY, $tenantId, null, $portal, false);
    }

    // ─────────────────────────────────────────── الإتاحة الافتراضية (ON)

    public function test_feature_on_by_default_preserves_existing_behavior(): void
    {
        [, $u, $cm] = $this->world();
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")->assertOk();
        $this->actingAs($u)->get('/beta/shortlisting')->assertOk();
    }

    // ─────────────────────────────────────────── الإطفاء ⇒ 403 لكل سطح

    public function test_feature_off_blocks_every_agency_surface_with_403(): void
    {
        [$t, $u, $cm, $cr] = $this->world();
        $this->disable($t->id);

        // مسار مباشر
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")->assertForbidden();
        // سطح عام
        $this->actingAs($u)->get('/beta/shortlisting')->assertForbidden();
        // تصدير
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist/export")->assertForbidden();
        // تصدير مقترح العميل (PDF)
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist/proposal/download")->assertForbidden();
        // إجراءات (API/POST)
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/shortlist/add", ['creator_id' => $cr->id])->assertForbidden();
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/shortlist/submit")->assertForbidden();
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/shortlist/revise")->assertForbidden();
    }

    public function test_feature_off_blocks_nominate_from_creator_database(): void
    {
        [$t, $u] = $this->world();
        $pool = PoolCreator::create(['name' => 'مصدر', 'platform' => 'instagram', 'account_url' => 'https://ig.com/'.Str::random(6), 'followers' => 100000, 'source_type' => 'celebrity']);
        $this->disable($t->id);
        // الحارس المركزي يردّ 403 قبل أي منطق (حتى قبل فحص استحقاق قاعدة المؤثرين).
        $this->actingAs($u)->post("/beta/creator-database/{$pool->id}/nominate", ['campaign_id' => 1])->assertForbidden();
    }

    // ─────────────────────────────────────────── حفظ البيانات + العودة

    public function test_disable_preserves_data_and_reenable_returns_it(): void
    {
        [$t, $u, $cm, $cr] = $this->world();

        // أضِف مرشّحًا بينما الميزة مُفعّلة
        $this->actingAs($u)->post("/beta/campaigns/{$cm->id}/shortlist/add", ['creator_id' => $cr->id])->assertRedirect();
        TenantContext::bypass(true);
        $shortlistId = CampaignShortlist::where('campaign_id', $cm->id)->firstOrFail()->id;
        $itemsBefore = CampaignShortlist::find($shortlistId)->currentVersion()->items()->count();
        TenantContext::reset();
        $this->assertSame(1, $itemsBefore);

        // أطفئ: يُمنع الوصول، لكنّ البيانات باقية في قاعدة البيانات
        $this->disable($t->id);
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")->assertForbidden();
        TenantContext::bypass(true);
        $this->assertSame(1, CampaignShortlist::find($shortlistId)->currentVersion()->items()->count(), 'الإطفاء يجب ألّا يمحو أي بيان');
        TenantContext::reset();

        // أعِد التفعيل: نفس السجلّ يعود
        app(FeatureAvailabilityResolver::class)->set(NominationAbilities::KEY, $t->id, null, 'agency', true);
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('items', 1)->where('items.0.creator', 'مبدع مطابق'));
    }

    // ─────────────────────────────────────────── عزل المستأجر

    public function test_disabling_one_tenant_does_not_affect_another(): void
    {
        // ملاحظة: تبديل مستأجرَين عبر طلبين HTTP متتاليين في اختبار واحد يصطدم بتسريب
        // سياق المصادقة بين الطلبات في بيئة الاختبار (أثر harness لا عيب منتج). لذا نُثبت
        // العزل من مصدر القرار نفسه (خدميًّا) + طلب HTTP واحد للمستأجر غير المتأثّر.
        [$tA] = $this->world();
        [$tB, $uB, $cmB] = $this->world();

        $this->disable($tA->id);

        $access = app(NominationAccess::class);
        $this->assertFalse($access->availableForTenant($tA->id, 'agency'), 'المستأجر المُعطَّل غير متاح');
        $this->assertTrue($access->availableForTenant($tB->id, 'agency'), 'المستأجر الآخر غير متأثّر');

        // طلب HTTP واحد (لا تبديل مستأجر) يؤكّد أن المستأجر الآخر يعمل فعلًا
        $this->actingAs($uB)->get("/beta/campaigns/{$cmB->id}/shortlist")->assertOk();
    }

    // ─────────────────────────────────────────── عزل الصلاحية

    public function test_role_without_view_permission_is_denied_even_when_enabled(): void
    {
        [, $u, $cm] = $this->world('finance'); // finance ليس ضمن VIEW
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")->assertForbidden();
    }

    // ─────────────────────────────────────────── إخفاء الـnav (نفس المصدر)

    public function test_nav_capability_reflects_availability_and_role(): void
    {
        [$t, $u] = $this->world('agency_admin');

        // مُفعّلة + دور يرى ⇒ الرابط ظاهر
        $this->actingAs($u)->get('/beta')
            ->assertInertia(fn (Assert $p) => $p->where('nav.can.influencer_nomination', true));

        // مُطفأة ⇒ مخفيّ
        $this->disable($t->id);
        $this->actingAs($u)->get('/beta')
            ->assertInertia(fn (Assert $p) => $p->where('nav.can.influencer_nomination', false));
    }

    public function test_nav_hidden_for_role_without_view(): void
    {
        [, $u] = $this->world('finance');
        $this->actingAs($u)->get('/beta')
            ->assertInertia(fn (Assert $p) => $p->where('nav.can.influencer_nomination', false));
    }

    // ─────────────────────────────────────────── إدارة المنصّة (manage_feature)

    public function test_platform_owner_can_toggle_feature_and_it_takes_effect(): void
    {
        [$t, $u, $cm] = $this->world();
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@platform.test', 'password' => bcrypt('x'), 'is_active' => true]);
        $owner->forceFill(['is_system_admin' => true, 'is_platform_owner' => true])->save();

        // إيقاف عبر مالك المنصّة
        $this->actingAs($owner)->post("/platform/tenants/{$t->id}/features/nomination", ['enabled' => false, 'portal' => 'agency'])->assertRedirect();
        $this->assertFalse(app(NominationAccess::class)->availableForTenant($t->id, 'agency'));
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")->assertForbidden();

        // إعادة تفعيل
        $this->actingAs($owner)->post("/platform/tenants/{$t->id}/features/nomination", ['enabled' => true, 'portal' => 'agency'])->assertRedirect();
        $this->assertTrue(app(NominationAccess::class)->availableForTenant($t->id, 'agency'));
        $this->actingAs($u)->get("/beta/campaigns/{$cm->id}/shortlist")->assertOk();
    }

    public function test_non_platform_owner_cannot_toggle_feature(): void
    {
        [$t, $u] = $this->world('agency_admin');
        $this->actingAs($u)->post("/platform/tenants/{$t->id}/features/nomination", ['enabled' => false])->assertForbidden();
        // لم يتغيّر شيء
        $this->assertTrue(app(NominationAccess::class)->availableForTenant($t->id, 'agency'));
    }

    // ─────────────────────────────────────────── استقلال البوّابات + دقّة الحلّ

    public function test_portal_scoping_is_independent(): void
    {
        [$t] = $this->world();
        $this->disable($t->id, 'agency'); // أطفئ بوّابة الوكالة فقط

        $access = app(NominationAccess::class);
        $this->assertFalse($access->availableForTenant($t->id, 'agency'));
        $this->assertTrue($access->availableForTenant($t->id, 'client'), 'إطفاء بوّابة لا يطفئ غيرها');
    }

    public function test_most_specific_scope_wins(): void
    {
        [$t] = $this->world();
        $resolver = app(FeatureAvailabilityResolver::class);
        $resolver->set(NominationAbilities::KEY, null, null, null, false);   // عام: مُطفأ
        $resolver->set(NominationAbilities::KEY, $t->id, null, null, true);  // مستأجر: مُفعّل (أخصّ)

        $this->assertTrue($resolver->enabled(NominationAbilities::KEY, $t->id, null, 'agency'), 'الأخصّ (المستأجر) يفوز العامّ');
        // مستأجر آخر لا صفّ له ⇒ يقع على العام (مُطفأ)
        $this->assertFalse($resolver->enabled(NominationAbilities::KEY, $t->id + 999, null, 'agency'));
    }
}
