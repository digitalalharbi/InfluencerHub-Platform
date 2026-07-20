<?php

namespace App\Domain\Brands\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Brands\Models\BrandClaimDocument;
use App\Domain\Brands\Models\BrandClaimRequest;
use App\Domain\Brands\Models\BrandSignup;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * المطالبة بعلامة قائمة — الطريق الوحيد إلى سجلٍّ يملكه غيرك.
 *
 * ## لماذا لا ربط تلقائي، ولو كان التطابق قاطعًا
 *
 * أقوى مؤشّر عندنا هو نطاق البريد المؤسسي، ومعناه «يملك بريدًا على هذا
 * النطاق» لا «يملك هذه العلامة». موظّف مستقيل، أو متعاقد، أو من اشترى نطاقًا
 * منتهيًا — كلّهم يجتازونه. فالدرجة تختصر **الأدلّة المطلوبة**، ولا تختصر
 * المراجعة.
 *
 * ## ولماذا لا يُكشف شيء للطالب
 *
 * لو قال النظام «العلامة موجودة، قدّم مطالبة» لصار أداة تعداد: يجرّب المهاجم
 * أسماء ونطاقات فيعرف من هم عملاؤنا. فالردّ واحد في كل الأحوال، ولا تُعاد
 * بيانات العلامة المطابَقة إلى الطالب أبدًا — ولا حتّى اسمها.
 */
class BrandClaimService
{
    public const TTL_DAYS = 14;

    public const MAX_DOCUMENTS = 10;

    public const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;

    /** الأدوار التي تراجع المطالبات. غيرها لا يرى الطلبات أصلًا. */
    public const REVIEWER_ROLES = [Role::SystemAdmin->value, Role::SuperAdmin->value];

    public function __construct(private NotificationService $notifications) {}

    /**
     * يفتح طلب مطالبة.
     *
     * منع التكرار مزدوج: فحصٌ يعطي رسالة مفهومة، وفهرس جزئي في القاعدة يمسك
     * ما يفلت من الفحص عند طلبين متزامنين. الاعتماد على الفحص وحده يترك ثغرة
     * سباق تُنتج طلبين يعتمدهما مراجعان مختلفان.
     */
    public function open(Brand $brand, string $requesterEmail, ?BrandSignup $signup = null, array $evidence = [], ?User $requester = null): BrandClaimRequest
    {
        $email = Str::lower(trim($requesterEmail));

        $existing = BrandClaimRequest::where('brand_id', $brand->id)
            ->where('requester_email', $email)
            ->whereIn('status', BrandClaimRequest::LIVE)
            ->first();

        if ($existing) {
            throw new RuntimeException('لديك طلب قائم على هذه العلامة.');
        }

        try {
            $claim = BrandClaimRequest::create([
                'reference' => (string) Str::uuid(),
                'brand_id' => $brand->id,
                'signup_id' => $signup?->id,
                'requester_email' => $email,
                'requester_user_id' => $requester?->id,
                'status' => BrandClaimRequest::PENDING,
                'evidence' => $evidence,
                'match_signals' => $signup?->match_signals ?? [],
                'match_score' => $signup?->match_score,
                // نطاق بريد مؤسسي تحقّقنا منه فعلًا في الرحلة — لا ادّعاء
                'corporate_email_verified' => $signup !== null
                    && ($signup->match_signals['email_domain'] ?? null) !== null
                    && $signup->emailVerified(),
                'expires_at' => now()->addDays(self::TTL_DAYS),
            ]);
        } catch (QueryException $e) {
            // الفهرس الجزئي أمسك سباقًا فات الفحص أعلاه
            throw new RuntimeException('لديك طلب قائم على هذه العلامة.', 0, $e);
        }

        if ($signup) {
            $signup->update(['status' => 'claim_pending']);
        }

        AuditLogger::log('brand_claim.opened', $claim, [], $brand->tenant_id, $requester?->id, [
            'brand_id' => $brand->id, 'score' => $claim->match_score,
        ]);

        $this->notifyBrandSide($claim, 'مطالبة بملكية علامتك',
            'وصل طلب إثبات ملكية لعلامة في مساحتك. لا يُمنح وصول قبل المراجعة.');

        return $claim;
    }

