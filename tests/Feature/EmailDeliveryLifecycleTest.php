<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\Notification;
use App\Domain\Communications\Models\NotificationDeliveryAttempt;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Mail\NotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * دورة حياة تسليم البريد: queued → sent من حدث النقل الفعليّ (MessageSent)، بلا ادّعاء
 * «delivered» (يلزم webhook مزوّد). آمن التكرار: يقدّم فقط ما زال «queued».
 */
class EmailDeliveryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_message_sent_advances_email_attempt_queued_to_sent(): void
    {
        config(['mail.default' => 'array']); // نقل حقيقيّ (لا Mail::fake) كي يُطلَق MessageSent

        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        [$u, $n, $attempt] = TenantContext::withBypass(function () use ($t) {
            Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'محمد', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true, 'locale' => 'ar']);
            $n = Notification::create(['tenant_id' => $t->id, 'user_id' => $u->id, 'type' => 'x', 'category' => 'general', 'title' => 'عنوان', 'action_url' => '/app/x']);
            // صفّ محاولة بريد «queued» كما يسجّله الموزّع قبل إرسال العامل
            $attempt = NotificationDeliveryAttempt::create([
                'tenant_id' => $t->id, 'notification_id' => $n->id, 'channel' => 'email', 'provider' => 'array',
                'recipient' => $u->email, 'status' => 'queued', 'queued_at' => now(),
            ]);

            return [$u, $n, $attempt];
        });

        // إرسال حقيقيّ → MessageSent → المستمع يقدّم الحالة
        Mail::to($u->email)->send(new NotificationMail($n));

        $this->assertSame('sent', $attempt->fresh()->status, 'تقدّمت الحالة queued→sent من حدث النقل');
    }

    public function test_no_header_or_no_queued_attempt_is_a_noop(): void
    {
        config(['mail.default' => 'array']);
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        [$u, $n, $attempt] = TenantContext::withBypass(function () use ($t) {
            Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
            $u = User::create(['name' => 'م', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
            $n = Notification::create(['tenant_id' => $t->id, 'user_id' => $u->id, 'type' => 'x', 'category' => 'general', 'title' => 'ع']);
            // محاولة سبق تسليمها (sent) — يجب ألّا تُلمَس (آمن التكرار)
            $attempt = NotificationDeliveryAttempt::create([
                'tenant_id' => $t->id, 'notification_id' => $n->id, 'channel' => 'email', 'provider' => 'array',
                'recipient' => $u->email, 'status' => 'sent', 'provider_message_id' => 'orig-id',
            ]);

            return [$u, $n, $attempt];
        });

        Mail::to($u->email)->send(new NotificationMail($n));

        $fresh = $attempt->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertSame('orig-id', $fresh->provider_message_id, 'المحاولة المُسلَّمة لا تُعاد الكتابة عليها');
    }
}
