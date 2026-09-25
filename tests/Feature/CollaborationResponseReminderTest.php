<?php

namespace Tests\Feature;

use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Collaborations\Services\CollaborationResponseReminderService;
use App\Domain\Communications\Models\Notification;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تذكير ردّ المؤثر على عرض التعاون: تذكير مرّة واحدة بعد مرور ٤٨ ساعة بلا ردّ، بلا تكرار
 * عند إعادة المسح، ولا تذكير لعرض حديث أو عرضٍ رُدَّ عليه. مبنيّ على offered_at الحقيقيّة.
 */
class CollaborationResponseReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User,2:Creator} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $owner = User::create(['name' => 'منسّق', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $creatorUser = User::create(['name' => 'مؤثر', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $creator = TenantContext::withTenant($t->id, fn () => Creator::create([
            'tenant_id' => $t->id, 'creator_number' => 'CR-'.Str::random(4), 'type' => 'influencer',
            'display_name' => 'نجم', 'status' => 'active', 'user_id' => $creatorUser->id,
        ]));

        return [$t, $owner, $creator];
    }

    private function collab(Tenant $t, User $owner, Creator $creator, string $status, ?string $offeredAt, ?string $respondedAt = null): Collaboration
    {
        return TenantContext::withTenant($t->id, fn () => Collaboration::create([
            'tenant_id' => $t->id, 'collaboration_number' => 'CB-'.Str::random(4), 'creator_id' => $creator->id,
            'title' => 'تعاون الصيف', 'status' => $status, 'offered_at' => $offeredAt, 'responded_at' => $respondedAt,
            'created_by' => $owner->id,
        ]));
    }

    private function reminderNotifs(int $tenantId): int
    {
        return TenantContext::withBypass(fn () => Notification::where('tenant_id', $tenantId)->where('type', 'collaboration.response_reminder')->count());
    }

    public function test_offer_pending_beyond_window_reminds_once(): void
    {
        [$t, $owner, $creator] = $this->world();
        $c = $this->collab($t, $owner, $creator, 'offered', now()->subHours(60)->toDateTimeString());

        $r = app(CollaborationResponseReminderService::class)->scan();

        $this->assertSame(1, $r['notified']);
        // المؤثر + صاحب العرض
        $this->assertSame(2, $this->reminderNotifs($t->id));
        $this->assertNotNull($c->fresh()->response_reminded_at, 'العلامة تُضبط (منع التكرار)');
    }

    public function test_rescan_is_idempotent_no_duplicate(): void
    {
        [$t, $owner, $creator] = $this->world();
        $this->collab($t, $owner, $creator, 'offered', now()->subHours(60)->toDateTimeString());

        app(CollaborationResponseReminderService::class)->scan();
        $after1 = $this->reminderNotifs($t->id);
        $r2 = app(CollaborationResponseReminderService::class)->scan();
        $after2 = $this->reminderNotifs($t->id);

        $this->assertSame(0, $r2['notified'], 'إعادة المسح لا تُنتج تذكيرًا ثانيًا');
        $this->assertSame($after1, $after2, 'لا إشعارات مكرّرة');
    }

    public function test_recent_offer_within_window_is_not_reminded(): void
    {
        [$t, $owner, $creator] = $this->world();
        $this->collab($t, $owner, $creator, 'offered', now()->subHours(6)->toDateTimeString());

        $r = app(CollaborationResponseReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->reminderNotifs($t->id));
    }

    public function test_responded_offer_is_not_reminded_even_if_old(): void
    {
        [$t, $owner, $creator] = $this->world();
        $this->collab($t, $owner, $creator, 'accepted', now()->subDays(5)->toDateTimeString(), now()->subDays(4)->toDateTimeString());

        $r = app(CollaborationResponseReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->reminderNotifs($t->id));
    }
}