    /** يرفع مستند إثبات. */
    public function attachDocument(BrandClaimRequest $claim, UploadedFile $file, string $type, ?User $actor = null): BrandClaimDocument
    {
        $this->assertLive($claim);

        if (! in_array($type, BrandClaimDocument::TYPES, true)) {
            throw new RuntimeException('نوع مستند غير معروف.');
        }

        if ($claim->documents()->count() >= self::MAX_DOCUMENTS) {
            throw new RuntimeException('بلغتَ حدّ عدد المستندات.');
        }

        if ($file->getSize() > self::MAX_DOCUMENT_BYTES) {
            throw new RuntimeException('حجم الملفّ يتجاوز الحدّ المسموح.');
        }

        // المسار مولَّد، لا يشتقّ من اسم المستخدم — وإلّا صار `../../.env` اسمًا مقبولًا
        $path = $file->storeAs(
            "brand-claims/{$claim->id}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'private',
        );

        $doc = BrandClaimDocument::create([
            'claim_request_id' => $claim->id,
            'type' => $type,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $actor?->id,
        ]);

        AuditLogger::log('brand_claim.document_added', $claim, [], null, $actor?->id, ['type' => $type]);

        return $doc;
    }

    /** يبدأ المراجعة. */
    public function startReview(BrandClaimRequest $claim, User $reviewer): BrandClaimRequest
    {
        return $this->transition($claim, BrandClaimRequest::UNDER_REVIEW, $reviewer);
    }

    /** يطلب معلومات إضافية — يعيد الكرة إلى الطالب بلا رفض. */
    public function requestMoreInfo(BrandClaimRequest $claim, User $reviewer, string $what): BrandClaimRequest
    {
        $claim = $this->transition($claim, BrandClaimRequest::MORE_INFO, $reviewer, ['info_requested' => $what]);

        $this->notifyRequester($claim, 'طلبك يحتاج معلومات إضافية', $what);

        return $claim;
    }

