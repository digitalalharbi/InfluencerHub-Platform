<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Nomination\Services\NominationMatchService;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * المُطابِق القانونيّ الموحّد: درجة + أسباب + تحذيرات من بيانات حقيقيّة فقط.
 * unknown ≠ zero (لا خصم مُختلق)، وتحذير صادق عند نقص السعر/اختلاف المنصّة.
 */
class NominationMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Campaign,1:callable} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'ع', 'type' => 'company', 'status' => 'active']);
        $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'client_id' => $cl->id, 'name' => 'ح', 'status' => 'active', 'budget_minor' => 100000, 'currency' => 'SAR']);
        $mk = fn (array $a) => Creator::create(array_merge([
            'tenant_id' => $t->id, 'creator_number' => 'CR-'.Str::random(4), 'type' => 'influencer',
            'display_name' => 'م', 'handle' => '@'.Str::random(4), 'primary_platform' => 'instagram',
            'followers_count' => 120000, 'status' => 'active',
        ], $a));

        return [$cm, $mk];
    }

    public function test_platform_match_and_verified_and_rate_raise_score_with_reasons(): void
    {
        [$cm, $mk] = $this->world();
        $cr = $mk(['primary_platform' => 'instagram', 'followers_count' => 600000, 'mowthooq_status' => 'verified', 'rate_per_post_minor' => 300000]);
        $r = app(NominationMatchService::class)->score($cm, $cr, 'instagram');

        $this->assertGreaterThan(70, $r['score']);
        $this->assertContains('متوافق مع المنصّة المطلوبة', $r['reasons']);
        $this->assertContains('حساب موثّق', $r['reasons']);
        $this->assertContains('سعر محدّد', $r['reasons']);
        $this->assertContains('وصول واسع', $r['reasons']);
        $this->assertSame([], $r['flags']);
    }

    public function test_platform_mismatch_and_missing_rate_produce_honest_flags_not_negative(): void
    {
        [$cm, $mk] = $this->world();
        $cr = $mk(['primary_platform' => 'tiktok', 'rate_per_post_minor' => null]);
        $r = app(NominationMatchService::class)->score($cm, $cr, 'instagram');

        $this->assertContains('منصّة مختلفة عن المطلوب', $r['flags']);
        $this->assertContains('بيانات السعر غير مكتملة', $r['flags']);
        $this->assertNotContains('متوافق مع المنصّة المطلوبة', $r['reasons']);
        $this->assertGreaterThanOrEqual(0, $r['score']); // لا خصم سالب مُختلق
    }

    public function test_unknown_platform_is_not_penalised(): void
    {
        [$cm, $mk] = $this->world();
        $cr = $mk([]);
        // بلا منصّة معروفة للحملة ولا override ⇒ لا نقاط منصّة ولا تحذير (غير معروف)
        $r = app(NominationMatchService::class)->score($cm, $cr, null);
        $this->assertNotContains('منصّة مختلفة عن المطلوب', $r['flags']);
    }
}
