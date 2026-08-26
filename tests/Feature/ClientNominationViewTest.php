<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Nomination\Support\ClientNominationView;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * الإسقاط الآمن للعميل: يعرض السعر المخصّص للعميل (بيع) + إشارات ملاءمة وصفيّة والقرار،
 * ولا يُسرّب أبدًا تكلفة/هامش/مستحقّات المبدع أو أي بيان ماليّ داخليّ. (حصانة انحدار خصوصيّة.)
 */
class ClientNominationViewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_client_projection_exposes_only_safe_fields(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $item = TenantContext::withBypass(function () use ($t) {
            Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'ع', 'type' => 'company', 'status' => 'active']);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'client_id' => $cl->id, 'name' => 'ح', 'status' => 'active', 'budget_minor' => 100000, 'currency' => 'SAR']);
            $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer',
                'display_name' => 'نورة', 'handle' => '@n', 'primary_platform' => 'instagram', 'followers_count' => 120000,
                'status' => 'active', 'rate_per_post_minor' => 300000]);
            $svc = app(ShortlistService::class);
            $sl = $svc->getOrCreate($cm);
            $item = $svc->addCreator($sl->currentVersion(), $cr);

            return $item->load('creator'); // كما يحمّله المتحكّم (with('creator'))
        });

        $view = ClientNominationView::item($item);

        // الحقول الآمنة موجودة
        $this->assertSame('نورة', $view['creator']);
        $this->assertSame(300000, $view['feeMinor']);       // سعر البيع للعميل
        $this->assertArrayHasKey('decision', $view);
        $this->assertArrayHasKey('score', $view);

        // لا أي مفتاح ماليّ داخليّ
        $keys = array_keys($view);
        foreach (['cost', 'cost_minor', 'costMinor', 'margin', 'payout', 'internal', 'bank', 'iban'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "الإسقاط يجب ألّا يحمل مفتاح «{$forbidden}»");
        }
        // مجموعة المفاتيح مقيّدة ومعروفة (whitelist)
        sort($keys);
        $this->assertSame(
            ['creator', 'decision', 'decisionLabel', 'decisionTone', 'feeMinor', 'followers', 'handle', 'id', 'isBackup', 'platform', 'reasons', 'score'],
            $keys,
        );
    }
}
