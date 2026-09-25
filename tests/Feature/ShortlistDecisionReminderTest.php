<?php

namespace Tests\Feature;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignShortlist;
use App\Domain\Campaigns\Models\CampaignShortlistVersion;
use App\Domain\Campaigns\Services\ShortlistDecisionReminderService;
use App\Domain\Communications\Models\Notification;
use App\Domain\CRM\Models\Client;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تذكير قرار العميل على الترشيح: تذكير مرّة واحدة بعد مضيّ المهلة بلا قرار، بلا تكرار عند
 * إعادة المسح، ولا تذكير لترشيح حديث أو مُبَتّ فيه. مبنيّ على submitted_at الحقيقيّة.
 */
class ShortlistDecisionReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User,2:Campaign} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);

        return TenantContext::withBypass(function () use ($t) {
            $owner = User::create(['name' => 'منسّق', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            $clientUser = User::create(['name' => 'عميل', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            $cl = Client::create(['tenant_id' => $t->id, 'client_number' => 'CL-'.$t->id, 'display_name' => 'شركة نماء', 'type' => 'company', 'status' => 'active']);
            ClientMember::create(['tenant_id' => $t->id, 'client_id' => $cl->id, 'user_id' => $clientUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
            $cm = Campaign::create(['tenant_id' => $t->id, 'campaign_number' => 'CM-'.$t->id, 'client_id' => $cl->id, 'name' => 'صيف الرياض', 'status' => 'active', 'budget_minor' => 5000000, 'currency' => 'SAR', 'created_by' => $owner->id]);

            return [$t, $owner, $cm];
        });
    }

    private function version(Tenant $t, Campaign $cm, string $status, ?string $submittedAt, ?string $decidedAt = null): CampaignShortlistVersion
    {
        return TenantContext::withBypass(function () use ($t, $cm, $status, $submittedAt, $decidedAt) {
            $sl = CampaignShortlist::create(['tenant_id' => $t->id, 'campaign_id' => $cm->id, 'current_version' => 1, 'status' => 'submitted', 'created_by' => $cm->created_by]);

            return CampaignShortlistVersion::create([
                'tenant_id' => $t->id, 'shortlist_id' => $sl->id, 'version' => 1,
                'status' => $status, 'submitted_at' => $submittedAt, 'decided_at' => $decidedAt,
            ]);
        });
    }

    private function reminderNotifs(int $tenantId): int
    {
        return TenantContext::withBypass(fn () => Notification::where('tenant_id', $tenantId)->where('type', 'shortlist.decision_reminder')->count());
    }

    public function test_pending_decision_beyond_window_reminds_once(): void
    {
        [$t, , $cm] = $this->world();
        $v = $this->version($t, $cm, 'submitted', now()->subHours(80)->toDateTimeString());

        $r = app(ShortlistDecisionReminderService::class)->scan();

        $this->assertSame(1, $r['notified']);
        // عضو العميل + صاحب الحملة
        $this->assertSame(2, $this->reminderNotifs($t->id));
        $this->assertNotNull($v->fresh()->client_decision_reminded_at, 'العلامة تُضبط (منع التكرار)');
    }

    public function test_rescan_is_idempotent_no_duplicate(): void
    {
        [$t, , $cm] = $this->world();
        $this->version($t, $cm, 'submitted', now()->subHours(80)->toDateTimeString());

        app(ShortlistDecisionReminderService::class)->scan();
        $after1 = $this->reminderNotifs($t->id);
        $r2 = app(ShortlistDecisionReminderService::class)->scan();
        $after2 = $this->reminderNotifs($t->id);

        $this->assertSame(0, $r2['notified'], 'إعادة المسح لا تُنتج تذكيرًا ثانيًا');
        $this->assertSame($after1, $after2, 'لا إشعارات مكرّرة');
    }

    public function test_recent_submission_within_window_is_not_reminded(): void
    {
        [$t, , $cm] = $this->world();
        $this->version($t, $cm, 'submitted', now()->subHours(10)->toDateTimeString());

        $r = app(ShortlistDecisionReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->reminderNotifs($t->id));
    }

    public function test_decided_version_is_not_reminded_even_if_old(): void
    {
        [$t, , $cm] = $this->world();
        $this->version($t, $cm, 'approved', now()->subDays(5)->toDateTimeString(), now()->subDays(4)->toDateTimeString());

        $r = app(ShortlistDecisionReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->reminderNotifs($t->id));
    }
}
