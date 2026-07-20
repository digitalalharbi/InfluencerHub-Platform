<?php

namespace App\Support\Showcase;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Actions\CreateSubscription;
use App\Domain\Billing\Models\{Plan, PlanVersion, PlanEntitlement, Subscription};
use App\Domain\Campaigns\Models\{Campaign, CampaignDeliverable, CampaignStatusHistory};
use App\Domain\Collaborations\Models\{Collaboration, CollaborationStatusHistory};
use App\Domain\Content\Models\{ContentItem, ContentApproval, ContentStatusHistory};
use App\Domain\Contracts\Models\{Contract, ContractStatusHistory};
use App\Domain\CRM\Models\{Brand, BrandStatusHistory, Client, ClientAddress, ClientBillingProfile, ClientContact, ClientDocument, ClientStatusHistory};
use App\Domain\Communications\Models\Notification;
use App\Domain\Creators\Models\{Creator, CreatorPlatform};
use App\Domain\Finance\Models\{Payout, PayoutStatusHistory};
use App\Domain\Identity\Models\User;
use App\Domain\Requests\Models\{ServiceRequest, ServiceRequestStatusHistory};
use App\Domain\Tenancy\Models\{Organization, OrganizationMembership, Tenant};
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use App\Domain\Creators\Services\CreatorCapabilityService;
use Illuminate\Support\Str;

/**
 * مُنشئ بيئة العرض التجريبية (Showcase) — بيانات مترابطة، وهمية بوضوح، محلية/اختبار فقط.
 *
 * مبادئ:
 *  - مستأجر مستقل بالكامل (slug=showcase) لا يخلط بيانات المستأجر التجريبي القائم.
 *  - Idempotent: reset() يحذف المستأجر (Cascade) والمستخدمين ثم build() يعيد التوليد.
 *  - حتمي (deterministic): pseudo-random مشتق من crc32 لثبات النتائج بين التشغيلات.
 *  - أمين: نبني فقط الكيانات التي لها جداول فعلية. لا Invoice/Task/AdCampaign/Payment/Tag
 *    (غير مُنمذجة) — المالية تُحسب من الحملات/المستحقات، و"المهام/الخطوة التالية" تُشتق من
 *    السجلات المعلّقة الحقيقية، والتصنيفات (VIP، فئة A/B/C) مشتقّة لا أعمدة مزيّفة.
 */
class ShowcaseBuilder
{
    public const TENANT_SLUG = 'showcase';
    public const USER_DOMAIN = 'showcase.test';
    private const SALT = 'ih-showcase-v1';

    private const SAR = 100; // وحدة صغرى: هللة

    // مجمّعات وهمية بوضوح (أسماء مخترعة، لا علامات/أشخاص حقيقيين)
    private const CLIENT_NAMES = ['نسيم','لمسة','أفق','بيت الذوق','واحة','رونق','مسك','درب','صدى','نُوّة','بصمة','منارة','طيف','ركن','سُحُب'];
    private const CLIENT_SUFFIX = ['التجارية','للتجارة','ستور','جروب','القابضة','ماركت'];
    private const SECTORS = ['تجزئة','أغذية ومشروبات','تقنية','أزياء','تجميل','سياحة','عقار','تعليم','صحة','سيارات'];
    private const CITIES = ['الرياض','جدة','الدمام','مكة','المدينة','الخبر','أبها','تبوك','بريدة','الطائف'];
    private const FIRST_NAMES = ['نورة','ريم','سارة','لمى','دانة','هند','جود','غلا','رغد','شهد','فيصل','خالد','عبدالله','تركي','ناصر','سلطان','ماجد','بدر','راكان','يزيد'];
    private const LAST_NAMES = ['القحطاني','العتيبي','الشمري','الدوسري','الحربي','الغامدي','الزهراني','المطيري','الرشيدي','العنزي','السبيعي','البقمي','الشهري','القرني','الأحمدي'];
    private const NICHES = ['أزياء','تجميل','تقنية','طعام','سفر','ألعاب','لياقة','عائلة','نمط حياة','رياضة'];
    private const PLATFORMS = ['instagram','tiktok','youtube','snapchat','x'];
    private const MGR_NAMES = ['سارة المنصور','خالد الفهد','ليان العمري','عبدالعزيز الناصر','رهف الحمد'];

