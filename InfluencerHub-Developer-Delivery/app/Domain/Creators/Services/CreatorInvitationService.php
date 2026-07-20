<?php

namespace App\Domain\Creators\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Creators\Models\{Creator, CreatorInvitation};
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use RuntimeException;

/**
 * دعوة صانع محتوى موجود إلى بوابته، وربط حسابه بسجلّه.
 *
 * المشكلة: المسار الوحيد الذي كان يُنشئ حساب صانع محتوى هو قبول طلب انضمام
 * عامّ. فمن تُضيفه الوكالة بنفسها — وهو 166 من 168 — يُرشَّح ويُعتمَد ويُتعاقَد
 * معه ثم يقف: لا يستطيع الدخول ليقبل التعاون أو يوقّع العقد أو يسلّم المحتوى.
 *
 * الرحلة: دعوة ← تحقّق بريد ← تحقّق جوال ← كلمة مرور ← ربط User بالسجلّ الموجود
 * ← تفعيل البوابة. الرمز الخام يُعرض مرّة واحدة ولا يُخزَّن.
 */
class CreatorInvitationService
{
    /** مهلة الدعوة. بعدها تُطلب إعادة إرسال — لا تبقى صالحة للأبد. */
    public const TTL_DAYS = 7;

    /** حدّ إعادة الإرسال لكل دعوة، يمنع استعمالها قناة إزعاج. */
    public const MAX_SENDS = 5;

    /** أقلّ فاصل بين إرسالين. */
    public const RESEND_COOLDOWN_MINUTES = 2;

    public function __construct(private NotificationService $notifications) {}

    /**
     * ينشئ دعوة لصانع محتوى موجود.
     *
     * @return array{0:CreatorInvitation,1:string} الدعوة والرمز الخام
     */
    public function invite(Creator $creator, string $email, ?string $phone, User $inviter): array
    {
        return DB::transaction(function () use ($creator, $email, $phone, $inviter) {
            return TenantContext::withTenant($creator->tenant_id, function () use ($creator, $email, $inviter, $phone) {
                if ($creator->user_id) {
                    throw new RuntimeException('لهذا الحساب مستخدم مرتبط بالفعل — لا حاجة لدعوة.');
                }

                // بريد مستعمَل في مستأجر آخر ليس مشكلتنا؛ المستعمَل هنا يمنع ازدواج الربط
                $taken = Creator::where('email', $email)->whereNotNull('user_id')
                    ->where('id', '!=', $creator->id)->exists();
                if ($taken) {
                    throw new RuntimeException('هذا البريد مرتبط بصانع محتوى آخر في هذه المساحة.');
                }

                // دعوة قائمة صالحة تُعاد لا تُكرَّر — وإلا تعدّدت الروابط الحيّة للسجلّ الواحد
                $existing = CreatorInvitation::where('creator_id', $creator->id)
                    ->whereNull('accepted_at')->whereNull('revoked_at')->latest('id')->first();
                if ($existing && $existing->isUsable()) {
                    throw new RuntimeException("لهذا الحساب دعوة قائمة صالحة — أعِد إرسالها بدل إنشاء أخرى.");
                }

                $raw = Str::random(48);
                $inv = CreatorInvitation::create([
                    'tenant_id' => $creator->tenant_id,
                    'creator_id' => $creator->id,
                    'email' => $email,
                    'phone' => $phone,
                    'token_hash' => hash('sha256', $raw),
                    'email_code' => $this->code(),
                    'phone_code' => $phone ? $this->code() : null,
                    'invited_by' => $inviter->id,
                    'expires_at' => now()->addDays(self::TTL_DAYS),
                    'last_sent_at' => now(),
                ]);

                AuditLogger::log('creator_invitation.sent', $inv,
                    ['creator_id' => $creator->id, 'email' => $email], $creator->tenant_id, $inviter->id);

                return [$inv, $raw];
            });
        });
    }

    /**
     * يعيد إرسال دعوة قائمة برمز جديد.
     *
     * الرمز القديم يبطل فورًا: إعادة الإرسال تعني أن الأوّل لم يصل أو ضاع،
     * وإبقاؤه صالحًا يوسّع سطح الاستعمال بلا سبب.
     */
    public function resend(CreatorInvitation $inv, User $actor): array
    {
        return DB::transaction(function () use ($inv, $actor) {
            return TenantContext::withTenant($inv->tenant_id, function () use ($actor, $inv) {
                if ($inv->accepted_at) {
                    throw new RuntimeException('الدعوة استُخدمت — لا تُعاد.');
                }
                if ($inv->revoked_at) {
                    throw new RuntimeException('الدعوة مُلغاة — أنشئ دعوة جديدة.');
                }
                if ($inv->sent_count >= self::MAX_SENDS) {
                    throw new RuntimeException('بلغت الدعوة حدّ الإرسال (' . self::MAX_SENDS . '). أنشئ دعوة جديدة.');
                }
                if ($inv->last_sent_at && $inv->last_sent_at->diffInMinutes(now()) < self::RESEND_COOLDOWN_MINUTES) {
                    throw new RuntimeException('أُرسلت قبل قليل — انتظر دقيقتين قبل إعادة الإرسال.');
                }

                $raw = Str::random(48);
                $inv->update([
                    'token_hash' => hash('sha256', $raw),
                    'email_code' => $this->code(),
                    'phone_code' => $inv->phone ? $this->code() : null,
                    'email_verified_at' => null,
                    'phone_verified_at' => null,
                    'expires_at' => now()->addDays(self::TTL_DAYS),
                    'sent_count' => $inv->sent_count + 1,
                    'last_sent_at' => now(),
                ]);

                AuditLogger::log('creator_invitation.resent', $inv,
                    ['sent_count' => $inv->sent_count], $inv->tenant_id, $actor->id);

                return [$inv->fresh(), $raw];
            });
        });
    }

