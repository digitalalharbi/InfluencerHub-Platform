<?php
namespace App\Domain\Creators\Services;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Models\{CreatorApplication, CreatorApplicationStatusHistory, CreatorApplicationVerification};
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use RuntimeException;

/** منطق بوابة طلبات الانضمام: مرجع آمن، مسودة، تحوّلات حالة (append-only)، OTP. */
class CreatorApplicationService {
    /**
     * حلّ المستأجر للبوابة العامة — صريح، fail-closed، لا يستخدم "أول مستأجر".
     * saas: يلزم workspace slug (Organization). dedicated/self_hosted: مؤسسة وحيدة موثّقة.
     * يعيد [tenant, organization, source] أو null.
     */
    public function resolveTenantContext(?string $slug): ?array {
        $mode = config('influencerhub.deployment_mode', 'saas');
        return TenantContext::withBypass(function () use ($slug, $mode) {
            if ($slug) {
                $org = \App\Domain\Tenancy\Models\Organization::where('slug', $slug)->where('status', 'active')->first();
                if (! $org) return null;                                   // slug غير صالح → fail-closed
                $tenant = Tenant::where('id', $org->tenant_id)->where('status', 'active')->first();
                return $tenant ? [$tenant, $org, 'slug'] : null;
            }
            // بلا slug: مسموح فقط في dedicated/self_hosted (مؤسسة وحيدة)
            if (in_array($mode, ['dedicated', 'self_hosted'], true)) {
                $count = \App\Domain\Tenancy\Models\Organization::count();
                if ($count !== 1) return null;                            // ليست مؤسسة وحيدة → fail-closed
                $org = \App\Domain\Tenancy\Models\Organization::first();
                $tenant = Tenant::where('id', $org->tenant_id)->where('status', 'active')->first();
                return $tenant ? [$tenant, $org, $mode] : null;
            }
            return null; // saas بلا slug → مرفوض
        });
    }

    /** توافقًا خلفيًا: يعيد المستأجر فقط (يستخدم الحلّ الصريح). */
    public function resolveTenant(?string $slug = null): ?Tenant {
        $ctx = $this->resolveTenantContext($slug);
        return $ctx ? $ctx[0] : null;
    }

    // ===== وصول المتقدّم (رمز منفصل عن المرجع) =====
    public const ACCESS_TTL_DAYS = 30;

    /** يصدر رمز وصول (يُخزَّن Hash فقط)؛ يعيد الرمز الخام مرة واحدة. */
    public function issueAccessToken(CreatorApplication $app): string {
        $raw = Str::random(48);
        TenantContext::withTenant($app->tenant_id, fn () => $app->update([
            'access_token_hash' => hash('sha256', $raw),
            'access_token_expires_at' => now()->addDays(self::ACCESS_TTL_DAYS),
            'access_token_revoked_at' => null,
        ]));
        return $raw;
    }

    /** يتحقق من رمز الوصول (hash/انتهاء/إلغاء). */
    public function verifyAccessToken(CreatorApplication $app, string $raw): bool {
        if (! $app->access_token_hash || $app->access_token_revoked_at) return false;
        if ($app->access_token_expires_at && now()->greaterThan($app->access_token_expires_at)) return false;
        return hash_equals($app->access_token_hash, hash('sha256', $raw));
    }

    public function revokeAccessToken(CreatorApplication $app): void {
        TenantContext::withTenant($app->tenant_id, fn () => $app->update(['access_token_revoked_at' => now()]));
    }

    /** يسجّل محاولة وصول (للتدقيق الأمني، لا يكشف وجود الطلب). */
    public function logAccessAttempt(?string $reference, string $outcome): void {
        \App\Domain\Creators\Models\CreatorApplicationAccessAttempt::create([
            'reference' => $reference, 'outcome' => $outcome,
            'ip' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now(),
        ]);
    }

