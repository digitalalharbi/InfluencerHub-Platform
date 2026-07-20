<?php

namespace App\Domain\Onboarding\Services;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion};
use App\Domain\Identity\Models\User;
use App\Domain\Onboarding\Models\SelfSignup;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

/**
 * التسجيل الذاتي لمساحة وكالة: من التحقّق إلى مستأجر عامل بتجربة مجانية.
 *
 * لا دفع هنا ولا ادّعاء دفع. الاشتراك يُنشأ بحالة `trialing` ومزوّد `fake`،
 * وهي الحقيقة: لا مزوّد مالي مربوط بعد. عند ربطه تتغيّر الحالة عبر المزوّد
 * لا عبر تعديل هذه الشيفرة.
 */
class SelfSignupService
{
    /** مهلة رمز التحقّق ومحاولاته — حدّ يمنع التخمين بلا أن يُرهق مستخدمًا صادقًا. */
    private const CODE_TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    /** يبدأ المسار ويعيد [السجلّ, الرمز] — الرمز لا يُخزَّن نصًّا فيُعاد ليُرسَل. */
    public function start(string $email, string $accountType, ?string $ip = null): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $signup = SelfSignup::create([
            'account_type' => $accountType,
            'email' => $email,
            'status' => 'email_verification_pending',
            'verification_code_hash' => Hash::make($code),
            'code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'completed_steps' => [],
            'ip_address' => $ip,
        ]);

        return [$signup, $code];
    }

    /** @throws \RuntimeException بسبب مفهوم يصل إلى الواجهة */
    public function verify(SelfSignup $signup, string $code): void
    {
        if ($signup->isVerified()) {
            return;
        }
        if ($signup->verification_attempts >= self::MAX_ATTEMPTS) {
            throw new \RuntimeException('تجاوزت عدد المحاولات. اطلب رمزًا جديدًا.');
        }
        if (! $signup->code_expires_at || $signup->code_expires_at->isPast()) {
            throw new \RuntimeException('انتهت صلاحية الرمز. اطلب رمزًا جديدًا.');
        }

        $signup->increment('verification_attempts');

        if (! Hash::check($code, (string) $signup->verification_code_hash)) {
            throw new \RuntimeException('الرمز غير صحيح.');
        }

        $signup->update([
            'email_verified_at' => now(),
            'status' => 'verified',
            'verification_code_hash' => null,
            'completed_steps' => [...($signup->completed_steps ?? []), 'email_verification_pending'],
        ]);
    }

    /**
     * التفعيل: مستأجر + مؤسسة + مالك + اشتراك تجريبي، في معاملة واحدة.
     * الذرّية مقصودة: مساحة نصف مُنشأة أسوأ من فشل صريح.
     */
    public function provision(SelfSignup $signup, array $data): User
    {
        if (! $signup->isVerified()) {
            throw new \RuntimeException('أكّد بريدك قبل إنشاء المساحة.');
        }
        if ($signup->created_tenant_id) {
            throw new \RuntimeException('أُنشئت هذه المساحة بالفعل.');
        }
        if (User::where('email', $signup->email)->exists()) {
            throw new \RuntimeException('يوجد حساب بهذا البريد. سجّل الدخول بدلًا من إنشاء مساحة جديدة.');
        }

        return DB::transaction(function () use ($signup, $data) {
            $user = TenantContext::withBypass(function () use ($data, $signup) {
    
                $tenant = Tenant::create([
                    'name' => $data['organization_name'],
                    'slug' => $this->uniqueSlug($data['organization_name']),
                    'deployment_mode' => 'saas',
                    'status' => 'active',
                ]);
    
                $org = Organization::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['organization_name'],
                    'slug' => $this->uniqueSlug($data['organization_name'], 'organizations'),
                    'type' => 'agency',
                    'status' => 'active',
                ]);
    
                $user = User::create([
                    'name' => $data['owner_name'],
                    'email' => $signup->email,
                    'password' => Hash::make($data['password']),
                    'is_active' => true,
                ]);
    
                // خارج الإنشاء الجماعي: email_verified_at ليس في $fillable فكان يُسقَط
                // صامتًا، فيُطالَب المستخدم بتأكيد بريد أكّده قبل قليل.
                $user->forceFill(['email_verified_at' => now()])->save();
    
                OrganizationMembership::create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $org->id,
                    'user_id' => $user->id,
                    'role' => 'agency_admin',
                    'status' => 'active',
                ]);
    
                // تجربة مجانية بلا دفع: CreateSubscription يضع trialing ومزوّدًا وهميًّا صراحةً
                if ($version = $this->trialPlanVersion()) {
                    app(CreateSubscription::class)->handle($org, $version);
                }
    
                $signup->update([
                    'status' => 'active',
                    'created_tenant_id' => $tenant->id,
                    'created_user_id' => $user->id,
                    'completed_steps' => [...($signup->completed_steps ?? []), 'verified', 'organization_pending'],
                ]);
    
                return $user;
            });

            return $user;
        });
    }

    /** نسخة الخطة التي تبدأ عليها التجربة — أحدث نسخة فعّالة من خطة فعّالة. */
    private function trialPlanVersion(): ?PlanVersion
    {
        $plan = Plan::where('is_active', true)->where('key', '!=', 'showcase')->orderBy('id')->first();

        return $plan
            ? PlanVersion::where('plan_id', $plan->id)->where('is_active', true)->latest('version')->first()
            : null;
    }

    private function uniqueSlug(string $name, string $table = 'tenants'): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 1;
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
