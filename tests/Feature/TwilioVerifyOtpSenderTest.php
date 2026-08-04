<?php

namespace Tests\Feature;

use App\Domain\Creators\Services\Otp\TwilioVerifySmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Tests\TestCase;

class TwilioVerifyOtpSenderTest extends TestCase
{
    public function test_it_sends_local_otp_code_through_twilio_verify_whatsapp(): void
    {
        config()->set('services.twilio.sid', 'AC123');
        config()->set('services.twilio.auth_token', 'secret');
        config()->set('services.twilio.verify_sid', 'VA123');
        config()->set('services.twilio.verify_channel', 'whatsapp');
        config()->set('services.twilio.locale', 'ar');
        config()->set('services.twilio.whatsapp_from', null);

        Log::spy();

        Http::fake([
            'verify.twilio.com/*' => Http::response(['status' => 'pending'], 201),
        ]);

        $status = (new TwilioVerifySmsSender())->send('+201234567890', '123456');

        $this->assertSame('sent', $status);
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://verify.twilio.com/v2/Services/VA123/Verifications'
            && $request['To'] === '+201234567890'
            && $request['Channel'] === 'whatsapp'
            && $request['CustomCode'] === '123456'
            && $request['Locale'] === 'ar'
        );
    }

    public function test_it_sends_local_otp_code_through_twilio_whatsapp_sandbox_number(): void
    {
        config()->set('services.twilio.sid', 'AC123');
        config()->set('services.twilio.auth_token', 'secret');
        config()->set('services.twilio.verify_sid', null);
        config()->set('services.twilio.verify_channel', 'whatsapp');
        config()->set('services.twilio.whatsapp_from', '+14155238886');

        Log::spy();

        Http::fake([
            'api.twilio.com/*' => Http::response(['status' => 'queued'], 201),
        ]);

        $status = (new TwilioVerifySmsSender())->send('+201234567890', '123456');

        $this->assertSame('sent', $status);
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
            && $request['From'] === 'whatsapp:+14155238886'
            && $request['To'] === 'whatsapp:+201234567890'
            && str_contains($request['Body'], '123456')
        );
    }


    public function test_it_normalizes_egyptian_local_mobile_numbers_for_twilio_whatsapp(): void
    {
        config()->set('services.twilio.sid', 'AC123');
        config()->set('services.twilio.auth_token', 'secret');
        config()->set('services.twilio.verify_sid', null);
        config()->set('services.twilio.verify_channel', 'whatsapp');
        config()->set('services.twilio.whatsapp_from', '+14155238886');

        Log::spy();

        Http::fake([
            'api.twilio.com/*' => Http::response(['status' => 'queued'], 201),
        ]);

        $status = (new TwilioVerifySmsSender())->send('+01090962585', '123456');

        $this->assertSame('sent', $status);
        Http::assertSent(fn ($request) => $request['To'] === 'whatsapp:+201090962585');
    }
    public function test_it_requires_e164_phone_numbers(): void
    {
        config()->set('services.twilio.sid', 'AC123');
        config()->set('services.twilio.auth_token', 'secret');
        config()->set('services.twilio.verify_sid', 'VA123');

        $this->expectException(InvalidArgumentException::class);

        (new TwilioVerifySmsSender())->send('12345', '123456');
    }
}