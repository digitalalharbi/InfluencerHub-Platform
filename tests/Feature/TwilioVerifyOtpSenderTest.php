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

    public function test_it_requires_e164_phone_numbers(): void
    {
        config()->set('services.twilio.sid', 'AC123');
        config()->set('services.twilio.auth_token', 'secret');
        config()->set('services.twilio.verify_sid', 'VA123');

        $this->expectException(InvalidArgumentException::class);

        (new TwilioVerifySmsSender())->send('01234567890', '123456');
    }
}