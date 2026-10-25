<?php

namespace Tests\Feature;

use App\Domain\Communications\Inbox\CommsConnectionService;
use App\Domain\Communications\Inbox\ContactMatcher;
use App\Domain\Communications\Inbox\ConversationService;
use App\Domain\Communications\Inbox\InboundMessageService;
use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorTimeline;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\IntegrationExternalEvent;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * COMMS-01 — طبقة الرسائل ثنائيّة الاتّجاه فوق أساس التكاملات. مُختبَرة بلا شبكة/اعتماد:
 * ابتلاع وارد idempotent، مطابقة المرسِل بمبدع/عميل، أحداث حقيقيّة تغذّي الخطّ الزمني،
 * إرسال صادر يرفع الجاهزية، وتمثيل القنوات على السلّم من إعداد حقيقيّ. لا تلفيق مستأجر/مطابقة.
 */
class CommunicationsInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0:Tenant,1:Creator} */
    private function tenantWithCreator(string $phone = '+966500000001'): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        $c = TenantContext::withTenant($t->id, fn () => Creator::create([
            'tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer',
            'display_name' => 'مبدع', 'status' => 'active', 'phone' => $phone, 'whatsapp' => $phone,
        ]));

        return [$t, $c];
    }

    private function inboundMessage(string $from, string $id, string $text): array
    {
        return ['id' => $id, 'from' => $from, 'type' => 'text', 'timestamp' => (string) now()->timestamp, 'text' => ['body' => $text]];
    }

    public function test_inbound_message_matched_to_creator_creates_timeline_event(): void
    {
        [$t, $c] = $this->tenantWithCreator('+966500000001');
        $svc = app(InboundMessageService::class);

        $ev = $svc->ingest($this->inboundMessage('966500000001', 'wamid.in1', 'مرحبًا، مهتمّ بالحملة'));
        $this->assertNotNull($ev);
        $this->assertSame('message_in', $ev->kind);
        $this->assertSame($c->id, $ev->creator_id);
        $this->assertSame('inbound', $ev->direction);

        $this->assertDatabaseHas('integration_webhook_events', ['provider' => 'whatsapp_cloud', 'event_id' => 'wamid.in1', 'status' => 'processed']);

        $timeline = TenantContext::withTenant($t->id, fn () => CreatorTimeline::for($c->fresh()));
        $this->assertTrue(collect($timeline)->contains(fn ($e) => $e['type'] === 'ext_whatsapp' && $e['icon'] === 'message-circle'));
    }

    public function test_inbound_is_idempotent_on_message_id(): void
    {
        [$t] = $this->tenantWithCreator('+966500000002');
        $svc = app(InboundMessageService::class);

        $first = $svc->ingest($this->inboundMessage('966500000002', 'wamid.dup', 'أولى'));
        $second = $svc->ingest($this->inboundMessage('966500000002', 'wamid.dup', 'ثانية'));

        $this->assertNotNull($first);
        $this->assertNull($second); // تكرار يُرفض
        $this->assertSame(1, TenantContext::withTenant($t->id, fn () => IntegrationExternalEvent::where('external_id', 'wamid.dup')->count()));
    }

    public function test_inbound_unmatched_sender_records_no_timeline_event(): void
    {
        $this->tenantWithCreator('+966500000003');
        $svc = app(InboundMessageService::class);

        $ev = $svc->ingest($this->inboundMessage('966599999999', 'wamid.nomatch', 'من مجهول'));
        $this->assertNull($ev);
        $this->assertSame(0, IntegrationExternalEvent::where('external_id', 'wamid.nomatch')->count());
        $this->assertDatabaseHas('integration_webhook_events', ['provider' => 'whatsapp_cloud', 'event_id' => 'wamid.nomatch', 'status' => 'skipped']);
    }

    public function test_contact_matcher_normalizes_phone_digits(): void
    {
        [$t, $c] = $this->tenantWithCreator('+966 50-000 0004');
        $match = app(ContactMatcher::class)->byPhone($t->id, '966500000004');
        $this->assertSame($c->id, $match['creator_id']);
    }

    public function test_outbound_whatsapp_text_records_event_and_marks_connection_live(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out1']]], 200)]);
        config(['channels.whatsapp.enabled' => true, 'channels.whatsapp.phone_number_id' => 'PNID', 'channels.whatsapp.access_token' => 'TOK']);
        [$t, $c] = $this->tenantWithCreator('+966500000005');

        $out = app(ConversationService::class)->sendWhatsAppText($t->id, '966500000005', 'شكرًا لتعاونك');
        $this->assertSame('wamid.out1', $out['message_id']);

        $ev = TenantContext::withTenant($t->id, fn () => IntegrationExternalEvent::where('external_id', 'wamid.out1')->first());
        $this->assertSame('message_out', $ev->kind);
        $this->assertSame($c->id, $ev->creator_id);

        $conn = TenantContext::withTenant($t->id, fn () => IntegrationConnection::where('provider', 'whatsapp_cloud')->first());
        $this->assertSame('LIVE_WRITE_VERIFIED', $conn->readiness());
    }

    public function test_comms_connection_reflects_config_without_inventing(): void
    {
        [$t] = $this->tenantWithCreator();
        config(['channels.whatsapp.enabled' => false, 'channels.email.enabled' => false, 'channels.sms.enabled' => false]);
        app(CommsConnectionService::class)->syncFromConfig($t->id);

        $wa = TenantContext::withTenant($t->id, fn () => IntegrationConnection::where('provider', 'whatsapp_cloud')->first());
        $this->assertSame('waiting_for_credentials', $wa->status);
        $this->assertSame('AWAITING_CREDENTIALS', $wa->readiness());
    }

    public function test_webhook_rejects_invalid_signature_and_accepts_signed_inbound(): void
    {
        config(['channels.whatsapp.app_secret' => 'shhh']);
        [$t, $c] = $this->tenantWithCreator('+966500000006');

        $payload = ['entry' => [['changes' => [['value' => ['messages' => [
            $this->inboundMessage('966500000006', 'wamid.http1', 'عبر الويبهوك'),
        ]]]]]]];
        $body = json_encode($payload);

        // توقيع خاطئ ⇒ 403
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], ['HTTP_X-Hub-Signature-256' => 'sha256=bad', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(403);

        // توقيع صحيح ⇒ 200 + ابتلاع
        $sig = 'sha256='.hash_hmac('sha256', $body, 'shhh');
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], ['HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();

        $this->assertSame(1, TenantContext::withTenant($t->id, fn () => IntegrationExternalEvent::where('external_id', 'wamid.http1')->where('creator_id', $c->id)->count()));
    }
}
