<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\Notification;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\ContentPublishReminderService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تذكير موعد نشر المحتوى: تذكير مرّة واحدة حين يقترب/يفوت موعد نشر محتوى مُجدوَل ولم
 * يُنشَر، بلا تكرار عند إعادة المسح، ولا إشعار للبعيد موعده أو المنشور. مبنيّ على
 * scheduled_at الحقيقيّة لا على موعد مُختلَق.
 */
class ContentPublishReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:User} */
    private function world(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $u = User::create(['name' => 'منسّق', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);

        return [$t, $u];
    }

    private function content(Tenant $t, User $owner, string $status, ?string $scheduledAt, ?string $publishedAt = null): ContentItem
    {
        return TenantContext::withTenant($t->id, fn () => ContentItem::create([
            'tenant_id' => $t->id, 'content_number' => 'CT-'.Str::random(5), 'title' => 'إعلان الصيف',
            'type' => 'post', 'platform' => 'instagram', 'status' => $status,
            'scheduled_at' => $scheduledAt, 'published_at' => $publishedAt, 'created_by' => $owner->id,
        ]));
    }

    private function reminderNotifs(int $tenantId): int
    {
        return TenantContext::withBypass(fn () => Notification::where('tenant_id', $tenantId)->where('type', 'content.publish_reminder')->count());
    }

    public function test_scheduled_content_due_soon_notifies_once(): void
    {
        [$t, $owner] = $this->world();
        $item = $this->content($t, $owner, 'scheduled', now()->addHours(3)->toDateTimeString());

        $r = app(ContentPublishReminderService::class)->scan();

        $this->assertSame(1, $r['notified']);
        $this->assertGreaterThan(0, $this->reminderNotifs($t->id));
        $this->assertNotNull($item->fresh()->publish_reminded_at, 'العلامة تُضبط (منع التكرار)');
    }

    public function test_overdue_unpublished_scheduled_content_notifies(): void
    {
        [$t, $owner] = $this->world();
        $this->content($t, $owner, 'scheduled', now()->subHours(6)->toDateTimeString());

        $r = app(ContentPublishReminderService::class)->scan();

        $this->assertSame(1, $r['notified']);
    }

    public function test_rescan_is_idempotent_no_duplicate(): void
    {
        [$t, $owner] = $this->world();
        $this->content($t, $owner, 'scheduled', now()->addHours(3)->toDateTimeString());

        app(ContentPublishReminderService::class)->scan();
        $after1 = $this->reminderNotifs($t->id);
        $r2 = app(ContentPublishReminderService::class)->scan();
        $after2 = $this->reminderNotifs($t->id);

        $this->assertSame(0, $r2['notified'], 'إعادة المسح لا تُنتج تذكيرًا ثانيًا');
        $this->assertSame($after1, $after2, 'لا إشعارات مكرّرة');
    }

    public function test_far_future_scheduled_content_is_not_notified(): void
    {
        [$t, $owner] = $this->world();
        $this->content($t, $owner, 'scheduled', now()->addDays(5)->toDateTimeString());

        $r = app(ContentPublishReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->reminderNotifs($t->id));
    }

    public function test_published_content_is_not_notified_even_if_past_schedule(): void
    {
        [$t, $owner] = $this->world();
        $this->content($t, $owner, 'published', now()->subDays(2)->toDateTimeString(), now()->subDay()->toDateTimeString());

        $r = app(ContentPublishReminderService::class)->scan();

        $this->assertSame(0, $r['notified']);
        $this->assertSame(0, $this->reminderNotifs($t->id));
    }
}