    /** ينشئ مسودة جديدة بمرجع عشوائي غير قابل للتخمين. */
    public function startDraft(Tenant $tenant, array $data): CreatorApplication {
        return DB::transaction(fn () => TenantContext::withTenant($tenant->id, function () use ($tenant, $data) {
            $app = CreatorApplication::create([
                'tenant_id' => $tenant->id,
                'reference' => 'CA-' . Str::upper(Str::random(20)), // 20 حرفًا عشوائيًا
                'status' => 'draft',
                'full_name' => $data['full_name'] ?? null,
                'professional_name' => $data['professional_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'city' => $data['city'] ?? null,
                // القدرات هي التصريح الحقيقي؛ `account_type` نصّ مشتقّ منها يُكتب
                // معها للتوافق الخلفي حتى يتوقّف كل قارئ عنه.
                'capabilities' => $caps = CreatorCapabilityService::normalize($data['capabilities'] ?? []),
                'account_type' => $data['account_type'] ?? CreatorCapabilityService::legacyType($caps),
                'current_step' => 1,
            ]);
            $this->recordStatus($app, null, 'draft', null, 'بدء مسودة جديدة');
            return $app;
        }));
    }

    /** يجلب طلبًا بمرجعه (بلا كشف ID). */
    public function findByReference(string $reference): ?CreatorApplication {
        return TenantContext::withBypass(fn () => CreatorApplication::where('reference', $reference)->first());
    }

    /** يحدّث بيانات مسودة (يُرفض إن لم تكن قابلة للتحرير). */
    public function updateDraft(CreatorApplication $app, array $data): CreatorApplication {
        if (! $app->isEditableByApplicant()) {
            throw new RuntimeException('لا يمكن تعديل هذا الطلب في حالته الحالية.');
        }
        return TenantContext::withTenant($app->tenant_id, function () use ($app, $data) {
            $app->update($data);
            return $app->fresh();
        });
    }

    /** ينقل الحالة ويسجّلها Append-only. */
    public function transition(CreatorApplication $app, string $to, ?int $actorId = null, array $meta = []): CreatorApplication {
        return TenantContext::withTenant($app->tenant_id, function () use ($app, $to, $actorId, $meta) {
            $from = $app->status;
            $app->update(['status' => $to] + array_intersect_key($meta, array_flip(['rejection_reason','submitted_at','reviewed_at','assigned_reviewer_id'])));
            $this->recordStatus($app, $from, $to, $actorId, $meta['reason'] ?? null, $meta['internal_notes'] ?? null, $meta['applicant_message'] ?? null);
            AuditLogger::log("creator_application.$to", $app, ['from' => $from], $app->tenant_id, $actorId);
            return $app->fresh();
        });
    }

    private function recordStatus(CreatorApplication $app, ?string $from, string $to, ?int $actorId, ?string $reason = null, ?string $internal = null, ?string $applicantMsg = null): void {
        CreatorApplicationStatusHistory::create([
            'tenant_id' => $app->tenant_id, 'application_id' => $app->id, 'from_status' => $from, 'to_status' => $to,
            'actor_id' => $actorId, 'reason' => $reason, 'internal_notes' => $internal, 'applicant_message' => $applicantMsg,
            'request_id' => request()?->headers->get('X-Request-Id'), 'occurred_at' => now(),
        ]);
    }

    // ===== OTP =====
    public const OTP_TTL_MINUTES = 10;
    public const OTP_MAX_ATTEMPTS = 5;
    public const OTP_RESEND_COOLDOWN_SECONDS = 60;

    /**
     * يصدر رمز تحقق (يُخزَّن Hash فقط) ويُرسله عبر الطابور. يرمي عند طلب متكرّر سريع (cooldown).
     * يعيد الرمز الخام لغرض العرض المحلي فقط — لا يُعرض في الإنتاج (يتحكّم المُتحكّم بذلك).
     */
    public function issueOtp(CreatorApplication $app, string $channel): string {
        return TenantContext::withTenant($app->tenant_id, function () use ($app, $channel) {
            // cooldown لإعادة الإرسال
            $last = CreatorApplicationVerification::where('application_id', $app->id)->where('channel', $channel)->latest('id')->first();
            if ($last && $last->created_at && $last->created_at->diffInSeconds(now()) < self::OTP_RESEND_COOLDOWN_SECONDS) {
                throw new RuntimeException('يرجى الانتظار قبل إعادة إرسال الرمز.');
            }
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            CreatorApplicationVerification::create([
                'tenant_id' => $app->tenant_id, 'application_id' => $app->id, 'channel' => $channel,
                'code_hash' => hash('sha256', $code), 'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'attempts' => 0, 'created_at' => now(),
            ]);
            // إرسال عبر الطابور (بريد/جوال) — لا يُعرض الرمز للمستخدم في الإنتاج
            $dest = $channel === 'email' ? (string) $app->email : (string) $app->phone;
            \App\Domain\Creators\Jobs\SendOtpJob::dispatch($channel, $dest, $code);
            return $code;
        });
    }

    /** يتحقق من رمز: يرفض المنتهي/تجاوز المحاولات/الخاطئ. */
    public function verifyOtp(CreatorApplication $app, string $channel, string $code): bool {
        return TenantContext::withTenant($app->tenant_id, function () use ($app, $channel, $code) {
            $v = CreatorApplicationVerification::where('application_id', $app->id)->where('channel', $channel)
                ->whereNull('verified_at')->latest('id')->first();
            // الرمي داخل withTenant يستعيد السياق تلقائيًا — فسقطت reset من كل مسار
            if (! $v) { throw new RuntimeException('لا يوجد رمز تحقق فعّال.'); }
            if ($v->attempts >= self::OTP_MAX_ATTEMPTS) { throw new RuntimeException('تم تجاوز عدد المحاولات المسموح.'); }
            if (now()->greaterThan($v->expires_at)) { throw new RuntimeException('انتهت صلاحية الرمز.'); }
            $v->increment('attempts');
            $ok = hash_equals($v->code_hash, hash('sha256', $code));
            if ($ok) {
                $v->update(['verified_at' => now()]);
                $field = $channel === 'email' ? 'email_verified_at' : 'phone_verified_at';
                $app->update([$field => now()]);
            }
            return $ok;
        });
    }
}