    /** عدّادات ترقيم لكل نوع (per-tenant) */
    private array $seq = [];
    private ?Tenant $tenant = null;
    private ?Organization $org = null;
    private ?User $admin = null;
    /** @var User[] */ private array $managers = [];

    // ============================ Reset ============================
    /**
     * حذف كامل لبيئة العرض. حذف صف المستأجر يُطلق ON DELETE CASCADE لكل الجداول المُنطّقة.
     * الاستثناء: audit_logs (append-only + nullOnDelete) يمنع UPDATE/DELETE، لذا نعطّل
     * مشغّلاته مؤقتًا (محلي/اختبار فقط) داخل try/finally لضمان إعادة تفعيلها دائمًا.
     */
    public function reset(): void
    {
        $pg = DB::getDriverName() === 'pgsql';
        TenantContext::withBypass(function () use ($pg) {
        if ($pg) { DB::statement('ALTER TABLE audit_logs DISABLE TRIGGER USER'); }
        try {
            $t = Tenant::withoutGlobalScopes()->where('slug', self::TENANT_SLUG)->first();
            if ($t) {
                // نحذف سجلات تدقيق العرض أولًا (تفادي أعمدة معلّقة null-tenant)
                DB::table('audit_logs')->where('tenant_id', $t->id)->delete();
                DB::table('tenants')->where('id', $t->id)->delete();
            }
            // مستخدمو العرض ليسوا مُنطّقين بالمستأجر — نحذف سجلات تدقيقهم ثم المستخدمين
            $userIds = User::where('email', 'like', '%@' . self::USER_DOMAIN)->pluck('id');
            if ($userIds->isNotEmpty()) {
                DB::table('audit_logs')->whereIn('user_id', $userIds)->delete();
                User::whereIn('id', $userIds)->forceDelete();
            }
        } finally {
            if ($pg) { DB::statement('ALTER TABLE audit_logs ENABLE TRIGGER USER'); }
        }
        });
    }

    // ============================ Build ============================
    public function build(): array
    {
        $this->reset();

        TenantContext::withBypass(function () {
            DB::transaction(function () {
                $this->createTenantOrgUsersPlan();
                $creators = $this->createCreators();      // 160 (120 مؤثر + 40 UGC)
                $this->createClientStories($creators);    // 15 عميلًا بقصص تشغيلية مترابطة
                $this->createNotifications();              // إشعارات للمسؤول
            });
        });

        return $this->summary();
    }

