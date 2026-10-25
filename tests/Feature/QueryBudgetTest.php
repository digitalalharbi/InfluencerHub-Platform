<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignDeliverable;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Finance\Models\Payout;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CORE-01 — حارس ميزانية الاستعلامات (performance guard). يثبّت مكاسب PERF-01 ويمنع عودة
 * N+1: كل سطح عالي القيمة يُصيَّر ببيانات ممثِّلة، وعدد استعلامات SQL يجب أن يبقى ضمن سقف.
 * السقوف أعلى من العدد الحالي بهامش؛ انحدار N+1 (يتناسب مع البيانات) يتجاوزها فيسقط الاختبار.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** سقوف الاستعلامات لكل مسار (مُعايَرة من القياس + هامش ~25%). */
    private const BUDGETS = [
        '/app' => 70,
        '/app/creators' => 20,
        '/app/campaigns' => 28,
        '/app/reports' => 72,
        '/app/payouts' => 18,
        '/app/automation' => 24,
    ];

    // GOOGLE-01 أضاف مصدرًا حقيقيًّا واحدًا للخطّ الزمني (IntegrationExternalEvent — استعلام واحد،
    // بلا علاقات ولا N+1)، فارتفع الفعليّ من 30 إلى 31؛ رُفعت الميزانية مع هامش يبقي الحارس فعّالًا.
    private const CREATOR_PROFILE_BUDGET = 34;

    private int $creatorId = 0;

    /** يبني وكالة ببيانات ممثِّلة كافية لكشف N+1 (مبدعون/عملاء/حملات/تعاونات/محتوى/مستحقات). */
    private function seedAgency(): User
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'وكالة', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);

        $creators = [];
        for ($i = 0; $i < 12; $i++) {
            $creators[] = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id.'-'.$i, 'type' => 'influencer',
                'display_name' => 'مبدع'.$i, 'status' => 'active', 'followers_count' => 50000 + $i * 40000,
                'mowthooq_status' => $i % 3 === 0 ? 'verified' : 'pending', 'rate_per_post_minor' => 100000 + $i * 1000, 'bio' => 'نبذة']);
        }
        $this->creatorId = $creators[0]->id;

        $clients = [];
        for ($i = 0; $i < 5; $i++) {
            $clients[] = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id.'-'.$i,
                'display_name' => 'عميل'.$i, 'type' => 'company', 'status' => 'active', 'email' => "c$i@ex.com"]);
        }

        for ($i = 0; $i < 6; $i++) {
            $c = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id.'-'.$i, 'name' => 'حملة'.$i,
                'status' => ['active', 'planning', 'paused', 'completed'][$i % 4], 'budget_minor' => 5000000, 'currency' => 'SAR',
                'client_id' => $clients[$i % 5]->id, 'created_by' => $u->id, 'end_date' => now()->addDays(10)]);
            CampaignDeliverable::create(['tenant_id' => $t->id, 'campaign_id' => $c->id, 'type' => 'post', 'quantity' => 2, 'platform' => 'instagram', 'fee_minor' => 1000000]);
            foreach ([$creators[$i], $creators[($i + 1) % 12]] as $cr) {
                Collaboration::create(['tenant_id' => $t->id, 'campaign_id' => $c->id, 'collaboration_number' => 'CO-'.Str::random(5),
                    'creator_id' => $cr->id, 'title' => 'تعاون', 'fee_minor' => 500000, 'currency' => 'SAR', 'status' => 'accepted']);
                ContentItem::create(['tenant_id' => $t->id, 'campaign_id' => $c->id, 'client_id' => $clients[$i % 5]->id, 'creator_id' => $cr->id,
                    'content_number' => 'CN-'.Str::random(5), 'type' => 'post', 'title' => 'منشور', 'status' => 'published', 'version' => 1,
                    'published_url' => 'https://x/p', 'published_at' => now()->subDays($i)]);
                Payout::create(['tenant_id' => $t->id, 'campaign_id' => $c->id, 'payout_number' => 'PO-'.Str::random(5),
                    'creator_id' => $cr->id, 'amount_minor' => 300000, 'currency' => 'SAR', 'status' => $i % 2 ? 'paid' : 'approved', 'paid_at' => now()->subDays($i)]);
            }
        }
        TenantContext::reset();

        return $u;
    }

    public function test_high_value_pages_stay_within_query_budget(): void
    {
        $user = $this->seedAgency();
        $over = [];
        $report = "\n=== CORE-01 query budgets ===\n";

        foreach (self::BUDGETS as $path => $budget) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $res = $this->actingAs($user)->get($path);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            $res->assertOk();
            $report .= sprintf("  %-18s %3d / %3d\n", $path, $n, $budget);
            if ($n > $budget) {
                $over[] = "$path used $n queries (budget $budget)";
            }
        }

        // Creator 360 (ملف مبدع) — صفحة مبدع واحد
        DB::flushQueryLog();
        DB::enableQueryLog();
        $res = $this->actingAs($user)->get("/app/creators/{$this->creatorId}");
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();
        $res->assertOk();
        $report .= sprintf("  %-18s %3d / %3d\n", '/app/creators/:id', $n, self::CREATOR_PROFILE_BUDGET);
        if ($n > self::CREATOR_PROFILE_BUDGET) {
            $over[] = "/app/creators/{$this->creatorId} used $n queries (budget ".self::CREATOR_PROFILE_BUDGET.')';
        }

        fwrite(STDERR, $report);
        $this->assertSame([], $over, "Query budget exceeded (possible N+1 regression):\n".implode("\n", $over));
    }
}