    /**
     * يعتمد المطالبة — وهنا وحدها تنتقل الملكية.
     *
     * ما يقع: تُنشأ للطالب مساحةُ علامة (مستأجر مستقلّ + مؤسسة + حساب) وتُنقَل
     * إليها العلامة، ويصير للوكالة السابقة تفويض `managing_agency` بدل ملكيةٍ
     * لم تكن لها أصلًا.
     *
     * **ولا تُحذف بيانات.** الحملات والعقود والفواتير تبقى معلّقة بالعلامة
     * نفسها؛ ما يتغيّر هو من يملكها لا ما فيها.
     */
    public function approve(BrandClaimRequest $claim, User $reviewer, array $owner): BrandClaimRequest
    {
        $this->assertReviewer($reviewer);

        if (! $claim->canTransitionTo(BrandClaimRequest::APPROVED)) {
            throw new RuntimeException("لا يمكن اعتماد طلب في حالة {$claim->status}.");
        }

        return DB::transaction(function () use ($claim, $reviewer, $owner) {
            $locked = BrandClaimRequest::whereKey($claim->getKey())->lockForUpdate()->first();

            if (! $locked->canTransitionTo(BrandClaimRequest::APPROVED)) {
                throw new RuntimeException('عولج هذا الطلب بالفعل.');
            }

            return TenantContext::withBypass(function () use ($locked, $reviewer, $owner) {
                $brand = Brand::withoutGlobalScopes()->findOrFail($locked->brand_id);
                $previousTenantId = $brand->tenant_id;

                $tenant = Tenant::create([
                    'name' => $brand->name,
                    'slug' => $this->uniqueSlug($brand->name, 'tenants'),
                    'type' => Tenant::TYPE_BRAND,
                    'deployment_mode' => 'saas',
                    'status' => 'active',
                ]);

                $organization = Organization::create([
                    'tenant_id' => $tenant->id,
                    'name' => $brand->name,
                    'slug' => $this->uniqueSlug($brand->name, 'organizations'),
                    'type' => 'brand',
                    'status' => 'active',
                ]);

                $user = User::withoutGlobalScopes()->where('email', Str::lower(trim($owner['email'])))->first()
                    ?? User::create([
                        'name' => $owner['name'],
                        'email' => Str::lower(trim($owner['email'])),
                        'password' => $owner['password'],
                        'is_active' => true,
                    ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                OrganizationMembership::firstOrCreate(
                    ['organization_id' => $organization->id, 'user_id' => $user->id, 'workspace_id' => null],
                    ['tenant_id' => $tenant->id, 'role' => Role::BrandAdmin->value, 'status' => 'active'],
                );

                // الوكالة السابقة تصير مفوَّضة لا مالكة — تفويضٌ يبقى حتّى يُلغى صراحةً
                BrandWorkspaceRelationship::updateOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'tenant_id' => $previousTenantId,
                        'relationship_type' => BrandWorkspaceRelationship::MANAGING_AGENCY,
                    ],
                    [
                        'status' => 'active',
                        'services_scope' => ['campaigns', 'shortlists', 'content', 'contracts', 'finance', 'reports'],
                        'started_at' => now(),
                        'approved_by' => $reviewer->id,
                    ],
                );

                // العلامة تنتقل إلى مستأجرها، وبياناتها معها
                $brand->forceFill(['tenant_id' => $tenant->id, 'client_id' => null])->save();

                BrandWorkspaceRelationship::create([
                    'brand_id' => $brand->id,
                    'tenant_id' => $tenant->id,
                    'relationship_type' => BrandWorkspaceRelationship::OWNER,
                    'status' => 'active',
                    'services_scope' => BrandWorkspaceRelationship::SERVICES,
                    'permissions_scope' => ['manage'],
                    'started_at' => now(),
                    'approved_by' => $reviewer->id,
                ]);

                $locked->update([
                    'status' => BrandClaimRequest::APPROVED,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                ]);

                if ($locked->signup_id) {
                    BrandSignup::whereKey($locked->signup_id)->update([
                        'status' => 'provisioned',
                        'created_tenant_id' => $tenant->id,
                        'created_brand_id' => $brand->id,
                        'created_user_id' => $user->id,
                    ]);
                }

                AuditLogger::log('brand_claim.approved', $locked, [], $tenant->id, $reviewer->id, [
                    'brand_id' => $brand->id,
                    'previous_tenant_id' => $previousTenantId,
                    'new_tenant_id' => $tenant->id,
                ]);

                $this->notifications->notify($tenant->id, $user->id, 'brand_claim.approved', 'general',
                    'اعتُمدت ملكيتك للعلامة', "صارت {$brand->name} في مساحتك.", '/brand', [], $locked);

                return $locked->fresh();
            });
        });
    }

    /** يرفض — والسبب إلزامي: رفضٌ بلا سبب لا يمكن تصحيحه ولا الطعن فيه. */
    public function reject(BrandClaimRequest $claim, User $reviewer, string $reason): BrandClaimRequest
    {
        if (trim($reason) === '') {
            throw new RuntimeException('سبب الرفض إلزامي.');
        }

        $claim = $this->transition($claim, BrandClaimRequest::REJECTED, $reviewer, ['decision_reason' => $reason]);

        $this->notifyRequester($claim, 'لم تُعتمد مطالبتك', $reason);

        return $claim;
    }

    /** يُلغيه الطالب نفسه — لا المراجع. */
    public function cancel(BrandClaimRequest $claim, User $actor): BrandClaimRequest
    {
        $this->assertLive($claim);

        $ownsIt = $claim->requester_user_id === $actor->id
            || Str::lower($claim->requester_email) === Str::lower($actor->email);

        if (! $ownsIt) {
            throw new RuntimeException('لا تملك إلغاء هذا الطلب.');
        }

        $claim->update([
            'status' => BrandClaimRequest::CANCELLED,
            'cancelled_at' => now(),
        ]);

        AuditLogger::log('brand_claim.cancelled', $claim, [], null, $actor->id);

        return $claim->fresh();
    }

