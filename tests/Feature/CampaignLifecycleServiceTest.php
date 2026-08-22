<?php

namespace Tests\Feature;

use App\Domain\CRM\Models\Client;
use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable, CampaignShortlist, CampaignShortlistItem, CampaignShortlistVersion};
use App\Domain\Campaigns\Services\CampaignLifecycleService;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Creators\Models\Creator;
use App\Domain\Finance\Models\{Invoice, Payout};
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * محرّك مراحل الحملة الـ13 — يُشتقّ من الحالة الحقيقية للنطاقات لا من حقل.
 * الحملة لا تتقدّم بمجرّد تغيير حالة؛ كل مرحلة تحتاج دليلًا فعليًّا مُخزَّنًا.
 */
class CampaignLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $t;
    private Client $client;
    private Creator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->t = Tenant::create(['name' => 'و', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $this->client = Client::create(['tenant_id' => $this->t->id, 'client_number' => 'CL-' . Str::random(4), 'display_name' => 'عميل', 'status' => 'active']);
        $this->creator = Creator::create(['tenant_id' => $this->t->id, 'creator_number' => 'CR-' . Str::random(4), 'type' => 'influencer', 'display_name' => 'مبدع', 'status' => 'active']);
        TenantContext::reset();
    }

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function svc(Campaign $c): array
    {
        return (new CampaignLifecycleService)->forCampaign($c->fresh());
    }

    private function stage(array $r, string $key): array
    {
        return collect($r['stages'])->firstWhere('key', $key);
    }

    private function campaign(string $status = 'draft', int $budget = 12000000): Campaign
    {
        return TenantContext::withBypass(fn () => Campaign::create([
            'tenant_id' => $this->t->id, 'campaign_number' => 'CM-' . Str::random(5), 'client_id' => $this->client->id,
            'name' => 'حملة', 'status' => $status, 'budget_minor' => $budget, 'currency' => 'SAR',
        ]));
    }

    private function do(callable $fn): void { TenantContext::bypass(true); $fn(); TenantContext::reset(); }

    /** يشتقّ الـ13 مرحلة من الأدلّة الحقيقية عبر رحلة كاملة. */
    public function test_lifecycle_is_derived_stage_by_stage_from_real_evidence(): void
    {
        $c = $this->campaign();

        // 1) حملة بلا مخرجات → الإنشاء قيد التنفيذ، الترشيح لم يبدأ
        $r = $this->svc($c);
        $this->assertSame('in_progress', $this->stage($r, 'creation')['state']);
        $this->assertSame('not_started', $this->stage($r, 'nomination')['state']);
        $this->assertSame('creation', $r['current']);
        $this->assertSame(13, $r['total']);

        // 2) مخرَج (بلا تاريخ) → الإنشاء مكتمل، الجدولة لم تبدأ
        $this->do(fn () => CampaignDeliverable::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'type' => 'post', 'quantity' => 1, 'platform' => 'instagram', 'fee_minor' => 1000000]));
        $r = $this->svc($c);
        $this->assertSame('complete', $this->stage($r, 'creation')['state']);
        $this->assertSame('not_started', $this->stage($r, 'scheduling')['state']);
        $this->assertSame('nomination', $r['current']);

        // 3) ترشيح (نسخة مسوّدة + عنصر) → الترشيح مكتمل، الاعتماد الداخلي قيد التنفيذ
        $sl = null;
        $this->do(function () use ($c, &$sl) {
            $sl = CampaignShortlist::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'current_version' => 1, 'status' => 'draft']);
            $v = CampaignShortlistVersion::create(['tenant_id' => $this->t->id, 'shortlist_id' => $sl->id, 'version' => 1, 'status' => 'draft']);
            CampaignShortlistItem::create(['tenant_id' => $this->t->id, 'shortlist_version_id' => $v->id, 'creator_id' => $this->creator->id, 'is_backup' => false, 'proposed_fee_minor' => 500000, 'match_score' => 70, 'reasons' => ['x'], 'client_decision' => 'pending']);
        });
        $r = $this->svc($c);
        $this->assertSame('complete', $this->stage($r, 'nomination')['state']);
        $this->assertSame('in_progress', $this->stage($r, 'internal_approval')['state']);

        // 4) النسخة مُرسَلة → الاعتماد الداخلي + الإرسال مكتملان، قرار العميل قيد التنفيذ
        $this->do(fn () => CampaignShortlistVersion::where('shortlist_id', $sl->id)->update(['status' => 'submitted', 'submitted_at' => now()]));
        $r = $this->svc($c);
        $this->assertSame('complete', $this->stage($r, 'internal_approval')['state']);
        $this->assertSame('complete', $this->stage($r, 'send_to_client')['state']);
        $this->assertSame('in_progress', $this->stage($r, 'client_decision')['state']);

        // 5) قرار العميل (اعتماد) → قرار العميل مكتمل
        $this->do(function () use ($sl) {
            $v = CampaignShortlistVersion::where('shortlist_id', $sl->id)->first();
            $v->update(['status' => 'approved']);
            CampaignShortlistItem::where('shortlist_version_id', $v->id)->update(['client_decision' => 'approved']);
        });
        $this->assertSame('complete', $this->stage($this->svc($c), 'client_decision')['state']);

        // 6) عقد موقّع → عرض السعر والعقد مكتمل
        $this->do(fn () => Contract::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'contract_number' => 'CT-' . Str::random(4), 'title' => 'عقد', 'party_type' => 'client', 'client_id' => $this->client->id, 'status' => 'signed']));
        $this->assertSame('complete', $this->stage($this->svc($c), 'quotation_contract')['state']);

        // 7) فاتورة مدفوعة → تحصيل العميل مكتمل
        $this->do(fn () => Invoice::create(['tenant_id' => $this->t->id, 'invoice_number' => 'INV-' . Str::random(5), 'client_id' => $this->client->id, 'campaign_id' => $c->id, 'status' => 'paid', 'currency' => 'SAR', 'subtotal_minor' => 1000000, 'discount_minor' => 0, 'tax_minor' => 150000, 'total_minor' => 1150000, 'tax_rate_bp' => 1500]));
        $this->assertSame('complete', $this->stage($this->svc($c), 'client_collection')['state']);

        // 8) تعاون مقبول → حجز المؤثرين مكتمل
        $this->do(fn () => Collaboration::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'collaboration_number' => 'CO-' . Str::random(4), 'creator_id' => $this->creator->id, 'title' => 'تعاون', 'fee_minor' => 500000, 'currency' => 'SAR', 'status' => 'accepted']));
        $this->assertSame('complete', $this->stage($this->svc($c), 'creator_booking')['state']);

        // 9) تاريخ للمخرَج → الجدولة مكتملة
        $this->do(fn () => CampaignDeliverable::where('campaign_id', $c->id)->update(['due_date' => '2026-09-15']));
        $this->assertSame('complete', $this->stage($this->svc($c), 'scheduling')['state']);

        // 10) مستحق مدفوع → الحوالات المالية مكتملة
        $this->do(fn () => Payout::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'payout_number' => 'PO-' . Str::random(4), 'creator_id' => $this->creator->id, 'amount_minor' => 500000, 'currency' => 'SAR', 'status' => 'paid']));
        $this->assertSame('complete', $this->stage($this->svc($c), 'creator_finance')['state']);

        // 11) محتوى منشور بإثبات + 12) أداء مُسجّل
        $this->do(fn () => ContentItem::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'client_id' => $this->client->id, 'creator_id' => $this->creator->id, 'content_number' => 'CN-' . Str::random(4), 'type' => 'post', 'title' => 'منشور', 'status' => 'published', 'version' => 1, 'published_url' => 'https://x/p/1', 'results_at' => now()]));
        $r = $this->svc($c);
        $this->assertSame('complete', $this->stage($r, 'publishing')['state']);
        $this->assertSame('complete', $this->stage($r, 'archive_performance')['state']);

        // التعاون المقبول التزام مفتوح يحجب الإقفال (المبدع قبِل ولم يُنجز) → أنجِزه أولًا
        $this->assertSame('blocked', $this->stage($this->svc($c), 'closure')['state']);
        $this->do(fn () => Collaboration::where('campaign_id', $c->id)->update(['status' => 'completed']));

        // 13) لا التزامات مفتوحة → الإقفال جاهز (قيد التنفيذ)، ثم إكمال الحالة → مكتمل + 100%
        $this->assertSame('complete', $this->stage($this->svc($c), 'creator_booking')['state']); // completed يبقي الحجز مكتملًا
        $this->assertSame('in_progress', $this->stage($this->svc($c), 'closure')['state']);
        $this->do(fn () => Campaign::where('id', $c->id)->update(['status' => 'completed']));
        $r = $this->svc($c);
        $this->assertSame('complete', $this->stage($r, 'closure')['state']);
        $this->assertSame(100, $r['progress']);
        $this->assertSame(13, $r['completed']);

        // الفصل المالي/التشغيلي: مُسوّاة ماليًّا (فاتورة مدفوعة + مستحق مدفوع)
        $this->assertTrue($r['financial']['settled']);
        $this->assertSame('closed', $r['operational']['state']);
    }

    /** مسار فشل: رفض العميل كل المرشّحين → قرار العميل «محجوب». */
    public function test_client_rejection_blocks_stage_5(): void
    {
        $c = $this->campaign();
        $this->do(function () use ($c) {
            $sl = CampaignShortlist::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'current_version' => 1, 'status' => 'rejected']);
            $v = CampaignShortlistVersion::create(['tenant_id' => $this->t->id, 'shortlist_id' => $sl->id, 'version' => 1, 'status' => 'rejected', 'submitted_at' => now()]);
            CampaignShortlistItem::create(['tenant_id' => $this->t->id, 'shortlist_version_id' => $v->id, 'creator_id' => $this->creator->id, 'is_backup' => false, 'proposed_fee_minor' => 500000, 'match_score' => 60, 'reasons' => ['x'], 'client_decision' => 'rejected']);
        });
        $st = $this->stage($this->svc($c), 'client_decision');
        $this->assertSame('blocked', $st['state']);
        $this->assertNotEmpty($st['blockers']);
    }

    /** مسار فشل: اعتذار المبدع (declined فقط) → حجز المؤثرين «محجوب». */
    public function test_declined_only_blocks_booking(): void
    {
        $c = $this->campaign();
        $this->do(fn () => Collaboration::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'collaboration_number' => 'CO-' . Str::random(4), 'creator_id' => $this->creator->id, 'title' => 'ت', 'fee_minor' => 500000, 'currency' => 'SAR', 'status' => 'declined']));
        $this->assertSame('blocked', $this->stage($this->svc($c), 'creator_booking')['state']);
    }

    /** الإقفال لا يتمّ بالتزامات مفتوحة: تعاون قائم + فاتورة غير محصّلة → «محجوب» ولا 100%. */
    public function test_open_obligations_block_closure(): void
    {
        $c = $this->campaign();
        $this->do(function () use ($c) {
            Collaboration::create(['tenant_id' => $this->t->id, 'campaign_id' => $c->id, 'collaboration_number' => 'CO-' . Str::random(4), 'creator_id' => $this->creator->id, 'title' => 'ت', 'fee_minor' => 500000, 'currency' => 'SAR', 'status' => 'in_progress']);
            Invoice::create(['tenant_id' => $this->t->id, 'invoice_number' => 'INV-' . Str::random(5), 'client_id' => $this->client->id, 'campaign_id' => $c->id, 'status' => 'issued', 'currency' => 'SAR', 'subtotal_minor' => 1000000, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 1000000, 'tax_rate_bp' => 0]);
        });
        $st = $this->stage($this->svc($c), 'closure');
        $this->assertSame('blocked', $st['state']);
        $this->assertNotEmpty($st['blockers']);
        $this->assertNotSame(100, $this->svc($c)['progress']);
        // ماليًّا غير مُسوّاة (فاتورة مفتوحة)
        $this->assertFalse($this->svc($c)['financial']['settled']);
    }
}
