<?php

namespace Tests\Feature;

use App\Domain\Communications\Channels\{ChannelRegistry, DeliveryChannel, InAppChannel};
use App\Domain\Communications\Models\{Notification, NotificationDeliveryAttempt};
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Communications\Support\DeliveryOutcome;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * نواة طبقة التسليم: القنوات محايدة للمزوّد، والحالات صادقة، ومحاولة التسليم
 * تحمل مزوّدًا ومستلِمًا ومعرّف رسالة وطوابع زمنية. القناة الخارجية لا تُرسِل
 * ما لم تكن available()؛ والمتاحة تُسجّل معرّف مزوّد.
 */
class NotificationDeliveryCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function bootup(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $u = TenantContext::withBypass(fn () => User::create([
            'name' => 'مستخدم', 'email' => Str::random(6) . '@ex.com', 'phone' => '0500000000',
            'password' => bcrypt('x'), 'is_active' => true,
        ]));
        return [$t, $u];
    }

    private function attempts(Notification $n)
    {
        return TenantContext::withBypass(fn () => NotificationDeliveryAttempt::where('notification_id', $n->id)->get()->keyBy('channel'));
    }

    public function test_in_app_always_delivered_with_provider(): void
    {
        [$t, $u] = $this->bootup();
        $n = app(NotificationService::class)->notify($t->id, $u->id, 'x.event', 'general', 'عنوان', 'نص');
        $a = $this->attempts($n);
        $this->assertSame('sent', $a['in_app']->status);
        $this->assertSame('in_app', $a['in_app']->provider);
        $this->assertNotNull($a['in_app']->delivered_at);
    }

    public function test_external_channels_wait_for_credentials_when_disabled_by_default(): void
    {
        [$t, $u] = $this->bootup();
        // فعّل كل القنوات لهذا المستخدم/الفئة
        app(NotificationService::class)->setPreference($t->id, $u->id, 'general', true, true, true, true);
        $n = app(NotificationService::class)->notify($t->id, $u->id, 'x.event', 'general', 'عنوان');
        $a = $this->attempts($n);
        // القنوات الخارجية غير مهيّأة افتراضيًا → حالة صادقة، لا تسليم وهمي
        $this->assertSame('waiting_for_credentials', $a['email']->status);
        $this->assertSame('waiting_for_credentials', $a['whatsapp']->status);
        $this->assertSame('waiting_for_credentials', $a['sms']->status);
        // المستلِم مُسجَّل رغم عدم الإرسال (بريد/هاتف المستخدم)
        $this->assertSame($u->email, $a['email']->recipient);
        $this->assertSame($u->phone, $a['whatsapp']->recipient);
    }

    public function test_available_channel_sends_and_records_provider_message_id(): void
    {
        [$t, $u] = $this->bootup();
        // استبدل السجلّ بقناة بريد مزيّفة متاحة تُعيد معرّف رسالة
        $fakeEmail = new class implements DeliveryChannel {
            public function key(): string { return 'email'; }
            public function provider(): string { return 'fake_smtp'; }
            public function available(): bool { return true; }
            public function recipientFor(User $user): ?string { return $user->email; }
            public function send(Notification $n, User $u, string $r): DeliveryOutcome { return DeliveryOutcome::sent('msg-123', 'أُرسل'); }
        };
        $this->app->singleton(ChannelRegistry::class, fn () => new ChannelRegistry([new InAppChannel, $fakeEmail]));

        app(NotificationService::class)->setPreference($t->id, $u->id, 'general', true, true, false, false);
        $n = app(NotificationService::class)->notify($t->id, $u->id, 'x.event', 'general', 'عنوان');
        $a = $this->attempts($n);
        $this->assertSame('sent', $a['email']->status);
        $this->assertSame('fake_smtp', $a['email']->provider);
        $this->assertSame('msg-123', $a['email']->provider_message_id);
        $this->assertNotNull($a['email']->delivered_at);
    }

    public function test_channel_send_failure_does_not_break_notification(): void
    {
        [$t, $u] = $this->bootup();
        $boom = new class implements DeliveryChannel {
            public function key(): string { return 'email'; }
            public function provider(): string { return 'fake_smtp'; }
            public function available(): bool { return true; }
            public function recipientFor(User $user): ?string { return $user->email; }
            public function send(Notification $n, User $u, string $r): DeliveryOutcome { throw new \RuntimeException('provider down'); }
        };
        $this->app->singleton(ChannelRegistry::class, fn () => new ChannelRegistry([new InAppChannel, $boom]));

        app(NotificationService::class)->setPreference($t->id, $u->id, 'general', true, true, false, false);
        $n = app(NotificationService::class)->notify($t->id, $u->id, 'x.event', 'general', 'عنوان');
        // الإشعار أُنشئ وسُلِّم داخل التطبيق رغم فشل القناة الخارجية
        $a = $this->attempts($n);
        $this->assertSame('sent', $a['in_app']->status);
        $this->assertSame('failed', $a['email']->status);
        $this->assertNotNull($a['email']->failed_at);
    }

    public function test_disabled_in_app_records_skipped(): void
    {
        [$t, $u] = $this->bootup();
        app(NotificationService::class)->setPreference($t->id, $u->id, 'general', false, false, false, false);
        $n = app(NotificationService::class)->notify($t->id, $u->id, 'x.event', 'general', 'عنوان');
        $a = $this->attempts($n);
        $this->assertSame('skipped', $a['in_app']->status);
    }
}
