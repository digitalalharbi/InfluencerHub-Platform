<?php

namespace Tests\Feature;

use App\Domain\Communications\Models\{Notification, NotificationDeliveryAttempt};
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Communications\WhatsApp\WhatsAppNumber;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * مزوّد واتساب (Cloud API): تطبيع الأرقام، الإرسال القالبي عبر Http (مزيّف)،
 * وويبهوك الحالة بتحقّق التوقيع والحماية من التنازل. الإرسال الحيّ محجوب خارجيًا
 * (لا بيانات اعتماد Meta) لكن كامل المسار مُختبَر داخليًا.
 */
class WhatsAppProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { TenantContext::reset(); parent::tearDown(); }

    public function test_number_normalization(): void
    {
        $this->assertSame('966501234567', WhatsAppNumber::normalize('0501234567'));
        $this->assertSame('966501234567', WhatsAppNumber::normalize('501234567'));
        $this->assertSame('966501234567', WhatsAppNumber::normalize('+966 50 123 4567'));
        $this->assertSame('966501234567', WhatsAppNumber::normalize('00966501234567'));
        $this->assertNull(WhatsAppNumber::normalize('123'));
        $this->assertNull(WhatsAppNumber::normalize(''));
        $this->assertNull(WhatsAppNumber::normalize(null));
    }

    private function make(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $u = TenantContext::withBypass(fn () => User::create([
            'name' => 'مستخدم', 'email' => 'u@ex.com', 'phone' => '0501234567', 'password' => bcrypt('x'), 'is_active' => true,
        ]));
        return [$t, $u];
    }

    public function test_enabled_channel_sends_template_and_records_message_id(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC123']]], 200)]);
        config([
            'channels.whatsapp.enabled' => true,
            'channels.whatsapp.phone_number_id' => '111',
            'channels.whatsapp.access_token' => 'tok',
        ]);
        [$t, $u] = $this->make();
        $svc = app(NotificationService::class);
        $svc->setPreference($t->id, $u->id, 'general', true, false, false, true);

        $n = $svc->notify($t->id, $u->id, 'service_request.assigned', 'general', 'أُسند إليك طلب', 'SR-1-3', '/app/service-requests/3');

        $a = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::where('notification_id', $n->id)->where('channel', 'whatsapp')->first());
        $this->assertSame('queued', $a->status);
        $this->assertSame('whatsapp_cloud', $a->provider);
        $this->assertSame('wamid.ABC123', $a->provider_message_id);
        $this->assertSame('966501234567', $a->recipient);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/111/messages') && $req['type'] === 'template');
    }

    public function test_webhook_verify_challenge(): void
    {
        config(['channels.whatsapp.verify_token' => 'v-secret']);
        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=v-secret&hub_challenge=CH123')
            ->assertOk()->assertSee('CH123');
        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=CH123')
            ->assertForbidden();
    }

    public function test_webhook_status_updates_attempt_with_valid_signature(): void
    {
        config(['channels.whatsapp.app_secret' => 'app-secret']);
        [$t, $u] = $this->make();
        $n = TenantContext::withBypass(fn () => Notification::create([
            'tenant_id' => $t->id, 'user_id' => $u->id, 'type' => 'x', 'category' => 'general', 'title' => 't',
        ]));
        $attempt = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::create([
            'tenant_id' => $t->id, 'notification_id' => $n->id, 'channel' => 'whatsapp', 'provider' => 'whatsapp_cloud',
            'provider_message_id' => 'wamid.XYZ', 'status' => 'queued', 'attempted_at' => now(),
        ]));

        $body = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [
            ['id' => 'wamid.XYZ', 'status' => 'delivered', 'timestamp' => '1700000000'],
        ]]]]]]]);
        $sig = 'sha256=' . hash_hmac('sha256', $body, 'app-secret');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $fresh = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::find($attempt->id));
        $this->assertSame('delivered', $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['channels.whatsapp.app_secret' => 'app-secret']);
        $body = json_encode(['entry' => []]);
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'HTTP_X-Hub-Signature-256' => 'sha256=deadbeef', 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertForbidden();
    }

    public function test_webhook_is_idempotent_no_downgrade(): void
    {
        config(['channels.whatsapp.app_secret' => 'app-secret']);
        [$t, $u] = $this->make();
        $n = TenantContext::withBypass(fn () => Notification::create(['tenant_id' => $t->id, 'user_id' => $u->id, 'type' => 'x', 'category' => 'general', 'title' => 't']));
        $attempt = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::create([
            'tenant_id' => $t->id, 'notification_id' => $n->id, 'channel' => 'whatsapp', 'provider' => 'whatsapp_cloud',
            'provider_message_id' => 'wamid.R', 'status' => 'read', 'attempted_at' => now(),
        ]));
        // حدث "sent" متأخّر يجب ألّا يُنزل الحالة من read
        $body = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.R', 'status' => 'sent']]]]]]]]);
        $sig = 'sha256=' . hash_hmac('sha256', $body, 'app-secret');
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], ['HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();

        $fresh = TenantContext::withBypass(fn () => NotificationDeliveryAttempt::find($attempt->id));
        $this->assertSame('read', $fresh->status);
    }
}
