<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable};
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * تنفيذ الحملة: مخرجات ← ترشيح ← إرسال لاعتماد العميل.
 *
 * كل اختبار هنا يحرس انقطاعًا ظهر أثناء تشغيل الرحلة من المتصفح: مخرَج بلا
 * أتعاب فلا أساس للفاتورة ولا للمستحق، ومبدع يُضاف فلا يظهر في الترشيح لأن
 * حالته «مبدئي» ولا مسار لتغييرها.
 */
class CampaignExecutionJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Organization $org;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'و', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $this->org = Organization::create(['tenant_id' => $this->tenant->id, 'name' => 'و',
            'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $this->admin = User::create(['name' => 'مالك', 'email' => Str::random(8) . '@ex.com',
            'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $this->tenant->id, 'organization_id' => $this->org->id,
            'user_id' => $this->admin->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function campaign(int $budgetMinor = 12000000): Campaign
    {
        TenantContext::bypass(true);
        $client = Client::create(['tenant_id' => $this->tenant->id, 'client_number' => 'CL-' . Str::random(4),
            'display_name' => 'عميل', 'status' => 'active']);
        $c = Campaign::create(['tenant_id' => $this->tenant->id, 'campaign_number' => 'CM-' . Str::random(4),
            'client_id' => $client->id, 'name' => 'حملة', 'status' => 'draft',
            'budget_minor' => $budgetMinor, 'currency' => 'SAR']);
        TenantContext::reset();

        return $c;
    }

    private function creator(string $status = 'prospect'): Creator
    {
        TenantContext::bypass(true);
        $c = Creator::create(['tenant_id' => $this->tenant->id, 'creator_number' => 'CR-' . Str::random(4),
            'type' => 'influencer', 'display_name' => 'مبدع', 'status' => $status]);
        TenantContext::reset();

        return $c;
    }

    // ===== المخرجات تحمل قيمتها =====

    /** بلا أتعاب يبقى المخرَج بلا قيمة: تقترح الفاتورة صفرًا ولا أساس للمستحق. */
    public function test_a_deliverable_stores_its_unit_fee_and_due_date(): void
    {
        $camp = $this->campaign();

        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/deliverables", [
            'type' => 'post', 'quantity' => 6, 'platform' => 'instagram',
            'fee_minor' => 1000000, 'due_date' => '2026-09-15',
        ])->assertRedirect();

        TenantContext::bypass(true);
        $d = CampaignDeliverable::where('campaign_id', $camp->id)->firstOrFail();
        TenantContext::reset();

        $this->assertSame(1000000, (int) $d->fee_minor);
        $this->assertSame(6, (int) $d->quantity);
    }

    /** الأتعاب لكل وحدة: الالتزام = الأتعاب × الكمية. */
    public function test_committed_budget_multiplies_fee_by_quantity(): void
    {
        $camp = $this->campaign();
        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/deliverables", [
            'type' => 'post', 'quantity' => 6, 'fee_minor' => 1000000,
        ]);

        TenantContext::bypass(true);
        $fresh = Campaign::with('deliverables')->find($camp->id);
        TenantContext::reset();

        $this->assertSame(6000000, $fresh->committedMinor(), 'الالتزام لا يضرب الأتعاب في الكمية');
    }

    /** بند الفاتورة المقترَح يأخذ سعر الوحدة والكمية — فلا يُعاد الإدخال. */
    public function test_invoice_items_are_suggested_from_deliverables(): void
    {
        $camp = $this->campaign();
        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/deliverables", [
            'type' => 'post', 'quantity' => 6, 'platform' => 'instagram', 'fee_minor' => 1000000,
        ]);

        $res = $this->actingAs($this->admin)->getJson("/app/campaigns/{$camp->id}/invoice-items");
        $res->assertOk();
        $item = $res->json('items.0');

        $this->assertSame(6, $item['quantity']);
        $this->assertSame(1000000, $item['unit_price_minor']);
    }

    // ===== حالة المبدع تحكم ظهوره في الترشيح =====

    /** الترشيح يعرض النشطين فقط — وهذا سلوك مقصود يُشرَح لا يُخفى. */
    public function test_a_prospect_creator_does_not_appear_among_candidates(): void
    {
        $camp = $this->campaign();
        $this->creator('prospect');

        $this->actingAs($this->admin)->get("/app/campaigns/{$camp->id}/shortlist")->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('candidates', 0)
                // الحالة الفارغة تُميّز «لا مبدعين» من «مبدعون غير نشطين»
                ->where('candidatePool.active', 0)
                ->where('candidatePool.inactive', 1));
    }

    /** لم يكن للمبدع مسار تحديث: يُضاف «مبدئيًّا» فيختفي من الترشيح بلا سبب. */
    public function test_activating_a_creator_makes_them_appear_among_candidates(): void
    {
        $camp = $this->campaign();
        $creator = $this->creator('prospect');

        $this->actingAs($this->admin)->post("/app/creators/{$creator->id}/update", ['status' => 'active'])
            ->assertRedirect();
        $this->assertSame('active', $creator->fresh()->status);

        $this->actingAs($this->admin)->get("/app/campaigns/{$camp->id}/shortlist")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('candidates', 1)
                ->where('candidatePool.active', 1));
    }

    public function test_creator_status_must_be_a_known_value(): void
    {
        $creator = $this->creator();

        $this->actingAs($this->admin)->from("/app/creators/{$creator->id}")
            ->post("/app/creators/{$creator->id}/update", ['status' => 'invented'])
            ->assertSessionHasErrors('status');

        $this->assertSame('prospect', $creator->fresh()->status);
    }

    public function test_creator_of_another_tenant_cannot_be_updated(): void
    {
        $other = Tenant::create(['name' => 'آخر', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $foreign = Creator::create(['tenant_id' => $other->id, 'creator_number' => 'CR-X',
            'type' => 'influencer', 'display_name' => 'غريب', 'status' => 'prospect']);
        TenantContext::reset();

        $this->actingAs($this->admin)->post("/app/creators/{$foreign->id}/update", ['status' => 'active'])
            ->assertNotFound();
        $this->assertSame('prospect', $foreign->fresh()->status);
    }

    // ===== الترشيح يصل العميل =====

    /** السلسلة كاملة: تفعيل ← إضافة كأساسي ← إرسال، فيصير للعميل قرار يُتّخذ. */
    public function test_the_shortlist_reaches_the_client_for_a_decision(): void
    {
        $camp = $this->campaign();
        $creator = $this->creator('active');

        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/shortlist/add", [
            'creator_id' => $creator->id,
        ])->assertRedirect();

        // الحملة تغادر المسودة أوّلًا: بوابة العميل لا تعرض المسودّات
        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/plan")->assertRedirect();

        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/shortlist/submit")
            ->assertRedirect()->assertSessionHasNoErrors();

        TenantContext::bypass(true);
        $sl = \App\Domain\Campaigns\Models\CampaignShortlist::where('campaign_id', $camp->id)->firstOrFail();
        $version = $sl->currentVersion();
        // العدّ داخل التجاوز: النطاق مغلق افتراضيًّا فيعود صفرًا بعد الاستعادة
        $itemCount = $version->items()->count();
        TenantContext::reset();

        $this->assertSame('submitted', $version->status);
        $this->assertNotNull($version->submitted_at);
        $this->assertSame(1, $itemCount);
    }

    /**
     * لا يُرسَل ترشيح على حملة مسودة: بوابة العميل تُخفي المسودّات، فتصير
     * القائمة «مُرسلة» ولا يجد العميل ما يقرّره — طريق مسدود صامت.
     */
    public function test_a_shortlist_cannot_be_sent_while_the_campaign_is_a_draft(): void
    {
        $camp = $this->campaign();
        $creator = $this->creator('active');
        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/shortlist/add", ['creator_id' => $creator->id]);

        $this->actingAs($this->admin)->from("/app/campaigns/{$camp->id}/shortlist")
            ->post("/app/campaigns/{$camp->id}/shortlist/submit")
            ->assertSessionHasErrors('shortlist');

        TenantContext::bypass(true);
        $sl = \App\Domain\Campaigns\Models\CampaignShortlist::where('campaign_id', $camp->id)->firstOrFail();
        $status = $sl->currentVersion()->status;
        TenantContext::reset();
        $this->assertSame('draft', $status, 'أُرسل ترشيح لحملة لا يراها العميل');
    }

    /** بعد نقل الحملة للتخطيط يصير الإرسال متاحًا. */
    public function test_the_shortlist_can_be_sent_once_the_campaign_leaves_draft(): void
    {
        $camp = $this->campaign();
        $creator = $this->creator('active');
        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/shortlist/add", ['creator_id' => $creator->id]);
        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/plan")->assertRedirect();

        $this->actingAs($this->admin)->post("/app/campaigns/{$camp->id}/shortlist/submit")
            ->assertRedirect()->assertSessionHasNoErrors();

        TenantContext::bypass(true);
        $sl = \App\Domain\Campaigns\Models\CampaignShortlist::where('campaign_id', $camp->id)->firstOrFail();
        $status = $sl->currentVersion()->status;
        TenantContext::reset();
        $this->assertSame('submitted', $status);
    }
}