    /**
     * يُنهي الطلبات المنتهية صلاحيتها (مهمّة مجدوَلة).
     *
     * الانتهاء ليس تنظيفًا شكليًّا: طلبٌ معلّق إلى الأبد يُبقي العلامة في
     * منطقة رمادية، ويُبقي الفهرس الجزئي مانعًا صاحبَها الحقيقي من التقدّم.
     */
    public function expireDue(): int
    {
        $due = BrandClaimRequest::whereIn('status', BrandClaimRequest::LIVE)
            ->where('expires_at', '<=', now())->get();

        foreach ($due as $claim) {
            $claim->update(['status' => BrandClaimRequest::EXPIRED]);
            AuditLogger::log('brand_claim.expired', $claim, [], null, null);
            $this->notifyRequester($claim, 'انتهت صلاحية مطالبتك',
                'مضت مدّة الطلب دون اكتمال المراجعة. يمكنك التقدّم بطلب جديد.');
        }

        return $due->count();
    }

    // ===== داخلي =====

    private function transition(BrandClaimRequest $claim, string $to, User $reviewer, array $extra = []): BrandClaimRequest
    {
        $this->assertReviewer($reviewer);

        if (! $claim->canTransitionTo($to)) {
            throw new RuntimeException("انتقال غير مسموح: {$claim->status} ← {$to}");
        }

        $from = $claim->status;

        $claim->update($extra + [
            'status' => $to,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        AuditLogger::log("brand_claim.{$to}", $claim, [], null, $reviewer->id, ['from' => $from]);

        return $claim->fresh();
    }

    /**
     * المراجعة لمدير النظام وحده.
     *
     * لا يكفي أن يكون المستخدم في مستأجر العلامة: الطلب يقرّر نقل ملكية بين
     * مستأجرَين، فمراجعته من داخل أحدهما تجعل الخصم حَكَمًا.
     */
    private function assertReviewer(User $user): void
    {
        if (! $user->is_system_admin) {
            throw new RuntimeException('مراجعة المطالبات تحتاج صلاحية مدير النظام.');
        }
    }

    private function assertLive(BrandClaimRequest $claim): void
    {
        if (! $claim->isLive()) {
            throw new RuntimeException('هذا الطلب لم يعد مفتوحًا.');
        }
    }

    /** يُخطر أصحاب المساحة التي تضمّ العلامة — الملكية لا تُنقل من خلف ظهورهم. */
    private function notifyBrandSide(BrandClaimRequest $claim, string $title, string $body): void
    {
        TenantContext::withBypass(function () use ($claim, $title, $body) {
            $brand = Brand::withoutGlobalScopes()->find($claim->brand_id);
            if (! $brand) {
                return;
            }

            $admins = OrganizationMembership::withoutGlobalScopes()
                ->where('tenant_id', $brand->tenant_id)
                ->whereIn('role', [Role::AgencyAdmin->value, Role::BrandAdmin->value])
                ->where('status', 'active')
                ->pluck('user_id')->unique()->all();

            foreach ($admins as $userId) {
                $this->notifications->notify($brand->tenant_id, (int) $userId,
                    'brand_claim.opened', 'general', $title, $body, '/app/brands', [], $claim);
            }
        });
    }

    private function notifyRequester(BrandClaimRequest $claim, string $title, string $body): void
    {
        if (! $claim->requester_user_id) {
            return;
        }

        TenantContext::withBypass(function () use ($claim, $title, $body) {
            $membership = OrganizationMembership::withoutGlobalScopes()
                ->where('user_id', $claim->requester_user_id)->where('status', 'active')->first();

            if ($membership) {
                $this->notifications->notify($membership->tenant_id, $claim->requester_user_id,
                    'brand_claim.update', 'general', $title, $body, null, [], $claim);
            }
        });
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
