<?php

namespace Tests\Feature;

use App\Domain\AdminPool\Models\{CreatorDatabaseOverlay, PoolCreator};
use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanEntitlement, PlanVersion};
use App\Domain\Campaigns\Models\{Campaign, CampaignShortlistItem};
use App\Domain\Campaigns\Services\CampaignLifecycleService;
use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * الجسر والتراكب: تجسيد حتمي لمبدع مشترك، ترشيح لحملة (المرحلة 2)، وعزل التراكب.
 */
class CreatorDatabaseBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    /** @return array{0:User,1:Organization,2:Tenant} */
    private function agency(string $role = 'agency_admin'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);

        return TenantContext::withBypass(function () use ($t, $role) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $plan = Plan::create(['key' => Str::random(6), 'name' => 'P', 'is_active' => true]);
            $pv = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
            PlanEntitlement::create(['plan_version_id' => $pv->id, 'feature_key' => 'creator_database.access', 'value' => 1]);
            (new CreateSubscription)->handle($org, $pv);
            $u = User::create(['name' => 'م', 'email' => Str::random(6) . '@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);

            return [$u, $org, $t];
        });
    }

    private function pool(): PoolCreator
    {
        return PoolCreator::create([
            'name' => 'مبدع مشترك', 'phone' => '0501234567', 'platform' => 'tiktok',
            'account_url' => 'https://www.tiktok.com/@shared' . Str::random(4), 'followers' => 200000,
            'tier' => 'A', 'gender' => 'female', 'categories' => ['أسلوب حياة'],
            'store' => 'وزنة', 'source_type' => 'ugc', 'cost_post_minor' => 100000, 'imported_at' => now(),
        ]);
    }

    private function campaign(Tenant $t): Campaign
    {
        return TenantContext::withBypass(function () use ($t) {
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-' . $t->id . '-1', 'display_name' => 'عميل', 'type' => 'company', 'status' => 'active']);

            return Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-' . $t->id . '-1', 'client_id' => $cl->id,
                'name' => 'حملة', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR']);
        });
    }

    public function test_nominate_materializes_creator_and_advances_stage_2(): void
    {
        [$u, , $t] = $this->agency();
        $pool = $this->pool();
        $campaign = $this->campaign($t);

        $this->actingAs($u)->post("/app/creator-database/{$pool->id}/nominate", ['campaign_id' => $campaign->id])
            ->assertRedirect();

        // علاقة مبدع للمستأجر أُنشئت ومربوطة بالمبدع المشترك (رابط داخلي)
        $creator = TenantContext::withBypass(fn () => Creator::where('pool_creator_id', $pool->id)->first());
        $this->assertNotNull($creator);
        $this->assertSame('مبدع مشترك', $creator->display_name);
        $this->assertSame('ugc_creator', $creator->type);

        // بند قائمة مختصرة أُضيف → المرحلة 2 (الترشيح) اكتملت
        $items = TenantContext::withBypass(fn () => CampaignShortlistItem::where('creator_id', $creator->id)->count());
        $this->assertSame(1, $items);

        $r = TenantContext::withBypass(fn () => (new CampaignLifecycleService)->forCampaign($campaign->fresh()));
        $nomination = collect($r['stages'])->firstWhere('key', 'nomination');
        $this->assertSame('complete', $nomination['state']);
    }

    public function test_nominate_is_idempotent(): void
    {
        [$u, , $t] = $this->agency();
        $pool = $this->pool();
        $campaign = $this->campaign($t);

        $this->actingAs($u)->post("/app/creator-database/{$pool->id}/nominate", ['campaign_id' => $campaign->id])->assertRedirect();
        $this->actingAs($u)->post("/app/creator-database/{$pool->id}/nominate", ['campaign_id' => $campaign->id])->assertRedirect();

        // ترشيح مكرّر لا يُنشئ علاقة مبدع ثانية ولا بندًا ثانيًا
        TenantContext::withBypass(function () use ($pool) {
            $this->assertSame(1, Creator::where('pool_creator_id', $pool->id)->count());
            $creatorId = Creator::where('pool_creator_id', $pool->id)->value('id');
            $this->assertSame(1, CampaignShortlistItem::where('creator_id', $creatorId)->count());
        });
    }

    public function test_materialized_creator_does_not_carry_source(): void
    {
        [$u, $org, $t] = $this->agency();
        $pool = $this->pool();
        $campaign = $this->campaign($t);
        $this->actingAs($u)->post("/app/creator-database/{$pool->id}/nominate", ['campaign_id' => $campaign->id]);

        $creator = TenantContext::withBypass(fn () => Creator::where('pool_creator_id', $pool->id)->first());
        // لا عمود مصدر/متجر على علاقة المستأجر — الرابط الوحيد رقمي داخلي
        $arr = $creator->toArray();
        $this->assertArrayNotHasKey('store', $arr);
        $this->assertArrayNotHasKey('source_type', $arr);
        $this->assertStringNotContainsString('وزنة', json_encode($arr, JSON_UNESCAPED_UNICODE));
    }

    public function test_viewer_cannot_nominate(): void
    {
        [$u, , $t] = $this->agency(role: 'viewer');
        $pool = $this->pool();
        $campaign = $this->campaign($t);

        $this->actingAs($u)->post("/app/creator-database/{$pool->id}/nominate", ['campaign_id' => $campaign->id])
            ->assertForbidden();
    }

    public function test_overlay_save_and_read(): void
    {
        [$u, $org] = $this->agency();
        $pool = $this->pool();

        $this->actingAs($u)->post("/app/creator-database/{$pool->id}/overlay", [
            'favorite' => true, 'tags' => ['مميّز'], 'notes' => 'تعاون سابق ممتاز', 'negotiated_rate' => 4500,
        ])->assertRedirect();

        $ov = TenantContext::withBypass(fn () => CreatorDatabaseOverlay::where('organization_id', $org->id)->where('pool_creator_id', $pool->id)->first());
        $this->assertNotNull($ov);
        $this->assertTrue($ov->favorite);
        $this->assertSame(['مميّز'], $ov->tags);
        $this->assertSame(450000, $ov->negotiated_rate_minor);
    }

    public function test_overlay_is_tenant_isolated(): void
    {
        [$ua, $orgA, $ta] = $this->agency();
        [, $orgB, $tb] = $this->agency();
        $pool = $this->pool();

        // مؤسسة ألف تحفظ تراكبها الخاصّ
        $this->actingAs($ua)->post("/app/creator-database/{$pool->id}/overlay", ['favorite' => true, 'notes' => 'سرّي لألف'])->assertRedirect();

        // العزل الحقيقي عبر نطاق BelongsToTenant: في سياق مستأجر باء لا يُقرأ تراكب ألف إطلاقًا
        $seenByB = TenantContext::withTenant($tb->id, fn () => CreatorDatabaseOverlay::where('pool_creator_id', $pool->id)->get());
        $this->assertCount(0, $seenByB, 'مؤسسة باء رأت تراكب ألف — خرق عزل');

        // ومؤسسة ألف ترى تراكبها هي فقط
        $seenByA = TenantContext::withTenant($ta->id, fn () => CreatorDatabaseOverlay::where('pool_creator_id', $pool->id)->get());
        $this->assertCount(1, $seenByA);
        $this->assertSame('سرّي لألف', $seenByA->first()->notes);

        // ولا يوجد تراكب لمؤسسة باء أصلًا
        $countB = TenantContext::withBypass(fn () => CreatorDatabaseOverlay::where('organization_id', $orgB->id)->count());
        $this->assertSame(0, $countB);
    }
}
