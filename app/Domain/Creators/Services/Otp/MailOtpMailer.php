<?php
namespace App\Domain\Creators\Services\Otp;
use App\Domain\Creators\Contracts\OtpMailer;
use App\Mail\CreatorOtpMail;
use Illuminate\Support\Facades\Mail;
/** مزوّد بريد فعلي عبر Laravel Mail (يعمل مع أي transport مُعَدّ). */
class MailOtpMailer implements OtpMailer {
    public function send(string $email, string $code): void { Mail::to($email)->send(new CreatorOtpMail($code)); }
}
