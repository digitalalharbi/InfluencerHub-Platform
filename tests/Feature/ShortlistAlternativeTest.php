<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Communications\Models\Notification;
use App\Domain\Creators\Models\Creator;
use App\Domain\CRM\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * N6 — قرار العميل «أحتاج بديلًا» (needs_alternative): يضع بند الترشيح في هذه الحالة،
 * ويجعل حالة الإصدار changes_requested (الدور على الوكالة)، ويُشعِر الوكالة بطلب البديل.
 */
class ShortlistAlternativeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_needs_alternative_sets_changes_requested_and_notifies_agency(): void
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        [$owner, $item] = TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $owner = User::create(['name' => 'محمد', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true, 'locale' => 'ar']);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'agency_admin', 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'شركة نماء', 'type' => 'company', 'status' => 'active']);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'client_id' => $cl->id, 'name' => 'صيف الرياض', 'status' => 'active', 'budget_minor' => 500000, 'currency' => 'SAR', 'created_by' => $owner->id]);
            $cr = Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer', 'display_name' => 'نورة القحطاني', 'handle' => '@n', 'primary_platform' => 'instagram', 'followers_count' => 100000, 'status' => 'active']);
            $svc = app(ShortlistService::class);
            $sl = $svc->getOrCreate($cm, $owner->id);
            $item = $svc->addCreator($sl->currentVersion(), $cr);
            // إرسال الإصدار للعميل حتى لا يكون مسودة
            $sl->currentVersion()->update(['status' => 'submitted', 'submitted_at' => now()]);

            return [$owner, $item];
        });

        TenantContext::withTenant($t->id, fn () => app(ShortlistService::class)->clientDecision($item, 'needs_alternative', 'أريد منصّة مختلفة'));

        [$fresh, $notif] = TenantContext::withBypass(fn () => [
            CampaignShortlistItem::with('version')->find($item->id),
            Notification::where('user_id', $owner->id)->where('type', 'shortlist.item_needs_alternative')->latest()->first(),
        ]);

        $this->assertSame('needs_alternative', $fresh->client_decision);
        $this->assertSame('changes_requested', $fresh->version->status, 'الدور على الوكالة');
        $this->assertNotNull($notif, 'أُشعِرت الوكالة بطلب البديل');
        $this->assertStringContainsString('طلب بديلًا', $notif->title);
        $this->assertSame('اقتراح بديل', $notif->data['cta_label'] ?? null);
    }
}
