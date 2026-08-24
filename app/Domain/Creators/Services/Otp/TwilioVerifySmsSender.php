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
        $whatsappFrom = (string) config('services.twilio.whatsapp_from');

        if ($sid === '' || $token === '' || ($serviceSid === '' && $whatsappFrom === '')) {
            Log::info('[OTP][twilio] WAITING_FOR_CREDENTIALS phone=' . $this->maskPhone($phone));

            return 'waiting_for_credentials';
        }

        $to = $this->normalizePhone($phone);

        if ($channel === 'whatsapp' && $whatsappFrom !== '') {
            return $this->sendWhatsAppMessage($sid, $token, $whatsappFrom, $to, $code);
        }

        if ($serviceSid === '') {
            Log::info('[OTP][twilio] WAITING_FOR_VERIFY_SERVICE phone=' . $this->maskPhone($phone));

            return 'waiting_for_credentials';
        }

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

            throw new RuntimeException('تعذر إرسال رمز التحقق عبر Twilio.');
        }

        Log::info('[OTP][twilio] sent', [
            'phone' => $this->maskPhone($to),
            'channel' => $channel,
            'status' => $response->json('status'),
        ]);

        return 'sent';
    }

    private function sendWhatsAppMessage(string $sid, string $token, string $from, string $to, string $code): string
    {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $from = $this->normalizeWhatsAppAddress($from);
        $to = $this->normalizeWhatsAppAddress($to);

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->timeout(15)
            ->retry(2, 250)
            ->post($url, [
                'From' => $from,
                'To' => $to,
                'Body' => "رمز التحقق الخاص بك في InfluencerHub هو: {$code}",
            ]);

        if (! $response->successful()) {
            Log::warning('[OTP][twilio] whatsapp_send_failed', [
                'phone' => $this->maskPhone($to),
                'from' => $from,
                'status' => $response->status(),
                'twilio_code' => $response->json('code'),
                'twilio_message' => $response->json('message'),
            ]);

            throw new RuntimeException('تعذر إرسال رمز التحقق عبر واتساب.');
        }

        Log::info('[OTP][twilio] whatsapp_sent', [
            'phone' => $this->maskPhone($to),
            'from' => $from,
            'status' => $response->json('status'),
        ]);

        return 'sent';
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $phone = str_replace([' ', '-', '(', ')'], '', $phone);

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        if (preg_match('/^\+?01[0125][0-9]{8}$/', $phone) === 1) {
            $digits = ltrim($phone, '+');
            $phone = '+20' . substr($digits, 1);
        }

        if (! str_starts_with($phone, '+')) {
            throw new InvalidArgumentException('Twilio requires phone numbers in E.164 format, for example +201234567890.');
        }

        return $phone;
    }

    private function normalizeWhatsAppAddress(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, 'whatsapp:')) {
            return $phone;
        }

        return 'whatsapp:' . $this->normalizePhone($phone);
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
