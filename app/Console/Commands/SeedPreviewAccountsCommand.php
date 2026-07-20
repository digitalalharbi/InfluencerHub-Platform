<?php
namespace App\Console\Commands;

use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement};
use App\Domain\CRM\Actions\CreateClient;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * حسابات معاينة محلية لكل دور (local/E2E فقط، ممنوع في الإنتاج).
 * كلمة المرور من PREVIEW_PASSWORD أو تُولَّد؛ تُكتب البيانات في ملف محلي غير متتبَّع بـGit.
 */
class SeedPreviewAccountsCommand extends Command {
    protected $signature = 'preview:seed {--fresh : أعد إنشاء بيانات المعاينة}';
    protected $description = 'حسابات معاينة محلية لكل دور (غير إنتاجي)';

    private const ORG_ROLES = ['system_admin','agency_admin','campaign_manager','creator_manager','finance'];

    public function handle(): int {
        if (app()->environment('production')) { $this->error('ممنوع في الإنتاج.'); return self::FAILURE; }
        // لا كلمة مرور ثابتة: من .env.preview أو تُولَّد عشوائيًا في كل تشغيل
        $password = env('PREVIEW_PASSWORD') ?: \Illuminate\Support\Str::password(16);

        $tenant = Tenant::where('slug', 'demo')->first();
        if (! $tenant) {
            $tenant = Tenant::create(['name' => 'وكالة تجريبية', 'slug' => 'demo', 'deployment_mode' => 'saas', 'status' => 'active']);
        }
        [$org, $rows] = TenantContext::withBypass(function () use ($tenant, $password) {
        $org = Organization::where('tenant_id', $tenant->id)->first()
            ?? Organization::create(['tenant_id' => $tenant->id, 'name' => 'الوكالة', 'slug' => 'demo-org', 'type' => 'agency']);

        // اشتراك بحد كافٍ
        if (! \App\Domain\Billing\Models\Subscription::where('organization_id', $org->id)->exists()) {
            $plan = Plan::firstOrCreate(['key' => 'preview'], ['name' => 'معاينة', 'is_active' => true]);
            $v = PlanVersion::firstOrCreate(['plan_id' => $plan->id, 'version' => 1], ['is_active' => true]);
            PlanEntitlement::firstOrCreate(['plan_version_id' => $v->id, 'feature_key' => 'customers.max'], ['value' => 100]);
            foreach (['creators.max' => 100, 'creator_applications.monthly.max' => 50, 'creator_storage.gb' => 10, 'creator_portal.enabled' => 1, 'ugc_creator.enabled' => 1, 'social_integrations.max' => 10] as $fk => $val) {
                PlanEntitlement::firstOrCreate(['plan_version_id' => $v->id, 'feature_key' => $fk], ['value' => $val]);
            }
            (new CreateSubscription)->handle($org, $v);
        }

        $rows = [];
        // أدوار المؤسسة (الوكالة)
        foreach (self::ORG_ROLES as $role) {
            $email = "{$role}@demo.test";
            $u = User::firstOrCreate(['email' => $email], ['name' => ucfirst(str_replace('_', ' ', $role)), 'is_active' => true, 'password' => $password]);
            $u->update(['password' => $password]); // تدوير كلمة المرور في كل تشغيل
            if ($role === 'system_admin') { $u->forceFill(['is_system_admin' => true])->save(); }
            OrganizationMembership::firstOrCreate(
                ['tenant_id' => $tenant->id, 'organization_id' => $org->id, 'user_id' => $u->id],
                ['role' => $role, 'status' => 'active'],
            );
            $rows[] = [$role, $email];
        }
        // أدوار خارج الوكالة (المبدع/البوابة) — حسابات مستخدم فقط الآن (بواباتها تُبنى في مراحل لاحقة)
        foreach (['client_admin','client_member','influencer','ugc_creator'] as $role) {
            $email = "{$role}@demo.test";
            $u = User::firstOrCreate(['email' => $email], ['name' => ucfirst(str_replace('_', ' ', $role)), 'is_active' => true, 'password' => $password]);
            $u->update(['password' => $password]);
            $rows[] = [$role, $email];
        }

        return [$org, $rows];
        });

        // عملاء تجريبيون إن لم يوجدوا
        TenantContext::withTenant($tenant->id, function () use ($tenant, $org) {
        if (\App\Domain\CRM\Models\Client::count() === 0) {
            $admin = User::where('email', 'agency_admin@demo.test')->first();
            foreach ([['نايك السعودية', 'active', 'رياضة'], ['stc', 'active', 'اتصالات'], ['مطاعم البيك', 'qualified', 'أغذية'], ['نون', 'lead', 'تجارة']] as [$n, $s, $sec]) {
                app(CreateClient::class)->handle($org, ['display_name' => $n, 'status' => $s, 'type' => 'company', 'sector' => $sec], $admin);
            }
        }
        // مبدعون (Phase 4)
        if (\App\Domain\Creators\Models\Creator::count() === 0) {
            $admin = User::where('email', 'agency_admin@demo.test')->first();
            foreach ([['نورة القحطاني', 'influencer', 'noura_q', 'instagram', 250000, 'active'], ['فيصل العتيبي', 'influencer', 'faisal', 'tiktok', 480000, 'active'], ['ستوديو لقطة', 'ugc_creator', 'laqta', 'instagram', 12000, 'prospect'], ['ريم UGC', 'ugc_creator', 'reem.ugc', 'tiktok', 8000, 'active'], ['محمد الشمري', 'both', 'm_shammari', 'youtube', 95000, 'paused']] as [$n, $ty, $h, $pf, $fol, $st]) {
                app(\App\Domain\Creators\Actions\CreateCreator::class)->handle($org, ['display_name' => $n, 'type' => $ty, 'handle' => $h, 'primary_platform' => $pf, 'followers_count' => $fol, 'status' => $st], $admin);
            }
        }
        // طلب انضمام تجريبي (مُرسَل، للمراجعة)
        if (\App\Domain\Creators\Models\CreatorApplication::count() === 0) {
            $svc = app(\App\Domain\Creators\Services\CreatorApplicationService::class);
            $appl = $svc->startDraft($tenant, ['account_type' => 'influencer', 'full_name' => 'ريناد الزهراني',
                'professional_name' => 'Renad', 'email' => 'renad@applicant.test', 'phone' => '+966505556677', 'country_code' => 'SA', 'city' => 'جدة']);
            TenantContext::withTenant($tenant->id, function () use ($appl, $tenant) {
                $appl->update(['email_verified_at' => now(), 'bio' => 'صانعة محتوى أزياء وجمال.', 'categories' => ['fashion', 'beauty']]);
                \App\Domain\Creators\Models\CreatorApplicationPlatform::create(['tenant_id' => $tenant->id, 'application_id' => $appl->id,
                    'platform' => 'instagram', 'username' => 'renad.style', 'followers_count' => 180000, 'status' => 'manual_unverified']);
            });
            $svc->transition($appl, 'submitted', null, ['submitted_at' => now()]);
            $svc->transition($appl->fresh(), 'under_review');
        }
        }, $org->id);

        // اكتب البيانات في تخزين خاص (غير متتبَّع بـGit، خارج الويب، صلاحيات محدودة)
        $dir = storage_path('app/private');
        if (! is_dir($dir)) { mkdir($dir, 0700, true); }
        $path = $dir . '/preview-credentials.txt';
        $lines = ["# حسابات معاينة محلية — InfluencerHub V2 (لا تُرفع إلى Git، غير إنتاجي)", "# كلمة المرور للجميع: {$password}", ''];
        foreach ($rows as [$role, $email]) { $lines[] = sprintf('%-22s %s', $role, $email); }
        file_put_contents($path, implode("\n", $lines) . "\n");
        @chmod($path, 0600); // صلاحيات وصول محدودة

        // لا تُطبَع كلمة المرور في المخرجات/التقارير
        $this->info('حسابات المعاينة جاهزة (' . count($rows) . ' دورًا). كلمة المرور في storage/app/private/preview-credentials.txt (غير متتبَّع).');
        $this->line('كلمة المرور: من PREVIEW_PASSWORD أو مُولَّدة عشوائيًا في كل تشغيل.');
        return self::SUCCESS;
    }
}
