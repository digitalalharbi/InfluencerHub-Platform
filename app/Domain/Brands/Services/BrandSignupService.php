<?php

namespace App\Domain\Brands\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Brands\Models\BrandSignup;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * رحلة تسجيل العلامة حتّى لحظة التزويد.
 *
 * الرحلة: بريد ← رمز ← جوال ← رمز ← بيانات المؤسسة والعلامة ← مطابقة.
 *
 * ## قرارات الأمان، ولماذا
 *
 * **الرموز مُجزَّأة.** `creator_invitations` تخزّنها نصًّا صريحًا، وذاك مقبول
 * هناك: دعوة يُرسلها موظّف إلى شخص يعرفه. وهذه بوّابة عامّة، فنسخةٌ من قاعدة
 * البيانات لا يجوز أن تُسلّم رموزًا صالحة.
 *
 * **لكلّ قناة عدّاد محاولات.** رمز من ستّ خانات بلا حدّ يُخمَّن في ألف محاولة.
 * والعدّاد لكلّ قناة على حدة: من أخطأ في رمز بريده لا يُقفَل عليه جواله.
 *
 * **لا يُكشف وجود بريد.** `start` تُنشئ سجلًّا جديدًا دائمًا ولا تقول «هذا
 * البريد مسجَّل». والرسالة للمستخدم واحدة مهما كانت الحال.
 */
class BrandSignupService
{
    public const CODE_TTL_MINUTES = 15;

    public const SIGNUP_TTL_HOURS = 24;

    public const MAX_ATTEMPTS = 5;

    public const MAX_SENDS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * يبدأ الرحلة ويعيد [السجلّ، الرمز الخام].
     *
     * الرمز يُعاد للمستدعي مرّة واحدة ولا يُخزَّن — المستدعي يرسله ثم ينساه.
     */
    public function start(string $email, ?string $ip = null): array
    {
        $code = $this->code();

        $signup = BrandSignup::create([
            'reference' => (string) Str::uuid(),
            'email' => Str::lower(trim($email)),
            'email_code_hash' => Hash::make($code),
            'expires_at' => now()->addHours(self::SIGNUP_TTL_HOURS),
            'status' => 'email_pending',
            'last_sent_at' => now(),
            'ip' => $ip,
        ]);

        AuditLogger::log('brand_signup.started', $signup, [], null, null, ['email' => $signup->email]);

        return [$signup, $code];
    }

    /** يتحقّق من رمز البريد. */
    public function verifyEmail(BrandSignup $signup, string $code): void
    {
        $this->verifyChannel($signup, 'email', $code);

        if ($signup->status === 'email_pending') {
            $signup->update(['status' => 'phone_pending']);
        }
    }

    /** يسجّل الجوال ويولّد رمزه. يعيد الرمز الخام. */
    public function startPhone(BrandSignup $signup, string $phone): string
    {
        $this->assertUsable($signup);

        if (! $signup->emailVerified()) {
            throw new RuntimeException('تحقّق من بريدك أوّلًا.');
        }

        $code = $this->code();

        $signup->update([
            'phone' => trim($phone),
            'phone_code_hash' => Hash::make($code),
            'phone_verified_at' => null,
            'phone_attempts' => 0,
            'last_sent_at' => now(),
            'status' => 'phone_pending',
        ]);

        return $code;
    }

    /** يتحقّق من رمز الجوال. */
    public function verifyPhone(BrandSignup $signup, string $code): void
    {
        $this->verifyChannel($signup, 'phone', $code);

        $signup->update(['status' => 'details_pending']);
    }

    /**
     * يُعيد إرسال رمز قناة. يعيد الرمز الخام.
     *
     * حدّان معًا: عدد الإرسالات الكلّي، وفترة تهدئة بين الإرسالتين. الأوّل
     * يمنع الاستنزاف، والثاني يمنع استعمال البوّابة قناةَ إزعاج لرقم غريب.
     */
    public function resend(BrandSignup $signup, string $channel): string
    {
        $this->assertUsable($signup);
        $this->assertChannel($channel);

        if ($signup->sent_count >= self::MAX_SENDS) {
            throw new RuntimeException('بلغتَ حدّ الإرسال. ابدأ من جديد.');
        }

        if ($signup->last_sent_at && $signup->last_sent_at->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS) {
            throw new RuntimeException('أُرسل الرمز قبل قليل — انتظر دقيقة.');
        }

        if ($channel === 'phone' && ! $signup->phone) {
            throw new RuntimeException('لا يوجد رقم جوال مسجَّل.');
        }

        $code = $this->code();

        $signup->update([
            "{$channel}_code_hash" => Hash::make($code),
            "{$channel}_attempts" => 0,          // رمز جديد ⇐ محاولات جديدة
            'sent_count' => $signup->sent_count + 1,
            'last_sent_at' => now(),
        ]);

        return $code;
    }

