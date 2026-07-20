<?php
namespace App\Console\Commands;
use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\CRM\Actions\CreateClient;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Tenant, Organization, OrganizationMembership};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * حالة معلومة وحتمية لاختبارات Playwright. مقيّد بغير الإنتاج فقط.
 * ينشئ مستأجرين (لاختبار العزل) + أدوارًا متعددة + عملاء.
 */
class SeedE2eCommand extends Command {
    protected $signature = 'e2e:seed';
    protected $description = 'تهيئة بيانات E2E حتمية (غير إنتاجي)';

    public function handle(): int {
        if (app()->environment('production')) { $this->error('ممنوع في الإنتاج.'); return 1; }

        // ==== المستأجر A (وكالة كاملة) ====
        $tA = Tenant::create(['name' => 'وكالة ألف', 'slug' => 'agency-a', 'deployment_mode' => 'saas', 'status' => 'active']);
        [$orgA, $admin, $viewer] = TenantContext::withBypass(function () use ($tA) {
        $orgA = Organization::create(['tenant_id' => $tA->id, 'name' => 'ألف', 'slug' => 'org-a', 'type' => 'agency']);
        $admin = $this->user('مدير الوكالة', 'admin@a.test');
        $viewer = $this->user('مشاهد', 'viewer@a.test');
        $this->member($tA, $orgA, $admin, 'agency_admin');
        $this->member($tA, $orgA, $viewer, 'viewer');
        $plan = Plan::create(['key' => 'pro', 'name' => 'احترافي', 'is_active' => true]);
        $v = PlanVersion::create(['plan_id' => $plan->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'customers.max', 'value' => 50]);
        PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => 'creators.max', 'value' => 50]);
        foreach (['creator_applications.monthly.max' => 50, 'creator_storage.gb' => 10, 'creator_portal.enabled' => 1, 'ugc_creator.enabled' => 1, 'social_integrations.max' => 10] as $fk => $val) {
            PlanEntitlement::create(['plan_version_id' => $v->id, 'feature_key' => $fk, 'value' => $val]);
        }
        (new CreateSubscription)->handle($orgA, $v);

        return [$orgA, $admin, $viewer];
        });

        [$nikeClient, $firstCreator] = TenantContext::withTenant($tA->id, function () use ($tA, $orgA, $admin) {
        $nikeClient = null;
        foreach ([['نايك السعودية', 'active', 'رياضة'], ['stc', 'active', 'اتصالات'], ['مطاعم البيك', 'qualified', 'أغذية'], ['نون', 'lead', 'تجارة']] as [$n, $s, $sec]) {
            $cl = app(CreateClient::class)->handle($orgA, ['display_name' => $n, 'status' => $s, 'type' => 'company', 'sector' => $sec], $admin);
            $nikeClient ??= $cl;
        }
        // Phase 5 — عضو بوابة عميل (client_admin) على نايك
        $clientUser = $this->user('عميل نايك', 'client@a.test');
        \App\Domain\CRM\Models\ClientMember::create(['tenant_id' => $tA->id, 'client_id' => $nikeClient->id, 'user_id' => $clientUser->id, 'role' => 'client_admin', 'status' => 'active', 'accepted_at' => now()]);
        // علامة معتمدة جاهزة + مسودة لاختبار سير العمل
        // (كان هنا إعادة ضبط للسياق تعويضًا عن `createDraft`؛ صارت تستعيد ما كان بنفسها)
        app(\App\Domain\CRM\Services\BrandWorkflowService::class)->createDraft($tA->id, $nikeClient->id, ['name' => 'Nike Air'], $clientUser->id);
        // مبدعون (Phase 4): مؤثّرون + صنّاع UGC لاختبار التصفية بالنوع
        $firstCreator = null;
        foreach ([['نورة القحطاني', 'influencer', 'active'], ['فيصل العتيبي', 'influencer', 'active'], ['ستوديو لقطة', 'ugc_creator', 'prospect'], ['محمد الشمري', 'both', 'paused']] as [$n, $ty, $st]) {
            $cr = app(\App\Domain\Creators\Actions\CreateCreator::class)->handle($orgA, ['display_name' => $n, 'type' => $ty, 'status' => $st, 'primary_platform' => 'instagram', 'followers_count' => 10000], $admin);
            $firstCreator ??= $cr;
        }
        // ربط مبدع بحساب دخول لبوابة المبدع
        $creatorUser = $this->user('نورة القحطاني', 'creator@a.test');
        $firstCreator->update(['user_id' => $creatorUser->id]);

        return [$nikeClient, $firstCreator];
        }, $orgA->id);

        // طلب انضمام مُرسَل (لاختبار المراجعة والقبول)
        $svc = app(\App\Domain\Creators\Services\CreatorApplicationService::class);
        $appl = $svc->startDraft($tA, ['account_type' => 'influencer', 'full_name' => 'ريناد الزهراني', 'professional_name' => 'Renad', 'email' => 'renad@applicant.test', 'phone' => '+966505556677', 'country_code' => 'SA', 'city' => 'جدة']);
        TenantContext::withTenant($tA->id, function () use ($appl, $tA) {
            $appl->update(['email_verified_at' => now(), 'categories' => ['fashion', 'beauty']]);
            \App\Domain\Creators\Models\CreatorApplicationPlatform::create(['tenant_id' => $tA->id, 'application_id' => $appl->id, 'platform' => 'instagram', 'username' => 'renad.style', 'followers_count' => 180000, 'status' => 'manual_unverified']);
        });
        $svc->transition($appl, 'submitted', null, ['submitted_at' => now()]);
        $svc->transition($appl->fresh(), 'under_review');

        // ==== Phase 5 — وكالة خارجية معتمدة + عضو شريك + ربط مُنطّق + دعوة معلّقة برمز معلوم ====
        $wf = app(\App\Domain\Partners\Services\ExternalAgencyWorkflowService::class);
        $partnerAgency = $wf->createDraft($tA->id, ['name' => 'وكالة نجمة الإبداع', 'contact_name' => 'سارة', 'specialization' => 'إنتاج فيديو'], $admin->id);
        $partnerAgency = $wf->approve($wf->startReview($wf->submit($partnerAgency, $admin->id), $admin->id), $admin->id);
        TenantContext::withTenant($tA->id, function () use ($tA, $partnerAgency, $nikeClient, $admin) {
        $partnerUser = $this->user('سارة الشريك', 'partner@a.test');
        \App\Domain\Partners\Models\ExternalAgencyMember::create(['tenant_id' => $tA->id, 'external_agency_id' => $partnerAgency->id, 'user_id' => $partnerUser->id, 'role' => 'partner_admin', 'status' => 'active', 'accepted_at' => now()]);
        \App\Domain\Partners\Models\PartnerClientLink::create(['tenant_id' => $tA->id, 'external_agency_id' => $partnerAgency->id, 'client_id' => $nikeClient->id, 'scopes' => ['view_briefs', 'submit_content'], 'status' => 'active', 'created_by' => $admin->id]);
        // دعوة معلّقة برمز معلوم لاختبار تدفّق القبول العام
        \App\Domain\Partners\Models\ExternalAgencyInvitation::create(['tenant_id' => $tA->id, 'external_agency_id' => $partnerAgency->id, 'email' => 'invited@partner.test', 'role' => 'partner_member', 'token_hash' => hash('sha256', 'e2e-partner-invite'), 'invited_by' => $admin->id, 'expires_at' => now()->addDays(7)]);
        });

        // ==== المستأجر B (لاختبار العزل) بحدّ 1 ====
        $tB = Tenant::create(['name' => 'وكالة باء', 'slug' => 'agency-b', 'deployment_mode' => 'saas', 'status' => 'active']);
        [$orgB, $adminB] = TenantContext::withBypass(function () use ($tB) {
        $orgB = Organization::create(['tenant_id' => $tB->id, 'name' => 'باء', 'slug' => 'org-b', 'type' => 'agency']);
        $adminB = $this->user('مدير باء', 'admin@b.test');
        $this->member($tB, $orgB, $adminB, 'agency_admin');
        $planB = Plan::create(['key' => 'basic', 'name' => 'أساسي', 'is_active' => true]);
        $vB = PlanVersion::create(['plan_id' => $planB->id, 'version' => 1, 'is_active' => true]);
        PlanEntitlement::create(['plan_version_id' => $vB->id, 'feature_key' => 'customers.max', 'value' => 1]);
        (new CreateSubscription)->handle($orgB, $vB);

        return [$orgB, $adminB];
        });

        TenantContext::withTenant($tB->id, function () use ($orgB, $adminB) {
            app(CreateClient::class)->handle($orgB, ['display_name' => 'عميل باء الوحيد', 'status' => 'active', 'type' => 'company'], $adminB);
        }, $orgB->id);

        $this->info('E2E seeded: admin@a.test / viewer@a.test / admin@b.test / creator@a.test / client@a.test / partner@a.test (E2E_PASSWORD)');
        return 0;
    }

    /** كلمة مرور E2E من البيئة (E2E_PASSWORD) — لا ثابتة في المصدر. */
    private function pw(): string { return env('E2E_PASSWORD', 'e2e-local-secret'); }
    private function user(string $name, string $email): User {
        return User::create(['name' => $name, 'email' => $email, 'password' => $this->pw(), 'is_active' => true]);
    }
    private function member(Tenant $t, Organization $o, User $u, string $role): void {
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $o->id, 'user_id' => $u->id, 'role' => $role, 'status' => 'active']);
    }
}
