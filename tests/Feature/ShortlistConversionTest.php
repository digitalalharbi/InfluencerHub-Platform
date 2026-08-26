<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlistItem;
use App\Domain\Campaigns\Services\ShortlistService;
use App\Domain\Collaborations\Models\Collaboration;
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
 * N6 — تحويل المعتمَدين للتنفيذ: كل بند اعتمده العميل يصبح «تعاون» عبر الخدمة القانونية
 * الوحيدة، بأثر رجعي دائم وidempotency. المرفوض/طالب البديل/الاحتياط لا يُحوَّل، والمعلّق يمنع.
 */
class ShortlistConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User,2:Campaign,3:ShortlistService} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        [$owner, $cm] = TenantContext::withBypass(function () use ($t) {
            $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $owner = User::create(['name' => 'محمد', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true, 'locale' => 'ar']);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'agency_admin', 'status' => 'active']);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'شركة نماء', 'type' => 'company', 'status' => 'active']);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'client_id' => $cl->id, 'name' => 'صيف الرياض', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR', 'created_by' => $owner->id]);

            return [$owner, $cm];
        });

        return [$t, $owner, $cm, app(ShortlistService::class)];
    }

    private function creator(Tenant $t, string $name): Creator
    {
        return TenantContext::withBypass(fn () => Creator::create([
            'tenant_id' => $t->id, 'creator_number' => 'CR-'.Str::random(6), 'type' => 'influencer',
            'display_name' => $name, 'handle' => '@'.Str::random(5), 'primary_platform' => 'instagram',
            'followers_count' => 100000, 'status' => 'active',
        ]));
    }

    /** يبني إصدارًا مُرسلًا ببنود بقرارات محدّدة. $decisions: [ [name, decision, isBackup, fee], ... ] */
    private function buildVersion(Tenant $t, Campaign $cm, ShortlistService $svc, array $decisions): array
    {
        return TenantContext::withBypass(function () use ($t, $cm, $svc, $decisions) {
            $sl = $svc->getOrCreate($cm);
            $v = $sl->currentVersion();
            $items = [];
            foreach ($decisions as [$name, $decision, $isBackup, $fee]) {
                $cr = $this->creator($t, $name);
                $it = $svc->addCreator($v, $cr, (bool) $isBackup);
                $it->update(['client_decision' => $decision, 'proposed_fee_minor' => $fee]);
                $items[] = $it;
            }
            $v->update(['status' => 'partially_approved', 'submitted_at' => now(), 'decided_at' => now()]);

            return [$sl->fresh(), $items];
        });
    }

    public function test_only_approved_non_backup_convert(): void
    {
        [$t, $owner, $cm, $svc] = $this->world();
        [$sl] = $this->buildVersion($t, $cm, $svc, [
            ['نورة', 'approved', false, 300000],
            ['سارة', 'rejected', false, 200000],
            ['ليان', 'needs_alternative', false, 250000],
            ['هند', 'approved', true, 150000],   // احتياط معتمَد — لا يُحوَّل
        ]);

        $res = TenantContext::withTenant($t->id, fn () => $svc->convertApprovedToCollaborations($sl, $owner->id));

        $this->assertSame(1, $res['created'], 'المعتمَد الأساسي فقط');
        [$cols, $noura] = TenantContext::withBypass(fn () => [
            Collaboration::get(),
            Creator::where('display_name', 'نورة')->first(),
        ]);
        $this->assertCount(1, $cols);
        $col = $cols->first();
        $this->assertSame($noura->id, $col->creator_id, 'المعتمَد نورة فقط');
        $this->assertSame('offered', $col->status);
        $this->assertSame(300000, $col->fee_minor);
        $this->assertSame($cm->id, $col->campaign_id);
        $this->assertNotNull($col->shortlist_item_id, 'أثر رجعي دائم للبند');
    }

    public function test_pending_items_block_conversion(): void
    {
        [$t, $owner, $cm, $svc] = $this->world();
        [$sl] = $this->buildVersion($t, $cm, $svc, [
            ['نورة', 'approved', false, 300000],
            ['سارة', 'pending', false, 200000],
        ]);

        $this->expectException(\RuntimeException::class);
        TenantContext::withTenant($t->id, fn () => $svc->convertApprovedToCollaborations($sl, $owner->id));
    }

    public function test_conversion_is_idempotent(): void
    {
        [$t, $owner, $cm, $svc] = $this->world();
        [$sl] = $this->buildVersion($t, $cm, $svc, [
            ['نورة', 'approved', false, 300000],
            ['سارة', 'approved', false, 200000],
        ]);

        $r1 = TenantContext::withTenant($t->id, fn () => $svc->convertApprovedToCollaborations($sl, $owner->id));
        $r2 = TenantContext::withTenant($t->id, fn () => $svc->convertApprovedToCollaborations($sl, $owner->id));

        $this->assertSame(2, $r1['created']);
        $this->assertSame(0, $r2['created'], 'إعادة التشغيل لا تُنشئ ازدواجًا');
        $this->assertSame(2, $r2['skipped']);
        $count = TenantContext::withBypass(fn () => Collaboration::count());
        $this->assertSame(2, $count, 'لا تعاونات مكرّرة');
    }

    public function test_conversion_is_audited(): void
    {
        [$t, $owner, $cm, $svc] = $this->world();
        [$sl] = $this->buildVersion($t, $cm, $svc, [['نورة', 'approved', false, 300000]]);

        TenantContext::withTenant($t->id, fn () => $svc->convertApprovedToCollaborations($sl, $owner->id));

        $audited = TenantContext::withBypass(fn () => \App\Domain\Audit\Models\AuditLog::where('action', 'nomination.converted')->exists());
        $this->assertTrue($audited, 'حُفِظ أثر التحويل');
    }

    public function test_convert_route_requires_collaboration_create_permission(): void
    {
        [$t, , $cm, $svc] = $this->world();
        $this->buildVersion($t, $cm, $svc, [['نورة', 'approved', false, 300000]]);

        // عضو بلا صلاحية إنشاء تعاون (viewer) — يُمنع بالدور رغم أن الميزة مفعّلة
        $viewer = TenantContext::withBypass(function () use ($t) {
            $org = Organization::where('tenant_id', $t->id)->first();
            $u = User::create(['name' => 'مشاهد', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true, 'locale' => 'ar']);
            OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'viewer', 'status' => 'active']);

            return $u;
        });

        $this->actingAs($viewer)->post("/app/campaigns/{$cm->id}/shortlist/convert")->assertForbidden();
        $count = TenantContext::withBypass(fn () => Collaboration::count());
        $this->assertSame(0, $count);
    }

    public function test_convert_route_creates_collaborations_for_agency_admin(): void
    {
        [$t, $owner, $cm, $svc] = $this->world();
        $this->buildVersion($t, $cm, $svc, [['نورة', 'approved', false, 300000]]);

        $this->actingAs($owner)->post("/app/campaigns/{$cm->id}/shortlist/convert")
            ->assertRedirect();

        $count = TenantContext::withBypass(fn () => Collaboration::count());
        $this->assertSame(1, $count);
    }
}