    /** يحفظ بيانات المؤسسة والعلامة. لا يُطابق بعد — المطابقة خطوة صريحة. */
    public function saveDetails(BrandSignup $signup, array $organization, array $brand): BrandSignup
    {
        $this->assertUsable($signup);

        if (! $signup->fullyVerified()) {
            throw new RuntimeException('أكمل التحقّق من البريد والجوال أوّلًا.');
        }

        $signup->update([
            'organization_data' => $organization,
            'brand_data' => $brand,
            'status' => 'matching',
        ]);

        return $signup->fresh();
    }

    /**
     * يُجري المطابقة ويخزّن نتيجتها.
     *
     * النتيجة **لا تُعاد إلى المتصفّح**: كشفُها يجعل البوّابة أداة تعداد
     * لسجلّاتنا. المتحكّم يقرأ القرار ويوجّه المسار، والرسالة للمستخدم واحدة.
     */
    public function runMatch(BrandSignup $signup, BrandMatchingService $matcher): BrandSignup
    {
        $this->assertUsable($signup);

        if (! $signup->organization_data || ! $signup->brand_data) {
            throw new RuntimeException('أكمل بيانات المؤسسة والعلامة أوّلًا.');
        }

        $result = $matcher->match(
            $signup->brand_data,
            $signup->organization_data,
            $signup->email,
            $signup->phone,
        );

        $signup->update([
            'match_decision' => $result['decision'],
            'match_score' => $result['score'],
            'match_signals' => $result['signals'],
            'matched_brand_id' => $result['brand']?->id,
        ]);

        AuditLogger::log('brand_signup.matched', $signup, [], null, null, [
            'decision' => $result['decision'], 'score' => $result['score'],
        ]);

        return $signup->fresh();
    }

    /** يجلب سجلًّا بمرجعه العلني. */
    public function findByReference(string $reference): ?BrandSignup
    {
        return BrandSignup::where('reference', $reference)->first();
    }

    // ===== داخلي =====

    /**
     * التحقّق من قناة: الترتيب مقصود.
     *
     * المحاولات تُفحص **قبل** المقارنة وتُزاد **قبلها** أيضًا — وإلّا لَما
     * حُسبت المحاولة الفاشلة إن رمت المقارنة، وصار الحدّ بلا أثر.
     */
    private function verifyChannel(BrandSignup $signup, string $channel, string $code): void
    {
        $this->assertUsable($signup);
        $this->assertChannel($channel);

        // إعادة التحقّق من قناة مُتحقَّق منها لا تُعدّ خطأً ولا تستهلك محاولة
        if ($signup->{"{$channel}_verified_at"} !== null) {
            return;
        }

        if ($signup->{"{$channel}_attempts"} >= self::MAX_ATTEMPTS) {
            throw new RuntimeException('تجاوزتَ عدد المحاولات. اطلب رمزًا جديدًا.');
        }

        $hash = $signup->{"{$channel}_code_hash"};
        if (! $hash) {
            throw new RuntimeException('لا يوجد رمز فعّال. اطلب رمزًا جديدًا.');
        }

        if ($signup->last_sent_at?->addMinutes(self::CODE_TTL_MINUTES)->isPast()) {
            throw new RuntimeException('انتهت صلاحية الرمز. اطلب رمزًا جديدًا.');
        }

        $signup->increment("{$channel}_attempts");

        if (! Hash::check(trim($code), $hash)) {
            throw new RuntimeException('الرمز غير صحيح.');
        }

        // نجح: يُمحى الرمز فلا يُعاد استعماله
        $signup->update([
            "{$channel}_verified_at" => now(),
            "{$channel}_code_hash" => null,
            "{$channel}_attempts" => 0,
        ]);
    }

    private function assertUsable(BrandSignup $signup): void
    {
        if ($signup->isProvisioned()) {
            throw new RuntimeException('اكتمل هذا التسجيل — سجّل الدخول إلى مساحتك.');
        }

        if ($signup->isExpired()) {
            throw new RuntimeException('انتهت صلاحية هذا التسجيل. ابدأ من جديد.');
        }
    }

    private function assertChannel(string $channel): void
    {
        if (! in_array($channel, ['email', 'phone'], true)) {
            throw new RuntimeException('قناة غير معروفة.');
        }
    }

    private function code(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
