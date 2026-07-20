<?php

namespace Tests\Feature;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion};
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\Payout;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** بوابة المبدع React/Inertia — لوحة معزولة + مهام المبدع + أرباح. */
class InertiaCreatorPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Creator,2:Tenant} */
    private function creatorUser(bool $portal = true): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'o', 'slug' => Str::random(8), 'type' => 'agency']);
        $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
        $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_portal.enabled', 'value' => $portal ? 1 : 0]);
        (new CreateSubscription)->handle($org, $pv);
        $u = User::create(['name' => 'مبدع', 'email' => Str::random(6) . '@ex.com', 'password' => 'x', 'is_active' => true]);
        $seq = Creator::withTrashed()->count() + 1;
        $c = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-' . $t->id . '-' . $seq, 'type' => 'influencer',
            'display_name' => 'نورة', 'handle' => '@noura', 'primary_platform' => 'instagram', 'status' => 'active',
            'user_id' => $u->id, 'mowthooq_status' => 'verified', 'financial_verification_status' => 'not_provided']);
        TenantContext::reset();
        return [$u, $c, $t];
    }

    public function test_non_creator_denied(): void
    {
        TenantContext::bypass(true);
        $u = User::create(['name' => 'x', 'email' => Str::random(5) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        TenantContext::reset();
        $this->actingAs($u)->get('/beta/creator')->assertForbidden();
    }

    public function test_dashboard_renders_with_pending_and_earnings(): void
    {
        [$u, $c, $t] = $this->creatorUser();
        TenantContext::set($t->id);
        $collab = Collaboration::create(['tenant_id' => $t->id, 'collaboration_number' => 'CO-' . $t->id, 'creator_id' => $c->id,
            'title' => 'تعاون', 'fee_minor' => 300000, 'currency' => 'SAR', 'status' => 'in_progress']);
        Payout::create(['tenant_id' => $t->id, 'payout_number' => 'PO-' . $t->id, 'creator_id' => $c->id,
            'collaboration_id' => $collab->id, 'description' => 'دفعة', 'amount_minor' => 300000, 'currency' => 'SAR', 'status' => 'paid']);
        TenantContext::reset();

        $this->actingAs($u)->get('/beta/creator')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreatorPortal/Dashboard')
                ->where('creator.name', 'نورة')
                ->where('creator.verified', true)
                ->where('pending.0.key', 'collaborations')
                ->where('pending.0.count', 1)
                ->where('earnings.paidMinor', 300000)
                ->has('recent', 1));
    }

    public function test_isolated_to_own_creator(): void
    {
        [$u1, , $t1] = $this->creatorUser();
        [, $c2, $t2] = $this->creatorUser();
        TenantContext::set($t2->id);
        Collaboration::create(['tenant_id' => $t2->id, 'collaboration_number' => 'CO-B', 'creator_id' => $c2->id,
            'title' => 'آخر', 'fee_minor' => 100000, 'currency' => 'SAR', 'status' => 'in_progress']);
        TenantContext::reset();
        // مبدع 1 لا يرى تعاونات مبدع 2
        $this->actingAs($u1)->get('/beta/creator')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pending.0.count', 0)->has('recent', 0));
    }

    public function test_portal_disabled_blocks(): void
    {
        [$u] = $this->creatorUser(portal: false);
        $this->actingAs($u)->get('/beta/creator')->assertForbidden();
    }

    /* ===== حساب المبدع (React) — الملف/المنصّات/الخدمات/الأعمال/موثوق/المالية ===== */

    public function test_account_page_exposes_all_sections(): void
    {
        [$u] = $this->creatorUser();
        $this->actingAs($u)->get('/beta/creator/account')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CreatorPortal/Account')
                ->has('profile')->has('platforms')->has('services')
                ->has('portfolio')->has('mowthooq')->has('financial')->has('platformOptions'));
    }

    public function test_account_updates_profile(): void
    {
        [$u, $c] = $this->creatorUser();
        $this->actingAs($u)->post('/beta/creator/account/profile', [
            'display_name' => 'نورة الجديدة', 'city' => 'جدة', 'bio' => 'صانعة محتوى',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $fresh = Creator::find($c->id);
        TenantContext::reset();
        $this->assertSame('نورة الجديدة', $fresh->display_name);
        $this->assertSame('جدة', $fresh->city);
    }

    public function test_account_adds_and_deletes_platform(): void
    {
        [$u, $c] = $this->creatorUser();
        $this->actingAs($u)->post('/beta/creator/account/platforms', [
            'platform' => 'instagram', 'handle' => 'noura', 'followers_count' => 5000,
        ])->assertRedirect();

        TenantContext::bypass(true);
        $p = \App\Domain\Creators\Models\CreatorPlatform::where('creator_id', $c->id)->firstOrFail();
        TenantContext::reset();

        $this->actingAs($u)->post("/beta/creator/account/platforms/{$p->id}/delete")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame(0, \App\Domain\Creators\Models\CreatorPlatform::where('creator_id', $c->id)->count());
        TenantContext::reset();
    }

    /** منع IDOR: لا يحذف مبدع منصّة مبدع آخر. */
    public function test_account_cannot_delete_another_creators_platform(): void
    {
        [$uA] = $this->creatorUser();
        [, $cB] = $this->creatorUser();
        TenantContext::bypass(true);
        $pB = \App\Domain\Creators\Models\CreatorPlatform::create([
            'tenant_id' => $cB->tenant_id, 'creator_id' => $cB->id, 'platform' => 'tiktok', 'handle' => 'b',
        ]);
        TenantContext::reset();

        $this->actingAs($uA)->post("/beta/creator/account/platforms/{$pB->id}/delete")->assertNotFound();
    }

    /** السعر يُدخل بالريال ويُخزَّن بوحدات صغرى صحيحة. */
    public function test_account_service_price_stored_in_minor_units(): void
    {
        [$u, $c] = $this->creatorUser();
        $this->actingAs($u)->post('/beta/creator/account/services', [
            'service_type' => 'reel', 'price' => 1500.50, 'delivery_days' => 3,
        ])->assertRedirect();

        TenantContext::bypass(true);
        $s = \App\Domain\Creators\Models\CreatorService::where('creator_id', $c->id)->firstOrFail();
        TenantContext::reset();
        $this->assertSame(150050, (int) $s->price_minor);
    }

    /** نموذج العمل يُؤرشَف لا يُحذف نهائيًا. */
    public function test_account_portfolio_delete_archives_instead(): void
    {
        [$u, $c] = $this->creatorUser();
        $this->actingAs($u)->post('/beta/creator/account/portfolio', ['type' => 'link', 'url' => 'https://ex.com/a'])
            ->assertRedirect();

        TenantContext::bypass(true);
        $pf = \App\Domain\Creators\Models\CreatorPortfolio::where('creator_id', $c->id)->firstOrFail();
        TenantContext::reset();

        $this->actingAs($u)->post("/beta/creator/account/portfolio/{$pf->id}/delete")->assertRedirect();
        TenantContext::bypass(true);
        $this->assertSame('hidden', \App\Domain\Creators\Models\CreatorPortfolio::find($pf->id)->status);
        TenantContext::reset();
    }

    /** التوثيق القائم لا يسقط بمجرّد تعديل بيانات الترخيص. */
    public function test_account_mowthooq_keeps_existing_verification(): void
    {
        [$u, $c] = $this->creatorUser(); // الحساب موثّق أصلًا في المهيّئ
        $this->actingAs($u)->post('/beta/creator/account/mowthooq', ['mowthooq_license_number' => 'ML-1'])
            ->assertRedirect();

        TenantContext::bypass(true);
        $fresh = Creator::find($c->id);
        TenantContext::reset();
        $this->assertSame('ML-1', $fresh->mowthooq_license_number);
        $this->assertSame('verified', $fresh->mowthooq_status);
    }

    /** المبدع لا يوثّق نفسه: غير الموثّق يبقى بانتظار مراجعة الوكالة. */
    public function test_account_mowthooq_unverified_goes_pending(): void
    {
        [$u, $c] = $this->creatorUser();
        TenantContext::bypass(true);
        Creator::where('id', $c->id)->update(['mowthooq_status' => 'not_provided']);
        TenantContext::reset();

        $this->actingAs($u)->post('/beta/creator/account/mowthooq', ['mowthooq_license_number' => 'ML-2'])
            ->assertRedirect();

        TenantContext::bypass(true);
        $this->assertSame('pending', Creator::find($c->id)->mowthooq_status);
        TenantContext::reset();
    }

    /** الآيبان يُشفَّر ولا يُعاد كاملًا — تُعرض آخر أربعة أرقام فقط. */
    public function test_account_iban_is_encrypted_and_not_echoed(): void
    {
        [$u, $c] = $this->creatorUser();
        $iban = 'SA4420000001234567891234';
        $this->actingAs($u)->post('/beta/creator/account/financial', [
            'beneficiary_name' => 'نورة', 'bank_name' => 'الراجحي', 'iban' => $iban,
        ])->assertRedirect();

        TenantContext::bypass(true);
        $fresh = Creator::find($c->id);
        TenantContext::reset();
        $this->assertNotSame($iban, $fresh->iban_encrypted);
        $this->assertSame('1234', $fresh->iban_last4);
        $this->assertSame('pending', $fresh->financial_verification_status);

        $this->actingAs($u)->get('/beta/creator/account')
            ->assertInertia(fn (Assert $page) => $page->where('financial.ibanLast4', '1234'))
            ->assertDontSee($iban);
    }

    public function test_account_requires_creator_session(): void
    {
        $this->get('/beta/creator/account')->assertRedirect();
    }
}
