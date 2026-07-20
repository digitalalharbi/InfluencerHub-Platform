<?php
namespace App\Domain\Creators\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Services\UsageMeterService;
use App\Domain\Creators\Models\{Creator, CreatorApplication, CreatorPlatform, CreatorService, CreatorPortfolio, CreatorApplicationStatusHistory};
use App\Domain\Creators\Services\CreatorCapabilityService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use RuntimeException;

/**
 * قبول طلب انضمام: معاملة واحدة ذرّية. يمنع القبول المزدوج، يتحقق من creators.max،
 * يستهلك Usage بمفتاح idempotent، ينشئ User+Creator+Membership، وينقل المنصات/الخدمات/الأعمال.
 * أي فشل → Rollback كامل (لا Usage يتيم، لا User بلا Creator).
 */
class ApproveCreatorApplication {
    public function __construct(private UsageMeterService $usage) {}

    public function handle(Organization $org, CreatorApplication $application, User $actor): Creator {
        $creator = DB::transaction(fn () => TenantContext::withTenant($org->tenant_id, function () use ($org, $application, $actor) {
            $app = CreatorApplication::whereKey($application->id)->lockForUpdate()->first();

            // منع القبول المزدوج
            if ($app->status === 'approved' || $app->creator_id) {
                throw new RuntimeException('الطلب مقبول مسبقًا.');
            }
            // منع تكرار مبدع بنفس البريد
            if ($app->email && Creator::where('email', $app->email)->exists()) {
                throw new RuntimeException('يوجد مبدع بنفس البريد.');
            }

            // حدّ الخطة (يرمي EntitlementLimitExceeded عند التجاوز → Rollback)
            $this->usage->consume($org, 'creators.max', 1, 'creator-application:approve:' . $app->id, $actor->id);

            // مستخدم لبوابة المبدع
            $user = User::create([
                'name' => $app->full_name ?: ($app->professional_name ?: 'مبدع'),
                'email' => $app->email ?: (Str::random(8) . '@creator.local'),
                'password' => Hash::make(Str::random(24)), // يُفعَّل عبر رابط لاحقًا
                'is_active' => true,
            ]);

            // إنشاء المبدع
            $seq = Creator::withTrashed()->where('tenant_id', $org->tenant_id)->count() + 1;
            $creator = Creator::create([
                'tenant_id' => $org->tenant_id,
                'creator_number' => 'CR-' . $org->tenant_id . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                // يُكتب هنا ثم يُعاد اشتقاقه في sync() أدناه — القدرات هي المرجع
                'type' => $app->account_type ?: 'influencer',
                'display_name' => $app->professional_name ?: $app->full_name,
                'professional_name' => $app->professional_name,
                'email' => $app->email, 'phone' => $app->phone, 'whatsapp' => $app->whatsapp,
                'city' => $app->city, 'country_code' => $app->country_code, 'gender' => $app->gender,
                'languages' => $app->languages, 'bio' => $app->bio, 'content_categories' => $app->categories,
                'status' => 'active', 'user_id' => $user->id, 'created_by' => $actor->id,
                'mowthooq_license_number' => $app->mowthooq_license_number, 'mowthooq_expires_at' => $app->mowthooq_expires_at,
                'mowthooq_status' => $app->mowthooq_status,
                'beneficiary_name' => $app->beneficiary_name, 'bank_name' => $app->bank_name,
                'iban_encrypted' => $app->iban_encrypted, 'iban_last4' => $app->iban_last4,
                'financial_verification_status' => $app->financial_verification_status,
            ]);

            // نقل القدرات كما صرّح بها المتقدّم. الرجوع إلى النوع القديم يخصّ
            // الطلبات التي سبقت عمود `capabilities` فقط، فلا يُقبل طلب بلا قدرة.
            $caps = CreatorCapabilityService::normalize($app->capabilities ?? []);
            if (! $caps) {
                $caps = CreatorCapabilityService::LEGACY_TO_CAPS[$app->account_type] ?? ['influencer'];
            }
            CreatorCapabilityService::sync($creator, $caps, 'application');

            // نقل المنصات/الخدمات/الأعمال
            foreach ($app->platforms as $p) {
                CreatorPlatform::create(['tenant_id' => $org->tenant_id, 'creator_id' => $creator->id,
                    'platform' => $p->platform, 'handle' => $p->username ?? '', 'url' => $p->profile_url, 'followers_count' => $p->followers_count]);
            }
            foreach ($app->services as $s) {
                CreatorService::create(['tenant_id' => $org->tenant_id, 'creator_id' => $creator->id, 'service_type' => $s->service_type,
                    'price_minor' => $s->price_minor, 'currency' => $s->currency, 'delivery_days' => $s->delivery_days,
                    'revision_rounds' => $s->revision_rounds, 'usage_rights_days' => $s->usage_rights_days,
                    'description' => $s->description, 'is_available' => $s->is_available]);
            }
            foreach ($app->portfolios as $pf) {
                CreatorPortfolio::create(['tenant_id' => $org->tenant_id, 'creator_id' => $creator->id, 'type' => $pf->type,
                    'url' => $pf->url, 'path' => $pf->path, 'category' => $pf->category, 'previous_brand' => $pf->previous_brand,
                    'description' => $pf->description, 'status' => $pf->status, 'sort_order' => $pf->sort_order]);
            }

            // خطة نقل الملفات: تُسجَّل داخل المعاملة (pending). النسخ الفعلي يتم بعد Commit
            // عبر FinalizeCreatorFilesJob (معاملة قاعدة البيانات لا تجعل عمليات التخزين ذرّية).
            foreach ($app->documents as $doc) {
                if ($doc->transfer_status !== 'completed') { $doc->update(['transfer_status' => 'pending']); }
            }

            // عضوية المبدع (تتيح الدخول لبوابته). الدور يُشتقّ من القدرات لا من
            // النص القديم، وإلا حصل مصوّر اختار UGC على دور «مؤثّر» بالخطأ.
            $role = match (CreatorCapabilityService::legacyType($caps)) {
                'ugc_creator' => 'ugc_creator',
                'both' => 'influencer_and_ugc',
                default => 'influencer',
            };
            OrganizationMembership::create(['tenant_id' => $org->tenant_id, 'organization_id' => $org->id,
                'user_id' => $user->id, 'role' => $role, 'status' => 'active']);

            // ربط + حالة
            $from = $app->status;
            $app->update(['status' => 'approved', 'creator_id' => $creator->id, 'user_id' => $user->id, 'reviewed_at' => now()]);
            CreatorApplicationStatusHistory::create(['tenant_id' => $org->tenant_id, 'application_id' => $app->id,
                'from_status' => $from, 'to_status' => 'approved', 'actor_id' => $actor->id, 'reason' => 'قبول وإنشاء حساب المبدع', 'occurred_at' => now()]);
            AuditLogger::log('creator_application.approved', $app, ['creator_id' => $creator->id, 'user_id' => $user->id], $org->tenant_id, $actor->id);

            // TODO(Queue بعد Commit): إرسال رابط تفعيل + إشعار الوكالة والمتقدّم
            return $creator;
        }, $org->id));

        // بعد نجاح Commit: إتمام نقل الملفات (post-commit، idempotent، قابل للتعويض)
        \App\Domain\Creators\Jobs\FinalizeCreatorFilesJob::dispatch($application->id);
        return $creator;
    }
}
