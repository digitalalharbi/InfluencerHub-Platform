<?php
namespace App\Domain\Creators\Jobs;
use App\Domain\Creators\Contracts\{OtpMailer, OtpSmsSender};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/** إرسال رمز التحقق عبر الطابور (بريد/جوال). لا يعرض الرمز للمستخدم. */
class SendOtpJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public string $channel, public string $destination, public string $code) {}
    public function handle(OtpMailer $mailer, OtpSmsSender $sms): void {
        if ($this->channel === 'email') { $mailer->send($this->destination, $this->code); }
        else { $sms->send($this->destination, $this->code); } // قد يعيد waiting_for_credentials محليًا
    }
}
