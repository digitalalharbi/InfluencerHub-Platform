<?php

namespace App\Domain\Creators\Services\Otp;

use App\Domain\Creators\Contracts\OtpSmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class TwilioVerifySmsSender implements OtpSmsSender
{
    public function send(string $phone, string $code): string
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.auth_token');
        $serviceSid = (string) config('services.twilio.verify_sid');
        $channel = (string) config('services.twilio.verify_channel', 'whatsapp');
        $locale = (string) config('services.twilio.locale', 'ar');

        if ($sid === '' || $token === '' || $serviceSid === '') {
            Log::info('[OTP][twilio] WAITING_FOR_CREDENTIALS phone=' . $this->maskPhone($phone));

            return 'waiting_for_credentials';
        }

        $to = $this->normalizePhone($phone);
        $url = "https://verify.twilio.com/v2/Services/{$serviceSid}/Verifications";

        $payload = [
            'To' => $to,
            'Channel' => $channel,
            'CustomCode' => $code,
        ];

        if ($locale !== '') {
            $payload['Locale'] = $locale;
        }

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->timeout(15)
            ->retry(2, 250)
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::warning('[OTP][twilio] send_failed', [
                'phone' => $this->maskPhone($to),
                'channel' => $channel,
                'status' => $response->status(),
                'twilio_code' => $response->json('code'),
                'twilio_message' => $response->json('message'),
            ]);

            throw new RuntimeException('???? ????? ??? ?????? ??? Twilio.');
        }

        Log::info('[OTP][twilio] sent', [
            'phone' => $this->maskPhone($to),
            'channel' => $channel,
            'status' => $response->json('status'),
        ]);

        return 'sent';
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $phone = str_replace([' ', '-', '(', ')'], '', $phone);

        if (! str_starts_with($phone, '+')) {
            throw new InvalidArgumentException('Twilio requires phone numbers in E.164 format, for example +201234567890.');
        }

        return $phone;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) <= 4) {
            return '****';
        }

        return '+' . str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }
}