<?php
namespace App\Domain\Creators\Services\Otp;
use App\Domain\Creators\Contracts\OtpMailer;
use Illuminate\Support\Facades\Log;
/** بديل محلي: يسجّل الرمز في اللوق بدل إرسال بريد فعلي (بيئة تطوير بلا SMTP). */
class LogOtpMailer implements OtpMailer {
    public function send(string $email, string $code): void { Log::info("[OTP][email] to={$email} code={$code}"); }
}
