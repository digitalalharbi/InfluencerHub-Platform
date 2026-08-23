<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\{Notification, NotificationDeliveryAttempt};
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Mail\NotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * مزوّد البريد الفعلي: حين تُفعَّل القناة، الإشعار يُرسِل NotificationMail إلى بريد
 * المستخدم عبر Laravel Mail، وتُسجَّل محاولة تسليم queued بمزوّد الـ mailer الحقيقي.
 * القالب عربي RTL موسوم ويحوي الرابط العميق. حين تُعطَّل: لا بريد (waiting).
 */
class EmailProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    private function make(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $u = TenantContext::withBypass(fn () => User::create([
            'name' => 'مستخدم', 'email' => 'to@example.com', 'phone' => '0500000000', 'password' => bcrypt('x'), 'is_active' => true,
        ]));
        return [$t, $u];
    }

    public function test_enabled_email_channel_sends_notification_mail(): void
    {
        Mail::fake();
        config(['channels.email.enabled' => true, 'mail.default' => 'smtp']);
        [$t, $u] = $this->make();
        $svc = app(NotificationService::class);
        $svc->setPreference($t->id, $u->id, 'general', true, true, false, false);

        $n = $svc->notify($t->id, $u->id, 'content.approved', 'general', 'اعتُمد محتواك', 'المحتوى #CN-1-8', '/app/content/8');

        Mail::assertQueued(NotificationMail::class, fn (NotificationMail $m) => $m->hasTo('to@example.com') && $m->notification->id === $n->id);

        $attempt = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::where('notification_id', $n->id)->where('channel', 'email')->first());
        $this->assertSame('queued', $attempt->status);
        $this->assertSame('smtp', $attempt->provider);
        $this->assertSame('to@example.com', $attempt->recipient);
    }

    public function test_disabled_email_channel_sends_no_mail(): void
    {
        Mail::fake();
        config(['channels.email.enabled' => false]);
        [$t, $u] = $this->make();
        $svc = app(NotificationService::class);
        $svc->setPreference($t->id, $u->id, 'general', true, true, false, false);

        $n = $svc->notify($t->id, $u->id, 'content.approved', 'general', 'عنوان');

        Mail::assertNothingQueued();
        $attempt = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::where('notification_id', $n->id)->where('channel', 'email')->first());
        $this->assertSame('waiting_for_credentials', $attempt->status);
    }

    public function test_notification_mail_renders_rtl_arabic_with_cta(): void
    {
        [$t, $u] = $this->make();
        $n = TenantContext::withBypass(fn () => Notification::create([
            'tenant_id' => $t->id, 'user_id' => $u->id, 'type' => 'content.approved', 'category' => 'general',
            'title' => 'اعتُمد محتواك', 'body' => 'المحتوى جاهز للنشر', 'action_url' => '/app/content/8',
        ]));
        config(['app.url' => 'https://influencerhub.io']);

        $html = (new NotificationMail($n))->render();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('اعتُمد محتواك', $html);
        $this->assertStringContainsString('إنفلونسر هَب', $html);
        $this->assertStringContainsString('https://influencerhub.io/app/content/8', $html);
        $this->assertStringContainsString('فتح في إنفلونسر هَب', $html);
    }
}
