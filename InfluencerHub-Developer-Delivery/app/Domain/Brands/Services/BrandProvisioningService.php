<?php

namespace App\Domain\Brands\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\PlanVersion;
use App\Domain\Brands\Models\BrandSignup;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandSocialAccount;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * تزويد مساحة العلامة: مستأجر + مؤسسة + علامة + مالك + مستخدم + عضوية + اشتراك.
 *
 * ## سبعة إنشاءات، معاملة واحدة
 *
 * الفشل في أيّها يجب أن يمحو ما قبله. النصف المزوَّد أسوأ من الفشل الصريح:
 * مستأجرٌ بلا مالك لا يملك أحد الدخول إليه، وعلامةٌ بلا مستأجر يتيمة، ومستخدمٌ
 * بلا عضوية يسجّل الدخول ولا يرى شيئًا — وكلّها حالات لا يكشفها إلا شكوى.
 *
 * ## الملكية في صفّ، لا في عمود
 *
 * `client_id` يبقى **فارغًا**: العلامة المسجِّلة لنفسها لا عميل لها. ملكيّتها
 * صفٌّ في `brand_workspace_relationships` بنوع `owner`. وإنشاء «عميل ذاتي»
 * يحمل اسم العلامة ممنوع صراحةً — يخلط العميل بالعلامة فيشوّه التقارير
 * والصلاحيات والفوترة.
 *
 * ## التزويد يقع مرّة واحدة
 *
 * `created_tenant_id` هو الحارس: وجوده يعني أن الرحلة اكتملت. وقراءته تقع
 * **داخل** المعاملة مع قفل الصفّ — لأن فحصًا قبلها يمرّ منه طلبان متزامنان
 * فيُنشئان مستأجرَين لعلامة واحدة.
 */
class BrandProvisioningService
{
    public function __construct(private BrandMatchingService $matcher) {}

