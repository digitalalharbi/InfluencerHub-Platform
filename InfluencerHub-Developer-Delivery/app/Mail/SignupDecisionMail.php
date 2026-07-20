<?php

namespace App\Mail;

use App\Domain\Onboarding\Models\SignupRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * إبلاغ مقدّم الطلب بقرار المراجعة.
 *
 * صنف بريد لا Mail::raw: الإرسال الخام لا يُرصَد في الاختبارات، فكان الإشعار
 * يمرّ بلا تحقّق. وهو أيضًا قابل للجدولة في الطابور وللقولبة لاحقًا.
 */
class SignupDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SignupRequest $signupRequest,
        public bool $approved,
        public ?string $setupUrl = null,
        public ?string $reason = null,
    ) {
    }

    public function build(): self
    {
        $s = $this->signupRequest;

        if ($this->approved) {
            return $this->subject('اعتُمد طلبك — إنفلونسر هَب')->text('mail.signup-approved', [
                'name' => $s->contact_name, 'company' => $s->company_name, 'url' => $this->setupUrl,
            ]);
        }

        return $this->subject('بخصوص طلبك — إنفلونسر هَب')->text('mail.signup-rejected', [
            'name' => $s->contact_name, 'company' => $s->company_name, 'reason' => $this->reason,
        ]);
    }
}