    // ---------- Tenant / Org / Users / Plan ----------
    private function createTenantOrgUsersPlan(): void
    {
        $this->tenant = Tenant::create([
            'name' => 'InfluencerHub Showcase Agency',
            'slug' => self::TENANT_SLUG, 'deployment_mode' => 'saas', 'status' => 'active',
        ]);
        $this->org = Organization::create([
            'tenant_id' => $this->tenant->id, 'name' => 'InfluencerHub Showcase',
            'slug' => 'showcase-org', 'type' => 'agency', 'status' => 'active',
        ]);

        // اشتراك بحدود عالية (لا يقيّد حجم بيانات العرض)
        $plan = Plan::firstOrCreate(['key' => 'showcase'], ['name' => 'Showcase', 'is_active' => true]);
        $ver = PlanVersion::firstOrCreate(['plan_id' => $plan->id, 'version' => 1], ['is_active' => true]);
        foreach (['customers.max' => 1000, 'creators.max' => 3000, 'creator_applications.monthly.max' => 500,
                  'creator_storage.gb' => 100, 'creator_portal.enabled' => 1, 'ugc_creator.enabled' => 1,
                  'social_integrations.max' => 50] as $fk => $val) {
            PlanEntitlement::firstOrCreate(['plan_version_id' => $ver->id, 'feature_key' => $fk], ['value' => $val]);
        }
        if (! Subscription::where('organization_id', $this->org->id)->exists()) {
            (new CreateSubscription)->handle($this->org, $ver);
        }

        // كلمة المرور: من PREVIEW_PASSWORD أو مُولَّدة؛ تُكتب في ملف محلي غير متتبَّع
        $password = env('PREVIEW_PASSWORD') ?: Str::password(16);

        $this->admin = User::firstOrCreate(['email' => 'showcase_admin@' . self::USER_DOMAIN],
            ['name' => 'مدير وكالة العرض', 'is_active' => true, 'password' => $password]);
        OrganizationMembership::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'organization_id' => $this->org->id, 'user_id' => $this->admin->id],
            ['role' => 'agency_admin', 'status' => 'active']);

        foreach (self::MGR_NAMES as $i => $name) {
            $u = User::firstOrCreate(['email' => 'manager' . ($i + 1) . '@' . self::USER_DOMAIN],
                ['name' => $name, 'is_active' => true, 'password' => $password]);
            OrganizationMembership::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'organization_id' => $this->org->id, 'user_id' => $u->id],
                ['role' => $i % 2 ? 'campaign_manager' : 'creator_manager', 'status' => 'active']);
            $this->managers[] = $u;
        }

        $this->writeCredentials($password);
    }

    // ---------- Creators (160) ----------
    /** @return Creator[] */
    private function createCreators(): array
    {
        $creators = [];
        $total = 160; // 120 influencer + 40 ugc
        for ($i = 0; $i < $total; $i++) {
            $isUgc = $i >= 120;
            $type = $isUgc ? 'ugc_creator' : 'influencer';
            $name = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)] . ' ' . self::LAST_NAMES[($i * 7) % count(self::LAST_NAMES)];
            $followers = $isUgc ? $this->num("f$i", 1500, 60000) : $this->num("f$i", 8000, 1800000);
            $platform = self::PLATFORMS[$i % count(self::PLATFORMS)];
            // حالة الملف: أغلبهم نشط؛ بعضهم مبدئي/موقوف/محظور
            $statusPool = ['active','active','active','active','prospect','paused','blocked'];
            $status = $statusPool[$i % count($statusPool)];
            // توثيق موثوق: خليط
            $mowthooq = ['verified','verified','pending','not_provided'][$i % 4];
            // بعضهم "غير مكتمل" (بلا سيرة/سعر)
            $incomplete = ($i % 6) === 0;
            $rate = $isUgc ? $this->num("r$i", 300, 3000) : (int) max(500, round($followers / 400));

            $c = Creator::create([
                'tenant_id' => $this->tenant->id,
                'creator_number' => $this->next('CR', true),
                'type' => $type,
                'display_name' => $name,
                'handle' => Str::slug(self::LAST_NAMES[($i * 7) % count(self::LAST_NAMES)]) . '_' . $i,
                'primary_platform' => $platform,
                'followers_count' => $followers,
                'content_categories' => [self::NICHES[$i % count(self::NICHES)], self::NICHES[($i + 3) % count(self::NICHES)]],
                'status' => $status,
                'rate_per_post_minor' => $incomplete ? null : $rate * self::SAR,
                'bio' => $incomplete ? null : 'صانع محتوى في ' . self::NICHES[$i % count(self::NICHES)] . ' — بيانات تجريبية.',
                'city' => self::CITIES[$i % count(self::CITIES)],
                'gender' => $i % 2 ? 'male' : 'female',
                'languages' => ['ar', ($i % 3 ? 'en' : 'ar')],
                'mowthooq_status' => $mowthooq,
                'financial_verification_status' => $mowthooq === 'verified' ? 'verified' : 'not_provided',
                'created_by' => $this->admin->id,
            ]);
            // القدرات تُكتب كصفوف كبقيّة المسارات؛ بعض صنّاع UGC يجمعون قدرة إنتاجية
            // ثانية ليظهر في العرض أن القدرات تتقاطع فعلًا لا تتبادل.
            $caps = $isUgc ? ['ugc'] : ['influencer'];
            if ($i % 5 === 0) $caps[] = ['photographer', 'videographer', 'voiceover', 'editor', 'livestream'][$i % 5];
            CreatorCapabilityService::sync($c, $caps, 'showcase');

            // منصّات (1–2)
            CreatorPlatform::create(['tenant_id' => $this->tenant->id, 'creator_id' => $c->id,
                'platform' => $platform, 'handle' => '@' . $c->handle, 'followers_count' => $followers]);
            if ($i % 2) {
                $alt = self::PLATFORMS[($i + 2) % count(self::PLATFORMS)];
                CreatorPlatform::create(['tenant_id' => $this->tenant->id, 'creator_id' => $c->id,
                    'platform' => $alt, 'handle' => '@' . $c->handle . '_' . $alt,
                    'followers_count' => (int) round($followers * 0.4)]);
            }
            $creators[] = $c;
        }
        return $creators;
    }

    // ---------- Client operational stories ----------
    /** @param Creator[] $creators */
    private function createClientStories(array $creators): void
    {
        $brandDist = [3,2,1,2,1,2,3,1,2,1,2,1,1,2,1];   // مجموع = 25
        $campDist  = [3,2,1,2,0,2,3,1,2,0,2,1,1,2,2];   // مجموع = 24
        $reqDist   = [2,2,2,2,2,2,2,2,2,2,2,2,2,2,2];   // مجموع = 30
        $statusPool = ['active','active','active','qualified','qualified','lead','inactive'];

        $creatorCursor = 0;

        for ($i = 0; $i < 15; $i++) {
            $status = $statusPool[$i % count($statusPool)];
            $name = self::CLIENT_NAMES[$i] . ' ' . self::CLIENT_SUFFIX[$i % count(self::CLIENT_SUFFIX)];
            $mgr = $this->managers[$i % count($this->managers)];
            // سيناريو "عميل جديد ملفه غير مكتمل": المهتمّون/غير النشطين ببيانات ناقصة
            $incomplete = in_array($status, ['lead', 'inactive'], true);

            $client = Client::create([
                'tenant_id' => $this->tenant->id,
                'client_number' => $this->next('CL', true),
                'type' => 'company',
                'display_name' => $name,
                'legal_name' => $incomplete ? null : 'شركة ' . self::CLIENT_NAMES[$i] . ' — تجريبي',
                'status' => $status,
                'sector' => $incomplete ? null : self::SECTORS[$i % count(self::SECTORS)],
                'city' => self::CITIES[($i * 3) % count(self::CITIES)],
                'country_code' => 'SA',
                'email' => 'contact' . ($i + 1) . '@' . self::CLIENT_NAMES[$i] . '.demo',
                'phone' => $incomplete ? null : '+96650' . str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'website' => $incomplete ? null : 'https://' . self::CLIENT_NAMES[$i] . '.demo',
                'commercial_registration_number' => $incomplete ? null : '10' . str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                'tax_number' => $incomplete ? null : '30' . str_pad((string) (10000000000 + $i), 13, '0', STR_PAD_LEFT),
                'vat_registered' => $i % 2 === 0,
                'account_manager_id' => $incomplete ? null : $mgr->id,
                'preferred_language' => 'ar',
                'acquisition_source' => ['referral','ads','event','inbound'][$i % 4],
                'created_by' => $this->admin->id,
            ]);
            ClientStatusHistory::create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id,
                'from_status' => null, 'to_status' => $status, 'changed_by' => $this->admin->id]);

            // معلومات تواصل + عنوان + ملف فوترة
            ClientContact::create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id,
                'name' => self::FIRST_NAMES[$i % count(self::FIRST_NAMES)] . ' ' . self::LAST_NAMES[$i % count(self::LAST_NAMES)],
                'job_title' => 'مدير التسويق', 'email' => 'mkt' . $i . '@' . self::CLIENT_NAMES[$i] . '.demo',
                'phone' => '+96655' . str_pad((string) (2000000 + $i), 7, '0', STR_PAD_LEFT), 'is_primary' => true]);
            ClientAddress::create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id, 'type' => 'headquarters',
                'city' => self::CITIES[($i * 3) % count(self::CITIES)], 'country_code' => 'SA', 'region' => 'الوسطى',
                'street' => 'طريق الملك فهد', 'building_number' => (string) (1000 + $i), 'is_default' => true]);
            ClientBillingProfile::create(['tenant_id' => $this->tenant->id, 'client_id' => $client->id,
                'payment_terms_days' => [15,30,45][$i % 3], 'default_currency' => 'SAR']);

            // مستندات (3 لكل عميل → ~45، بعضها بانتظار المراجعة)
            for ($d = 0; $d < 3; $d++) {
                $this->makeDocument($client, $i, $d);
            }

            // علامات تجارية
            $brands = [];
            $bn = $brandDist[$i];
            for ($b = 0; $b < $bn; $b++) {
                $bStatus = ['approved','approved','under_review','draft'][($i + $b) % 4];
                $brand = Brand::create([
                    'tenant_id' => $this->tenant->id, 'client_id' => $client->id,
                    'name' => 'علامة ' . self::CLIENT_NAMES[$i] . ' ' . chr(65 + $b),
                    'slug' => Str::slug(self::CLIENT_NAMES[$i] . '-' . $b) . '-' . Str::lower(Str::random(4)),
                    'sector' => self::SECTORS[($i + $b) % count(self::SECTORS)],
                    'status' => $bStatus, 'created_by' => $this->admin->id,
                    'tone_of_voice' => 'ودّي واحترافي',
                ]);
                BrandStatusHistory::create(['tenant_id' => $this->tenant->id, 'brand_id' => $brand->id,
                    'from_status' => null, 'to_status' => $bStatus, 'actor_id' => $this->admin->id]);
                $brands[] = $brand;
            }

            // طلبات خدمة (بعضها متأخر)
            for ($r = 0; $r < $reqDist[$i]; $r++) {
                $this->makeRequest($client, $brands, $i, $r);
            }

            // حملات + تعاونات + محتوى + عقود + مستحقات
            $cn = $campDist[$i];
            for ($cc = 0; $cc < $cn; $cc++) {
                $this->makeCampaignStory($client, $brands, $creators, $creatorCursor, $i, $cc);
                $creatorCursor += 4;
            }
        }
    }

    private function makeDocument(Client $client, int $i, int $d): void
    {
        $cat = ['commercial_registration','vat','brief','contract'][($i + $d) % 4];
        $docStatus = ['approved','pending','under_review','approved'][($i + $d) % 4];
        $raw = 'showcase-' . $client->id . '-' . $d;
        ClientDocument::create([
            'tenant_id' => $this->tenant->id, 'client_id' => $client->id,
            'title' => ['السجل التجاري','شهادة ضريبة','بريف الحملة','عقد إطار'][($i + $d) % 4] . ' (تجريبي)',
            'category' => $cat, 'status' => $docStatus, 'visibility' => 'agency_internal',
            'path' => 'showcase/documents/' . $raw . '.pdf', 'original_name' => 'مستند-' . $d . '.pdf',
            'mime' => 'application/pdf', 'size_bytes' => $this->num("sz$raw", 40000, 900000),
            'checksum_sha256' => hash('sha256', $raw), 'uploaded_by' => $this->admin->id,
        ]);
    }

    private function makeRequest(Client $client, array $brands, int $i, int $r): void
    {
        $statusPool = ['submitted','triage','in_progress','needs_info','resolved','closed'];
        $status = $statusPool[($i + $r) % count($statusPool)];
        $open = in_array($status, ServiceRequest::OPEN_STATUSES, true);
        $overdue = $open && ($r % 2 === 0);
        $type = ['campaign','content','report','consultation'][($i + $r) % 4];
        $sr = ServiceRequest::create([
            'tenant_id' => $this->tenant->id, 'request_number' => $this->next('SR'),
            'requester_type' => 'client', 'requester_client_id' => $client->id, 'client_id' => $client->id,
            'brand_id' => $brands[0]->id ?? null, 'type' => $type,
            'title' => ['طلب حملة مؤثرين','مراجعة محتوى','تقرير أداء','استشارة تسويقية'][($i + $r) % 4] . ' — ' . $client->display_name,
            'description' => 'طلب تجريبي مترابط لأغراض العرض.',
            'priority' => ['normal','high','urgent','normal'][($i + $r) % 4],
            'status' => $status,
            'assigned_to' => $open ? $this->managers[$i % count($this->managers)]->id : null,
            'due_at' => $overdue ? now()->subDays(3 + $r) : ($open ? now()->addDays(4 + $r) : null),
            'sla_breached_at' => $overdue ? now()->subDays(1) : null,
            'resolved_at' => $status === 'resolved' ? now()->subDays(2) : null,
        ]);
        ServiceRequestStatusHistory::create(['tenant_id' => $this->tenant->id, 'service_request_id' => $sr->id,
            'from_status' => null, 'to_status' => 'submitted', 'actor_id' => $this->admin->id]);
        if ($status !== 'submitted') {
            ServiceRequestStatusHistory::create(['tenant_id' => $this->tenant->id, 'service_request_id' => $sr->id,
                'from_status' => 'submitted', 'to_status' => $status, 'actor_id' => $this->admin->id]);
        }
    }

    /** قصّة حملة كاملة: حملة → مخرجات → تعاونات → محتوى → موافقات → عقود → مستحقات */
    private function makeCampaignStory(Client $client, array $brands, array $creators, int $cursor, int $i, int $cc): void
    {
        $statusPool = ['active','active','completed','planning','paused','draft'];
        $status = $statusPool[($i + $cc) % count($statusPool)];
        $brand = $brands[$cc % max(1, count($brands))] ?? null;
        $month = ($i + $cc) % 7; // يناير..يوليو 2026
        $start = now()->startOfYear()->addMonths($month);
        $budgetSar = $this->num("bud$i$cc", 40000, 300000);
        $budgetMinor = $budgetSar * self::SAR;

        $camp = Campaign::create([
            'tenant_id' => $this->tenant->id, 'campaign_number' => $this->next('CM'),
            'client_id' => $client->id, 'brand_id' => $brand?->id,
            'name' => 'حملة ' . self::NICHES[($i + $cc) % count(self::NICHES)] . ' — ' . $client->display_name,
            'objective' => 'زيادة الوعي والتفاعل عبر مؤثرين مختارين — بيانات تجريبية.',
            'status' => $status, 'budget_minor' => $budgetMinor, 'currency' => 'SAR',
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addMonths(1)->toDateString(),
            'created_by' => $this->admin->id,
        ]);
        CampaignStatusHistory::create(['tenant_id' => $this->tenant->id, 'campaign_id' => $camp->id,
            'from_status' => null, 'to_status' => 'draft', 'actor_id' => $this->admin->id]);
        if ($status !== 'draft') {
            CampaignStatusHistory::create(['tenant_id' => $this->tenant->id, 'campaign_id' => $camp->id,
                'from_status' => 'draft', 'to_status' => $status, 'actor_id' => $this->admin->id]);
        }

        // عقد عميل لهذه الحملة (بعضها بانتظار التوقيع)
        $clientContractStatus = ['signed','active','sent','completed'][($i + $cc) % 4];
        Contract::create([
            'tenant_id' => $this->tenant->id, 'contract_number' => $this->next('CT'),
            'party_type' => 'client', 'client_id' => $client->id, 'campaign_id' => $camp->id,
            'title' => 'عقد حملة ' . $camp->campaign_number, 'value_minor' => $budgetMinor, 'currency' => 'SAR',
            'status' => $clientContractStatus, 'start_date' => $start->toDateString(),
            'sent_at' => now()->subDays(20), 'signed_at' => in_array($clientContractStatus, ['signed','active','completed']) ? now()->subDays(15) : null,
            'created_by' => $this->admin->id,
        ]);

        // مخرجات + تعاونات + محتوى + مستحقات لعدد من المبدعين
        $isActive = in_array($status, ['active','completed','paused'], true);
        if (! $isActive) {
            return; // مسودة/تخطيط: بلا مخرجات بعد
        }
        $perCamp = 5;
        $collabFeeTotal = 0;
        for ($k = 0; $k < $perCamp; $k++) {
            $creator = $creators[($cursor + $k) % count($creators)];
            $dType = ['reel','post','story','video'][$k % 4];
            $dPlatform = $creator->primary_platform;
            $feeSar = max(500, (int) round($budgetSar * 0.15));
            $feeMinor = $feeSar * self::SAR;
            $collabFeeTotal += $feeMinor;

            $delivStatus = ['approved','submitted','in_progress','assigned'][$k % 4];
            $deliv = CampaignDeliverable::create([
                'tenant_id' => $this->tenant->id, 'campaign_id' => $camp->id, 'creator_id' => $creator->id,
                'type' => $dType, 'platform' => $dPlatform, 'quantity' => 1 + ($k % 3),
                'fee_minor' => $feeMinor, 'currency' => 'SAR', 'status' => $delivStatus,
            ]);

            $collabStatus = $status === 'completed' ? 'completed' : ['accepted','in_progress','submitted','approved'][$k % 4];
            $collab = Collaboration::create([
                'tenant_id' => $this->tenant->id, 'collaboration_number' => $this->next('CO'),
                'creator_id' => $creator->id, 'campaign_id' => $camp->id, 'deliverable_id' => $deliv->id,
                'client_id' => $client->id, 'title' => $dType . ' — ' . $creator->display_name,
                'brief' => 'محتوى تجريبي متوافق مع هوية العلامة.', 'fee_minor' => $feeMinor, 'currency' => 'SAR',
                'status' => $collabStatus, 'due_date' => $start->copy()->addDays(20)->toDateString(),
                'offered_at' => now()->subDays(25), 'created_by' => $this->admin->id,
            ]);
            CollaborationStatusHistory::create(['tenant_id' => $this->tenant->id, 'collaboration_id' => $collab->id,
                'from_status' => null, 'to_status' => 'offered', 'actor_type' => 'agency', 'actor_id' => $this->admin->id]);

            // محتوى (بعضه بانتظار موافقة العميل)
            $contentStatus = $status === 'completed' ? 'published'
                : ['client_review','agency_review','approved','changes_requested'][$k % 4];
            $content = ContentItem::create([
                'tenant_id' => $this->tenant->id, 'content_number' => $this->next('CN'),
                'collaboration_id' => $collab->id, 'campaign_id' => $camp->id, 'deliverable_id' => $deliv->id,
                'creator_id' => $creator->id, 'client_id' => $client->id,
                'title' => $dType . ' ' . $camp->name, 'type' => $dType === 'video' ? 'video' : $dType,
                'platform' => $dPlatform, 'caption' => 'تسمية توضيحية تجريبية #' . $camp->campaign_number,
                'status' => $contentStatus, 'version' => 1,
                'scheduled_at' => $status === 'completed' ? null : now()->addDays(3 + $k),
                'published_at' => $status === 'completed' ? now()->subDays(5) : null,
                'created_by' => $this->admin->id,
            ]);
            ContentStatusHistory::create(['tenant_id' => $this->tenant->id, 'content_item_id' => $content->id,
                'from_status' => null, 'to_status' => 'draft', 'actor_type' => 'creator', 'actor_id' => $this->admin->id]);
            if (in_array($contentStatus, ['approved','published','client_review'], true)) {
                ContentApproval::create(['tenant_id' => $this->tenant->id, 'content_item_id' => $content->id,
                    'stage' => 'agency', 'decision' => 'approved', 'reviewer_type' => 'agency',
                    'reviewer_id' => $this->admin->id, 'note' => 'مطابق للبريف.']);
            }
            // قطعة محتوى مرافقة (ستوري) عند المخرجات متعددة الكمية
            if ($deliv->quantity >= 3) {
                ContentItem::create([
                    'tenant_id' => $this->tenant->id, 'content_number' => $this->next('CN'),
                    'collaboration_id' => $collab->id, 'campaign_id' => $camp->id, 'deliverable_id' => $deliv->id,
                    'creator_id' => $creator->id, 'client_id' => $client->id,
                    'title' => 'ستوري مرافق — ' . $camp->name, 'type' => 'story', 'platform' => $dPlatform,
                    'caption' => 'ستوري تجريبي #' . $camp->campaign_number, 'status' => 'draft', 'version' => 1,
                    'created_by' => $this->admin->id,
                ]);
            }

            // مستحق للمبدع: التعاونات المعتمدة/المكتملة (جاهز للصرف)، والمُقدَّمة (قيد الانتظار)
            if (in_array($collabStatus, ['approved','completed','submitted'], true)) {
                $payStatus = $collabStatus === 'submitted' ? 'pending' : ['approved','scheduled','paid','pending'][$k % 4];
                $overduePay = in_array($payStatus, ['approved','scheduled'], true) && ($k % 2 === 0);
                $payRef = $payStatus === 'paid' ? 'TRX-' . str_pad((string) $collab->id, 6, '0', STR_PAD_LEFT) : null;
                $p = Payout::create([
                    'tenant_id' => $this->tenant->id, 'payout_number' => $this->next('PY'),
                    'creator_id' => $creator->id, 'collaboration_id' => $collab->id, 'campaign_id' => $camp->id,
                    'description' => 'مستحق تعاون ' . $collab->collaboration_number, 'amount_minor' => $feeMinor,
                    'currency' => 'SAR', 'status' => $payStatus,
                    'due_date' => $overduePay ? now()->subDays(4)->toDateString() : now()->addDays(7)->toDateString(),
                    'paid_at' => $payStatus === 'paid' ? now()->subDays(3) : null,
                    'payment_reference' => $payRef,
                    'created_by' => $this->admin->id,
                ]);
                PayoutStatusHistory::create(['tenant_id' => $this->tenant->id, 'payout_id' => $p->id,
                    'from_status' => null, 'to_status' => $payStatus, 'actor_id' => $this->admin->id]);

                // عقد مبدع لبعض التعاونات (بانتظار توقيع)
                if ($k % 2 === 0) {
                    Contract::create([
                        'tenant_id' => $this->tenant->id, 'contract_number' => $this->next('CT'),
                        'party_type' => 'creator', 'creator_id' => $creator->id, 'client_id' => $client->id,
                        'collaboration_id' => $collab->id, 'campaign_id' => $camp->id,
                        'title' => 'عقد تعاون ' . $collab->collaboration_number, 'value_minor' => $feeMinor, 'currency' => 'SAR',
                        'status' => ['sent','signed','active'][$k % 3], 'sent_at' => now()->subDays(10),
                        'created_by' => $this->admin->id,
                    ]);
                }
            }
        }

        // تدقيق: نشاط الحملة
        AuditLog::create(['tenant_id' => $this->tenant->id, 'action' => 'campaign.' . $status,
            'auditable_type' => Campaign::class, 'auditable_id' => $camp->id, 'user_id' => $this->admin->id,
            'changes' => ['status' => $status]]);
    }

    private function createNotifications(): void
    {
        $types = [
            ['content.awaiting_review', 'محتوى بانتظار مراجعتك'],
            ['payout.ready', 'مستحق جاهز للصرف'],
            ['request.assigned', 'طلب خدمة أُسند إليك'],
            ['contract.awaiting_signature', 'عقد بانتظار التوقيع'],
            ['campaign.late', 'حملة متأخرة تحتاج تدخّلًا'],
        ];
        for ($i = 0; $i < 50; $i++) {
            [$t, $title] = $types[$i % count($types)];
            Notification::create([
                'tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'type' => $t,
                'title' => $title, 'body' => 'إشعار تجريبي #' . ($i + 1),
                'data' => ['showcase' => true, 'n' => $i + 1],
                'read_at' => $i % 3 === 0 ? now()->subDays($i % 5) : null,
            ]);
        }
    }

    // ============================ helpers ============================
    private function num(string $key, int $min, int $max): int
    {
        $h = crc32(self::SALT . $key);
        return $min + ($h % max(1, ($max - $min + 1)));
    }

    private function next(string $prefix, bool $pad = false): string
    {
        $this->seq[$prefix] = ($this->seq[$prefix] ?? 0) + 1;
        $n = $this->seq[$prefix];
        return $prefix . '-' . $this->tenant->id . '-' . ($pad ? str_pad((string) $n, 4, '0', STR_PAD_LEFT) : $n);
    }

    private function writeCredentials(string $password): void
    {
        // لا تلوّث ملف الاعتماد المحلي أثناء الاختبارات
        if (app()->runningUnitTests()) { return; }
        $dir = storage_path('app/private');
        if (! is_dir($dir)) { @mkdir($dir, 0700, true); }
        $path = $dir . '/showcase-credentials.txt';
        $lines = [
            '# بيانات دخول بيئة العرض (Showcase) — محلية/اختبار فقط، غير متتبَّعة بـGit',
            '# كلمة المرور: ' . $password,
            '',
            sprintf('%-30s %s', 'showcase_admin (agency_admin)', 'showcase_admin@' . self::USER_DOMAIN),
        ];
        foreach ($this->managers as $m) { $lines[] = sprintf('%-30s %s', 'manager', $m->email); }
        file_put_contents($path, implode("\n", $lines) . "\n");
        @chmod($path, 0600);
    }

    private function summary(): array
    {
        return TenantContext::withBypass(function () {
        $tid = $this->tenant->id;
        $count = fn (string $model) => $model::withoutGlobalScopes()->where('tenant_id', $tid)->count();
        $s = [
            'tenant' => $this->tenant->name,
            'clients' => $count(Client::class),
            'brands' => $count(Brand::class),
            'creators' => $count(Creator::class),
            'requests' => $count(ServiceRequest::class),
            'campaigns' => $count(Campaign::class),
            'collaborations' => $count(Collaboration::class),
            'content' => $count(ContentItem::class),
            'contracts' => $count(Contract::class),
            'payouts' => $count(Payout::class),
            'documents' => $count(ClientDocument::class),
            'notifications' => $count(Notification::class),
        ];

        return $s;
        });
    }
}
