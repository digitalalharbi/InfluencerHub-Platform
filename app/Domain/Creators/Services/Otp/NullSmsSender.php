<?php
namespace App\Domain\Creators\Services\Otp;
use App\Domain\Creators\Contracts\OtpSmsSender;
use Illuminate\Support\Facades\Log;
class NullSmsSender implements OtpSmsSender {
    public function send(string $phone, string $code): string {
        Log::info("[OTP][sms] WAITING_FOR_CREDENTIALS phone={$phone} (لا مزوّد SMS مُعَدّ)");
        return 'waiting_for_credentials';
    }
}
