<?php
namespace App\Domain\Creators\Services\Otp;
use App\Domain\Creators\Contracts\OtpMailer;
class FakeOtpMailer implements OtpMailer {
    public static array $sent = [];
    public function send(string $email, string $code): void { self::$sent[] = ['email' => $email, 'code' => $code]; }
}