    /**
     * @param  array{name:string,email:string,password:string}  $owner
     * @return array{tenant:Tenant, brand:Brand, user:User, organization:Organization}
     */
    public function provision(BrandSignup $signup, array $owner, ?string $planKey = null): array
    {
        if (! $signup->fullyVerified()) {
            throw new RuntimeException('أكمل التحقّق قبل إنشاء المساحة.');
        }

        if (! $signup->organization_data || ! $signup->brand_data) {
            throw new RuntimeException('بيانات المؤسسة والعلامة ناقصة.');
        }

        // التطابق القويّ لا يُزوَّد: يمرّ بمسار المطالبة وحده
        if ($signup->match_decision === BrandSignup::DECISION_STRONG) {
            throw new RuntimeException('هذا التسجيل يحتاج إثبات ملكية قبل إنشاء المساحة.');
        }

        return DB::transaction(function () use ($signup, $owner, $planKey) {
            // القفل داخل المعاملة: فحصٌ قبلها يمرّ منه طلبان متزامنان
            $locked = BrandSignup::whereKey($signup->getKey())->lockForUpdate()->first();

            if (! $locked) {
                throw new RuntimeException('سجلّ التسجيل غير موجود.');
            }

            if ($locked->created_tenant_id !== null) {
                throw new RuntimeException('اكتمل هذا التسجيل من قبل.');
            }

            return TenantContext::withBypass(function () use ($locked, $owner, $planKey) {
                $this->assertEmailFree($owner['email']);

                $orgData = $locked->organization_data;
                $brandData = $locked->brand_data;
                $brandName = $brandData['name'] ?? ($orgData['legal_name'] ?? 'علامة');

                // 1) المستأجر — نوعه `brand` صراحةً لا اتّكالًا على قيمة القاعدة الافتراضية
                $tenant = Tenant::create([
                    'name' => $brandName,
                    'slug' => $this->uniqueSlug($brandName, 'tenants'),
                    'type' => Tenant::TYPE_BRAND,
                    'deployment_mode' => 'saas',
                    'status' => 'active',
                ]);

                // 2) المؤسسة داخله
                $organization = Organization::create([
                    'tenant_id' => $tenant->id,
                    'name' => $orgData['legal_name'] ?? $brandName,
                    'slug' => $this->uniqueSlug($brandName, 'organizations'),
                    'type' => 'brand',
                    'status' => 'active',
                ]);

                // 3) مالك الحساب
                $user = User::create([
                    'name' => $owner['name'],
                    'email' => Str::lower(trim($owner['email'])),
                    'password' => $owner['password'],
                    'is_active' => true,
                ]);
                // البريد تحقّقنا منه في الرحلة، و`email_verified_at` ليس قابلًا للملء
                $user->forceFill(['email_verified_at' => now()])->save();

                // 4) العضوية — `brand_admin` موجود في التعداد ولم يكن يُكتب قبل اليوم
                OrganizationMembership::create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'role' => Role::BrandAdmin->value,
                    'status' => 'active',
                ]);

                // 5) العلامة — بلا `client_id`، وبحقول هُويّة مُطبَّعة تُطابَق لاحقًا
                $brand = Brand::create([
                    'tenant_id' => $tenant->id,
                    'client_id' => null,
                    'name' => $brandName,
                    'slug' => $this->uniqueSlug($brandName, 'brands'),
                    'normalized_name' => $this->matcher->normalizeName($brandName),
                    'sector' => $brandData['sector'] ?? null,
                    'website' => $brandData['website'] ?? null,
                    'website_domain' => $this->matcher->domain($brandData['website'] ?? null),
                    'email_domain' => $this->matcher->emailDomain($locked->email),
                    'commercial_registration' => $this->matcher->cleanCr($orgData['commercial_registration'] ?? null),
                    'description' => $brandData['description'] ?? null,
                    'contact_information' => array_filter([
                        'email' => $locked->email,
                        'phone' => $locked->phone,
                    ]),
                    'status' => 'approved',   // علامة تملك نفسها لا تنتظر اعتماد وكالة
                    'current_version' => 1,
                    'created_by' => $user->id,
                ]);

                foreach (($brandData['social_accounts'] ?? []) as $account) {
                    $handle = is_array($account) ? ($account['handle'] ?? null) : $account;
                    if (! $handle) {
                        continue;
                    }

                    BrandSocialAccount::create([
                        'tenant_id' => $tenant->id,
                        'brand_id' => $brand->id,
                        'platform' => is_array($account) ? ($account['platform'] ?? 'other') : 'other',
                        'handle' => Str::lower(ltrim(trim((string) $handle), '@')),
                        'url' => is_array($account) ? ($account['url'] ?? null) : null,
                    ]);
                }

                // 6) الملكية — الصفّ الذي يجعل العلامة مالكة نفسها
                BrandWorkspaceRelationship::create([
                    'brand_id' => $brand->id,
                    'tenant_id' => $tenant->id,
                    'relationship_type' => BrandWorkspaceRelationship::OWNER,
                    'status' => 'active',
                    // المالك يملك كل شيء — ولا يُقرأ نطاقه أصلًا، فالملكية ليست تفويضًا
                    'services_scope' => BrandWorkspaceRelationship::SERVICES,
                    'permissions_scope' => ['manage'],
                    'started_at' => now(),
                    'approved_by' => $user->id,
                ]);

                // 7) الاشتراك التجريبي
                $subscription = null;
                if ($version = $this->planVersion($planKey)) {
                    $subscription = app(CreateSubscription::class)->handle($organization, $version);
                }

                $locked->update([
                    'status' => 'provisioned',
                    'created_tenant_id' => $tenant->id,
                    'created_brand_id' => $brand->id,
                    'created_user_id' => $user->id,
                ]);

                AuditLogger::log('brand.self_registered', $brand, [], $tenant->id, $user->id, [
                    'signup_reference' => $locked->reference,
                    'subscription_id' => $subscription?->id,
                ]);

                $this->assertConsistent($tenant, $brand, $user, $organization);

                return ['tenant' => $tenant, 'brand' => $brand, 'user' => $user, 'organization' => $organization];
            });
        });
    }

    /**
     * فحص ما بعد الإنشاء — داخل المعاملة، فأيّ خلل يُرجعها كاملة.
     *
     * ليس تزيينًا: الشروط هنا هي ما يجعل المساحة قابلة للاستعمال. وخرقُ أيّها
     * ينتج مساحة تبدو موجودة ولا تعمل — وهي حالة لا يكشفها إلا شكوى مستخدم.
     */
    private function assertConsistent(Tenant $tenant, Brand $brand, User $user, Organization $organization): void
    {
        $checks = [
            'مستأجر واحد بنوع علامة' => Tenant::withoutGlobalScopes()
                ->where('id', $tenant->id)->where('type', Tenant::TYPE_BRAND)->count() === 1,

            'علامة واحدة في المستأجر' => Brand::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)->count() === 1,

            'العلامة بلا عميل' => $brand->client_id === null,

            'علاقة مالك واحدة' => BrandWorkspaceRelationship::where('brand_id', $brand->id)
                ->where('relationship_type', BrandWorkspaceRelationship::OWNER)->count() === 1,

            'عضوية واحدة فعّالة للمالك' => OrganizationMembership::withoutGlobalScopes()
                ->where('organization_id', $organization->id)->where('user_id', $user->id)
                ->where('status', 'active')->count() === 1,

            'دور المالك brand_admin' => OrganizationMembership::withoutGlobalScopes()
                ->where('organization_id', $organization->id)->where('user_id', $user->id)
                ->value('role') === Role::BrandAdmin->value,
        ];

        foreach ($checks as $label => $ok) {
            if (! $ok) {
                throw new RuntimeException("فشل فحص الاتّساق بعد التزويد: {$label}");
            }
        }
    }

    /** بريد مملوك لحساب قائم لا يُنشأ له حساب ثانٍ. */
    private function assertEmailFree(string $email): void
    {
        $exists = User::withoutGlobalScopes()->where('email', Str::lower(trim($email)))->exists();

        if ($exists) {
            throw new RuntimeException('لهذا البريد حساب بالفعل — سجّل الدخول ثم أنشئ مساحة العلامة من حسابك.');
        }
    }

    private function planVersion(?string $planKey): ?PlanVersion
    {
        $plan = $planKey
            ? Plan::withoutGlobalScopes()->where('key', $planKey)->where('is_active', true)->first()
            : Plan::withoutGlobalScopes()->where('is_active', true)->where('key', '!=', 'showcase')->orderBy('id')->first();

        return $plan
            ? PlanVersion::withoutGlobalScopes()->where('plan_id', $plan->id)
                ->where('is_active', true)->orderByDesc('version')->first()
            : null;
    }

    private function uniqueSlug(string $name, string $table): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;

        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
