<?php
namespace App\Domain\Creators\Contracts;
interface OtpSmsSender {
    /** يعيد الحالة: sent | waiting_for_credentials. لا يدّعي الإرسال إن لم يوجد مزوّد. */
    public function send(string $phone, string $code): string;
}