    public function revoke(CreatorInvitation $inv, User $actor, ?string $reason = null): CreatorInvitation
    {
        return TenantContext::withTenant($inv->tenant_id, function () use ($actor, $inv, $reason) {
            if ($inv->accepted_at) {
                throw new RuntimeException('الدعوة استُخدمت — لا تُلغى بأثر رجعي.');
            }
            $inv->update(['revoked_at' => now()]);
            AuditLogger::log('creator_invitation.revoked', $inv, ['reason' => $reason], $inv->tenant_id, $actor->id);

            return $inv->fresh();
        });
    }

    /** يجلب دعوة بالرمز الخام. لا يكشف السبب هنا — المستدعي يقرّر ما يُعرَض. */
    public function findByToken(string $raw): ?CreatorInvitation
    {
        return TenantContext::withBypass(function () use ($raw) {
            return CreatorInvitation::where('token_hash', hash('sha256', $raw))->first();
        });
    }

    public function verifyEmail(CreatorInvitation $inv, string $code): CreatorInvitation
    {
        return $this->verify($inv, 'email', $code);
    }

    public function verifyPhone(CreatorInvitation $inv, string $code): CreatorInvitation
    {
        return $this->verify($inv, 'phone', $code);
    }

    private function verify(CreatorInvitation $inv, string $channel, string $code): CreatorInvitation
    {
        $this->assertUsable($inv);

        return TenantContext::withTenant($inv->tenant_id, function () use ($channel, $code, $inv) {
            $expected = $inv->{"{$channel}_code"};
            if (! $expected || ! hash_equals((string) $expected, trim($code))) {
                throw new RuntimeException('الرمز غير صحيح.');
            }
            $inv->update(["{$channel}_verified_at" => now(), "{$channel}_code" => null]);

            return $inv->fresh();
        });
    }

    /**
     * يُنشئ المستخدم ويربطه بسجلّ صانع المحتوى ويفعّل البوابة.
     *
     * لا يُنشئ سجلّ صانع محتوى جديدًا — الربط بالموجود هو الغرض، وإنشاء سجلّ
     * ثانٍ لنفس الشخص هو التكرار الذي تمنعه هذه الرحلة.
     */
    public function accept(CreatorInvitation $inv, string $password): User
    {
        $this->assertUsable($inv);

        if (! $inv->isFullyVerified()) {
            throw new RuntimeException('أكمل تحقّق البريد والجوال قبل إنشاء كلمة المرور.');
        }

        return DB::transaction(function () use ($inv, $password) {
            return TenantContext::withBypass(function () use ($inv, $password) {
                $creator = Creator::find($inv->creator_id);
                if (! $creator) {
                    throw new RuntimeException('سجلّ صانع المحتوى غير موجود.');
                }
                if ($creator->user_id) {
                    throw new RuntimeException('رُبط هذا السجلّ بحساب بالفعل.');
                }

                // بريد موجود يُربط بدل أن يُنشأ ثانيةً — نفس الشخص قد يكون
                // مدعوًّا في مساحة أخرى، والحساب واحد عبر المساحات.
                $user = User::where('email', $inv->email)->first();
                if ($user) {
                    if (! Hash::check($password, $user->password)) {
                        throw new RuntimeException('لهذا البريد حساب قائم — سجّل الدخول بكلمة مروره أو استعِدها.');
                    }
                } else {
                    $user = User::create([
                        'name' => $creator->display_name,
                        'email' => $inv->email,
                        'password' => Hash::make($password),
                        'is_active' => true,
                    ]);
                }

                $org = Organization::where('tenant_id', $inv->tenant_id)->where('type', 'agency')->first();
                if (! $org) {
                    throw new RuntimeException('لا مؤسسة وكالة في هذه المساحة.');
                }

                // الدور يُشتقّ من القدرات كما في قبول طلب الانضمام
                $role = match (CreatorCapabilityService::legacyType($creator->capabilityKeys())) {
                    'ugc_creator' => 'ugc_creator',
                    'both' => 'influencer_and_ugc',
                    default => 'influencer',
                };
                OrganizationMembership::firstOrCreate(
                    ['tenant_id' => $inv->tenant_id, 'organization_id' => $org->id, 'user_id' => $user->id],
                    ['role' => $role, 'status' => 'active'],
                );

                $creator->update(['user_id' => $user->id, 'email' => $creator->email ?: $inv->email]);
                $inv->update(['accepted_at' => now()]);

                AuditLogger::log('creator_invitation.accepted', $inv,
                    ['creator_id' => $creator->id, 'user_id' => $user->id], $inv->tenant_id, $user->id);

                if ($inv->invited_by) {
                    $this->notifications->notify($inv->tenant_id, (int) $inv->invited_by,
                        'creator_invitation.accepted', 'general',
                        'فُعِّلت بوابة صانع محتوى',
                        "{$creator->display_name} أنشأ حسابه ويستطيع الآن قبول التعاونات وتوقيع العقود.",
                        "/app/creators/{$creator->id}", ['creator_id' => $creator->id], $inv);
                }

                return $user;
            });
        });
    }

    private function assertUsable(CreatorInvitation $inv): void
    {
        if ($reason = $inv->unusableReason()) {
            throw new RuntimeException($reason);
        }
    }

    /** رمز رقمي من 6 خانات — يُقرأ صوتًا ويُكتب بلا لبس. */
    private function code(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
