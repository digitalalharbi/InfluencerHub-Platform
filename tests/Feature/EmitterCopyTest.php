<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Communications\Models\Notification;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Mail\NotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * البريد الحقيقي من المُطلِقات (لا من عيّنات المعرض): قرار العميل على الترشيح يُنتج إشعارًا
 * ببريد يعرض كائنات الأعمال (الحملة/صانع المحتوى) بأسمائها وزرًّا خاصًّا، بلا مصطلح تقنيّ.
 */
class EmitterCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_shortlist_client_decision_email_uses_business_objects_not_context(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        [$owner, $item] = TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $owner = User::create(['name' => 'محمد العتيبي', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true, 'locale' => 'ar']);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'agency_admin', 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'شركة نماء', 'type' => 'company', 'status' => 'active']);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'client_id' => $cl->id, 'name' => 'صيف الرياض', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR', 'created_by' => $owner->id]);
            $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer', 'display_name' => 'نورة القحطاني', 'handle' => '@n', 'primary_platform' => 'instagram', 'followers_count' => 100000, 'status' => 'active']);
            $svc = app(ShortlistService::class);
            $sl = $svc->getOrCreate($cm, $owner->id);
            $item = $svc->addCreator($sl->currentVersion(), $cr);

            return [$owner, $item];
        });

        // قرار العميل (اعتماد) — يُطلِق إشعار الوكالة
        TenantContext::withTenant($t->id, fn () => app(ShortlistService::class)->clientDecision($item, 'approved'));

        $n = TenantContext::withBypass(fn () => Notification::where('user_id', $owner->id)->where('type', 'shortlist.item_approved')->latest()->first());
        $this->assertNotNull($n, 'أُنشئ إشعار قرار العميل');
        $this->assertSame('صيف الرياض', $n->data['objects'][0]['name'] ?? null);
        $this->assertSame('نورة القحطاني', $n->data['objects'][1]['name'] ?? null);
        $this->assertSame('مراجعة الترشيحات', $n->data['cta_label'] ?? null);

        // البريد المُصيَّر يعرض التسميات البشريّة والاسم، بلا «السياق»
        $n->setRelation('user', $owner);
        $html = (new NotificationMail($n))->render();
        $this->assertStringContainsString('مرحبًا محمد العتيبي،', $html);
        $this->assertStringContainsString('الحملة', $html);
        $this->assertStringContainsString('صيف الرياض', $html);
        $this->assertStringContainsString('صانع المحتوى', $html);
        $this->assertStringContainsString('نورة القحطاني', $html);
        $this->assertStringContainsString('مراجعة الترشيحات', $html);
        $this->assertStringNotContainsString('السياق', $html);
    }
}
