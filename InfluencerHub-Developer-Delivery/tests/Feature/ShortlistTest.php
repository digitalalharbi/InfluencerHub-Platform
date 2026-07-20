<?php
namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShortlistTest extends TestCase
{
    use RefreshDatabase;
    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function ctx(): array
    {
        $t = Tenant::create(['name'=>'t','slug'=>Str::random(8),'deployment_mode'=>'saas','status'=>'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id'=>$t->id,'name'=>'o','slug'=>Str::random(8),'type'=>'agency']);
        TenantContext::reset(); TenantContext::set($t->id, $org->id);
        $cl = \App\Domain\CRM\Models\Client::create(['tenant_id'=>$t->id,'client_number'=>'CL-'.$t->id.'-1','display_name'=>'ع','status'=>'active']);
        $camp = Campaign::create(['tenant_id'=>$t->id,'campaign_number'=>'CM-'.$t->id.'-1','client_id'=>$cl->id,'name'=>'ح','status'=>'planning','budget_minor'=>5000000,'currency'=>'SAR']);
        return [$t, $camp];
    }
    private function creator(Tenant $t, int $i, int $rate = 100000): Creator
    {
        return Creator::create(['tenant_id'=>$t->id,'creator_number'=>'CR-'.$t->id.'-'.$i,'type'=>'influencer',
            'display_name'=>'م'.$i,'primary_platform'=>'snapchat','followers_count'=>200000,'status'=>'active',
            'rate_per_post_minor'=>$rate,'mowthooq_status'=>'verified']);
    }

    public function test_add_versioning_budget_and_client_decision(): void
    {
        [$t, $camp] = $this->ctx();
        $svc = app(ShortlistService::class);
        $sl = $svc->getOrCreate($camp);
        $this->assertSame(1, $sl->current_version);
        $c1 = $this->creator($t, 1, 100000);
        $c2 = $this->creator($t, 2, 150000);
        $svc->addCreator($sl->currentVersion(), $c1);
        $svc->addCreator($sl->currentVersion(), $c2);
        $this->assertSame(2, $sl->currentVersion()->items()->count());
        // درجة الملاءمة محسوبة
        $item = $sl->currentVersion()->items()->where('creator_id',$c1->id)->first();
        $this->assertGreaterThan(0, $item->match_score);
        // إرسال ثم إعادة الترشيح تنشئ إصدارًا جديدًا يَنسخ العناصر
        $svc->submit($sl);
        $this->assertSame('submitted', $sl->fresh()->currentVersion()->status);
        $v2 = $svc->newRevision($sl->fresh());
        $this->assertSame(2, $v2->version);
        $this->assertSame(2, $v2->items()->count());
        // قرار العميل
        $it2 = $v2->items()->first();
        $svc->clientDecision($it2, 'approved');
        $this->assertSame('approved', $it2->fresh()->client_decision);
        $this->assertContains($v2->fresh()->status, ['partially_approved','approved']);
    }

    public function test_client_rejecting_all_marks_version_rejected(): void
    {
        [$t, $camp] = $this->ctx();
        $svc = app(ShortlistService::class);
        $sl = $svc->getOrCreate($camp);
        $svc->addCreator($sl->currentVersion(), $this->creator($t, 1));
        $svc->addCreator($sl->currentVersion(), $this->creator($t, 2));
        $svc->submit($sl);
        $v = $sl->fresh()->currentVersion();
        foreach ($v->items()->get() as $it) {
            $svc->clientDecision($it, 'rejected', 'خارج الجمهور');
        }
        // رفض الكل ⇒ الحالة "مرفوض" لا "معتمد جزئيًا"
        $this->assertSame('rejected', $v->fresh()->status);
        $this->assertSame('rejected', $sl->fresh()->status);
    }

    public function test_client_mixed_decision_is_partially_approved(): void
    {
        [$t, $camp] = $this->ctx();
        $svc = app(ShortlistService::class);
        $sl = $svc->getOrCreate($camp);
        $svc->addCreator($sl->currentVersion(), $this->creator($t, 1));
        $svc->addCreator($sl->currentVersion(), $this->creator($t, 2));
        $svc->submit($sl);
        $v = $sl->fresh()->currentVersion();
        $items = $v->items()->orderBy('id')->get();
        $svc->clientDecision($items[0], 'approved');
        $svc->clientDecision($items[1], 'rejected');
        $this->assertSame('partially_approved', $v->fresh()->status);
    }

    /**
     * قرار العميل كان يُكتب بصمت.
     *
     * البوابة تَعِد العميل بأن «قرارك يصل فريق الوكالة فورًا»، والوكالة هي من
     * يُنشئ التعاون بعده — فبلا إشعار تقف الرحلة عند قرار لا يعلم به أحد.
     */
    public function test_client_decision_is_audited_and_reaches_the_campaign_owner(): void
    {
        [$t, $camp] = $this->ctx();
        TenantContext::bypass(true);
        $owner = User::create(['name'=>'مالك','email'=>Str::random(6).'@ex.com','password'=>bcrypt('x'),'is_active'=>true]);
        $camp->update(['created_by' => $owner->id]);
        TenantContext::reset(); TenantContext::set($t->id);

        $svc = app(ShortlistService::class);
        $sl = $svc->getOrCreate($camp);
        $svc->addCreator($sl->currentVersion(), $this->creator($t, 1));
        $svc->submit($sl);
        $item = $sl->fresh()->currentVersion()->items()->first();
        $svc->clientDecision($item, 'approved');
        TenantContext::reset();

        TenantContext::bypass(true);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $t->id,
            'action' => 'shortlist.item_approved',
            'auditable_id' => $item->id,
        ]);
        $n = \App\Domain\Communications\Models\Notification::where('tenant_id', $t->id)
            ->where('user_id', $owner->id)->where('type', 'shortlist.item_approved')->first();
        $this->assertNotNull($n, 'اعتماد العميل لم يصل مالك الحملة');
        $this->assertStringContainsString('أنشئ التعاون', (string) $n->body,
            'الإشعار لا يقول الخطوة التالية');
        TenantContext::reset();
    }

    /** مع بقاء مرشّح معلّق، الدور ما زال على العميل — لا تُستدعى الوكالة للتعاون بعد. */
    public function test_a_pending_sibling_keeps_the_agency_uncalled(): void
    {
        [$t, $camp] = $this->ctx();
        TenantContext::bypass(true);
        $owner = User::create(['name'=>'مالك','email'=>Str::random(6).'@ex.com','password'=>bcrypt('x'),'is_active'=>true]);
        $camp->update(['created_by' => $owner->id]);
        TenantContext::reset(); TenantContext::set($t->id);

        $svc = app(ShortlistService::class);
        $sl = $svc->getOrCreate($camp);
        $svc->addCreator($sl->currentVersion(), $this->creator($t, 1));
        $svc->addCreator($sl->currentVersion(), $this->creator($t, 2));
        $svc->submit($sl);
        $items = $sl->fresh()->currentVersion()->items()->orderBy('id')->get();
        $svc->clientDecision($items[0], 'approved');
        TenantContext::reset();

        TenantContext::bypass(true);
        $n = \App\Domain\Communications\Models\Notification::where('tenant_id', $t->id)
            ->where('user_id', $owner->id)->latest('id')->first();
        $this->assertStringContainsString('بانتظار قرار العميل', (string) $n->body,
            'استُدعيت الوكالة لإنشاء التعاون بينما ما زال مرشّح معلّقًا');
        TenantContext::reset();
    }

    public function test_submitted_version_cannot_be_mutated_over_http(): void
    {
        $t = Tenant::create(['name'=>'t','slug'=>Str::random(8),'deployment_mode'=>'saas','status'=>'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id'=>$t->id,'name'=>'o','slug'=>Str::random(8),'type'=>'agency','status'=>'active']);
        $u = User::create(['name'=>'A','email'=>Str::random(6).'@ex.com','password'=>bcrypt('x'),'is_active'=>true]);
        OrganizationMembership::create(['tenant_id'=>$t->id,'organization_id'=>$org->id,'user_id'=>$u->id,'role'=>'agency_admin','status'=>'active']);
        TenantContext::reset(); TenantContext::set($t->id, $org->id);
        $cl = \App\Domain\CRM\Models\Client::create(['tenant_id'=>$t->id,'client_number'=>'CL-'.$t->id.'-1','display_name'=>'ع','status'=>'active']);
        $camp = Campaign::create(['tenant_id'=>$t->id,'campaign_number'=>'CM-'.$t->id.'-1','client_id'=>$cl->id,'name'=>'ح','status'=>'planning','budget_minor'=>5000000,'currency'=>'SAR']);
        $svc = app(ShortlistService::class);
        $sl = $svc->getOrCreate($camp);
        $svc->addCreator($sl->currentVersion(), $this->creator($t, 1));
        $svc->submit($sl);
        $newCreator = $this->creator($t, 2);
        TenantContext::reset();

        // إضافة مؤثر إلى إصدار مُرسَل عبر HTTP يجب أن تُرفض بلا تعديل
        $this->actingAs($u)
            ->from("/app/campaigns/{$camp->id}/shortlist")
            ->post("/app/campaigns/{$camp->id}/shortlist/add", ['creator_id' => $newCreator->id])
            ->assertRedirect("/app/campaigns/{$camp->id}/shortlist")
            ->assertSessionHasErrors('shortlist');

        TenantContext::bypass(true);
        $this->assertSame(1, $sl->currentVersion()->items()->count(), 'يجب ألا يتغيّر عدد عناصر الإصدار المُرسَل');
        TenantContext::reset();
    }

    public function test_tenant_isolation_on_items(): void
    {
        [$t, $camp] = $this->ctx();
        $svc = app(ShortlistService::class);
        $sl = $svc->getOrCreate($camp);
        $svc->addCreator($sl->currentVersion(), $this->creator($t,1));
        // ضمن سياق t: عنصر واحد مرئي
        $this->assertSame(1, \App\Domain\Campaigns\Models\CampaignShortlistItem::count());
        // مستأجر آخر لا يرى شيئًا
        TenantContext::reset();
        [$t2,] = $this->ctx();
        $this->assertSame(0, \App\Domain\Campaigns\Models\CampaignShortlistItem::count());
    }

    /** الإرسال بلا مرشّحين يُرفض بسبب مفهوم، لا يمرّ صامتًا. */
    public function test_submit_without_candidates_explains_why(): void
    {
        $t = Tenant::create(['name'=>'t','slug'=>Str::random(8),'deployment_mode'=>'saas','status'=>'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id'=>$t->id,'name'=>'o','slug'=>Str::random(8),'type'=>'agency','status'=>'active']);
        $u = User::create(['name'=>'A','email'=>Str::random(6).'@ex.com','password'=>bcrypt('x'),'is_active'=>true]);
        OrganizationMembership::create(['tenant_id'=>$t->id,'organization_id'=>$org->id,'user_id'=>$u->id,'role'=>'agency_admin','status'=>'active']);
        TenantContext::reset(); TenantContext::set($t->id, $org->id);
        $cl = \App\Domain\CRM\Models\Client::create(['tenant_id'=>$t->id,'client_number'=>'CL-'.$t->id.'-9','display_name'=>'ع','status'=>'active']);
        $camp = Campaign::create(['tenant_id'=>$t->id,'campaign_number'=>'CM-'.$t->id.'-9','client_id'=>$cl->id,'name'=>'ح','status'=>'planning','budget_minor'=>1000,'currency'=>'SAR']);
        TenantContext::reset();

        $this->actingAs($u)
            ->from("/app/campaigns/{$camp->id}/shortlist")
            ->post("/app/campaigns/{$camp->id}/shortlist/submit")
            ->assertSessionHasErrors('shortlist');

        TenantContext::bypass(true);
        $sl = \App\Domain\Campaigns\Models\CampaignShortlist::where('campaign_id', $camp->id)->first();
        TenantContext::reset();
        $this->assertNotSame('submitted', $sl?->status, 'أُرسلت قائمة فارغة');
    }
}
