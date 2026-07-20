<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;
class CreatorOtpMail extends Mailable {
    use Queueable, SerializesModels;
    public function __construct(public string $code) {}
    public function envelope(): Envelope { return new Envelope(subject: 'رمز التحقق — إنفلونسر هَب'); }
    public function content(): Content { return new Content(view: 'mail.otp', with: ['code' => $this->code]); }
}
