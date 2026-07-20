<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Collaborations\Models\Collaboration;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Creators\Models\{Creator, CreatorApplication};
use App\Domain\CRM\Models\{Brand, Client};
use App\Domain\Finance\Models\Payout;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\ExternalAgency;
use App\Domain\Publishers\Models\Publisher;
use App\Domain\Requests\Models\ServiceRequest;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Platforms\PlatformRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, DB};
use Inertia\Inertia;
use Inertia\Response;

/**
 * مختبر رحلات المنتَج — صفحة تطوير فقط (`/product-lab`).
 *
 * الغرض: رؤية المنتَج كرحلات مترابطة لا كوحدات منفصلة، وقياس أين تنقطع كل
 * رحلة فعليًا من البيانات لا من التوقّع. كل خطوة تُحسب من قاعدة البيانات:
 * «منجزة» تعني وجود سجلّ فعلي يثبتها.
 *
 * محجوبة في الإنتاج (404) ولا تعرض كلمات مرور ولا أسرارًا ولا رموزًا.
 */
class ProductLabController extends Controller
{
    private function guard(): void
    {
        abort_if(app()->environment('production'), 404);
    }

    public function index(Request $r): Response
    {
        $this->guard();

        // نقرأ حالة كل المستأجرين لرسم الرحلات — سياق الطلب يُستعاد بعدها
        $payload = TenantContext::withBypass(fn () => [
            'journeys' => $this->journeys(),
            'modules' => $this->modules(),
            'accounts' => $this->accounts(),
            'integrations' => $this->integrations(),
            'blockers' => $this->blockers(),
            'services' => $this->services(),
            'dataset' => $this->dataset(),
            'changelog' => $this->changelog(),
        ]);

        return Inertia::render('ProductLab/Index', $payload);
    }

    /**
     * الرحلات التشغيلية وخطواتها — كل خطوة تُقاس بعدّ سجلّات حقيقية.
     * `done` يعني: يوجد في النظام ما يثبت أن هذه الخطوة تُنفَّذ فعلًا.
     *
     * @return array<int,array<string,mixed>>
     */
    private function journeys(): array
    {
        $has = fn (string $model, ?callable $scope = null) => (int) ($scope
            ? $scope($model::withoutGlobalScopes())->count()
            : $model::withoutGlobalScopes()->count());

        $campaignsFromRequest = Campaign::withoutGlobalScopes()->whereNotNull('source_request_id')->count();
        $signedContracts = Contract::withoutGlobalScopes()->whereNotNull('signed_at')->count();
        $publishedContent = ContentItem::withoutGlobalScopes()->where('status', 'published')->count();
        $paidPayouts = Payout::withoutGlobalScopes()->where('status', 'paid')->count();
        $convertedPublishers = Creator::withoutGlobalScopes()->whereNotNull('publisher_id')->count();
        $approvedApplications = CreatorApplication::withoutGlobalScopes()->where('status', 'approved')->count();

        return [
            [
                'key' => 'client_campaign',
                'title' => 'من الطلب إلى إغلاق الحملة',
                'subtitle' => 'الرحلة التجارية الأساسية: طلب العميل يتحوّل إلى حملة منفَّذة ومُغلَقة',
                'start' => '/app/service-requests',
                'steps' => [
                    ['label' => 'طلب خدمة وارد', 'done' => $has(ServiceRequest::class) > 0, 'count' => $has(ServiceRequest::class), 'link' => '/app/service-requests'],
                    ['label' => 'فرز الطلب (SLA)', 'done' => ServiceRequest::withoutGlobalScopes()->whereNotIn('status', ['submitted'])->count() > 0, 'count' => ServiceRequest::withoutGlobalScopes()->whereNotIn('status', ['submitted'])->count(), 'link' => '/app/service-requests'],
                    ['label' => 'عميل مرتبط', 'done' => $has(Client::class) > 0, 'count' => $has(Client::class), 'link' => '/app/clients'],
                    ['label' => 'علامة معتمدة', 'done' => Brand::withoutGlobalScopes()->where('status', 'approved')->count() > 0, 'count' => Brand::withoutGlobalScopes()->where('status', 'approved')->count(), 'link' => '/app/brands'],
                    ['label' => 'تحويل الطلب إلى حملة', 'done' => $campaignsFromRequest > 0, 'count' => $campaignsFromRequest, 'link' => '/app/campaigns', 'note' => 'يحفظ العلاقة بالمصدر ولا ينشئ سجلًا مكرّرًا'],
                    ['label' => 'مخرجات الحملة', 'done' => DB::table('campaign_deliverables')->count() > 0, 'count' => (int) DB::table('campaign_deliverables')->count(), 'link' => '/app/campaigns'],
                    ['label' => 'ترشيح المؤثرين', 'done' => DB::table('campaign_shortlist_items')->count() > 0, 'count' => (int) DB::table('campaign_shortlist_items')->count(), 'link' => '/app/shortlisting'],
                    ['label' => 'قرار العميل على الترشيح', 'done' => DB::table('campaign_shortlist_items')->where('client_decision', '!=', 'pending')->count() > 0, 'count' => (int) DB::table('campaign_shortlist_items')->where('client_decision', '!=', 'pending')->count(), 'link' => '/app/shortlisting'],
                    ['label' => 'تعاونات', 'done' => $has(Collaboration::class) > 0, 'count' => $has(Collaboration::class), 'link' => '/app/collaborations'],
                    ['label' => 'عقود موقّعة', 'done' => $signedContracts > 0, 'count' => $signedContracts, 'link' => '/app/contracts'],
                    ['label' => 'محتوى مرفوع', 'done' => $has(ContentItem::class) > 0, 'count' => $has(ContentItem::class), 'link' => '/app/content'],
                    ['label' => 'مراجعة العميل واعتماده', 'done' => ContentItem::withoutGlobalScopes()->whereIn('status', ['approved', 'scheduled', 'published'])->count() > 0, 'count' => ContentItem::withoutGlobalScopes()->whereIn('status', ['approved', 'scheduled', 'published'])->count(), 'link' => '/app/content'],
                    ['label' => 'نشر وإثبات', 'done' => $publishedContent > 0, 'count' => $publishedContent, 'link' => '/app/content'],
                    ['label' => 'فاتورة العميل', 'done' => false, 'count' => 0, 'link' => null, 'blocker' => 'لا وحدة فواتير — عائق تجاري موثّق'],
                    ['label' => 'تسجيل الدفع', 'done' => false, 'count' => 0, 'link' => null, 'blocker' => 'لا مزوّد دفع — عائق تجاري موثّق'],
                    ['label' => 'اعتماد مستحقات المؤثرين', 'done' => Payout::withoutGlobalScopes()->whereIn('status', ['approved', 'scheduled', 'paid'])->count() > 0, 'count' => Payout::withoutGlobalScopes()->whereIn('status', ['approved', 'scheduled', 'paid'])->count(), 'link' => '/app/payouts'],
                    ['label' => 'صرف المستحق', 'done' => $paidPayouts > 0, 'count' => $paidPayouts, 'link' => '/app/payouts', 'note' => 'تسجيل يدوي بمرجع — النظام لا ينفّذ تحويلًا'],
                    ['label' => 'تقرير وإغلاق', 'done' => Campaign::withoutGlobalScopes()->where('status', 'completed')->count() > 0, 'count' => Campaign::withoutGlobalScopes()->where('status', 'completed')->count(), 'link' => '/app/reports'],
                ],
            ],
            [
                'key' => 'creator',
                'title' => 'من طلب الانضمام إلى الصرف',
                'subtitle' => 'رحلة المؤثر: انضمام موثَّق ثم تعاون ثم استحقاق',
                'start' => '/app/creator-applications',
                'steps' => [
                    ['label' => 'طلب انضمام', 'done' => $has(CreatorApplication::class) > 0, 'count' => $has(CreatorApplication::class), 'link' => '/app/creator-applications'],
                    ['label' => 'توثيق البريد/الجوال', 'done' => CreatorApplication::withoutGlobalScopes()->whereNotNull('email_verified_at')->count() > 0, 'count' => CreatorApplication::withoutGlobalScopes()->whereNotNull('email_verified_at')->count(), 'link' => '/app/creator-applications'],
                    ['label' => 'مراجعة الطلب', 'done' => CreatorApplication::withoutGlobalScopes()->whereIn('status', ['under_review', 'approved', 'rejected'])->count() > 0, 'count' => CreatorApplication::withoutGlobalScopes()->whereIn('status', ['under_review', 'approved', 'rejected'])->count(), 'link' => '/app/creator-applications'],
                    ['label' => 'قبول وإنشاء الحساب', 'done' => $approvedApplications > 0, 'count' => $approvedApplications, 'link' => '/app/creators'],
                    ['label' => 'إكمال الملف والمنصّات', 'done' => DB::table('creator_platforms')->count() > 0, 'count' => (int) DB::table('creator_platforms')->count(), 'link' => '/creator/account'],
                    ['label' => 'الخدمات والأسعار', 'done' => DB::table('creator_services')->count() > 0, 'count' => (int) DB::table('creator_services')->count(), 'link' => '/creator/account#services'],
                    ['label' => 'البيانات المالية (آيبان مُشفَّر)', 'done' => Creator::withoutGlobalScopes()->whereNotNull('iban_last4')->count() > 0, 'count' => Creator::withoutGlobalScopes()->whereNotNull('iban_last4')->count(), 'link' => '/creator/account#financial'],
                    ['label' => 'عرض تعاون', 'done' => Collaboration::withoutGlobalScopes()->where('status', 'offered')->count() > 0, 'count' => Collaboration::withoutGlobalScopes()->where('status', 'offered')->count(), 'link' => '/app/collaborations'],
                    ['label' => 'قبول وتوقيع العقد', 'done' => $signedContracts > 0, 'count' => $signedContracts, 'link' => '/creator/contracts'],
                    ['label' => 'رفع المحتوى وتعديله', 'done' => $has(ContentItem::class) > 0, 'count' => $has(ContentItem::class), 'link' => '/creator/content'],
                    ['label' => 'اعتماد الاستحقاق والصرف', 'done' => $paidPayouts > 0, 'count' => $paidPayouts, 'link' => '/creator/payouts'],
                ],
            ],
            [
                'key' => 'publisher',
                'title' => 'من اكتشاف الناشر إلى التعاون',
                'subtitle' => 'رحلة الذكاء: اكتشاف ثم تحليل ثم تحويل إلى مؤثر ثم ترشيح',
                'start' => '/app/publishers',
                'steps' => [
                    ['label' => 'ناشرون في القاعدة', 'done' => $has(Publisher::class) > 0, 'count' => $has(Publisher::class), 'link' => '/app/publishers'],
                    ['label' => 'مصدر البيانات وحالته ظاهران', 'done' => true, 'count' => null, 'link' => '/app/publishers', 'note' => 'كل ناشر يحمل source و last_synced_at'],
                    ['label' => 'الاكتشاف الحيّ عبر APIs', 'done' => false, 'count' => 0, 'link' => '/app/integrations', 'blocker' => 'ينتظر اعتمادات المنصّات'],
                    ['label' => 'تحويل ناشر إلى مؤثر (بلا تكرار)', 'done' => $convertedPublishers > 0, 'count' => $convertedPublishers, 'link' => '/app/creators'],
                    ['label' => 'إضافته إلى ترشيح', 'done' => DB::table('campaign_shortlist_items')->count() > 0, 'count' => (int) DB::table('campaign_shortlist_items')->count(), 'link' => '/app/shortlisting'],
                ],
            ],
            [
                'key' => 'saas',
                'title' => 'من تسجيل المؤسسة إلى التجديد',
                'subtitle' => 'رحلة SaaS: مستأجر وخطة وحدود واستخدام',
                'start' => '/beta/admin/tenants',
                'steps' => [
                    ['label' => 'مستأجرون', 'done' => Tenant::count() > 0, 'count' => Tenant::count(), 'link' => '/beta/admin/tenants'],
                    ['label' => 'خطط وإصدارات', 'done' => DB::table('plans')->count() > 0, 'count' => (int) DB::table('plans')->count(), 'link' => '/beta/admin/plans'],
                    ['label' => 'اشتراكات نشطة/تجريبية', 'done' => DB::table('subscriptions')->count() > 0, 'count' => (int) DB::table('subscriptions')->count(), 'link' => '/beta/admin/subscriptions'],
                    ['label' => 'حقوق وحدود مُطبَّقة', 'done' => DB::table('plan_entitlements')->count() > 0, 'count' => (int) DB::table('plan_entitlements')->count(), 'link' => '/app/settings'],
                    ['label' => 'قياس الاستخدام', 'done' => DB::table('usage_records')->count() > 0, 'count' => (int) DB::table('usage_records')->count(), 'link' => '/app/settings'],
                    ['label' => 'دعوة الفريق والأدوار', 'done' => DB::table('organization_memberships')->count() > 0, 'count' => (int) DB::table('organization_memberships')->count(), 'link' => '/app/team'],
                    ['label' => 'فاتورة اشتراك', 'done' => false, 'count' => 0, 'link' => null, 'blocker' => 'لا وحدة فواتير اشتراك'],
                    ['label' => 'تجديد/ترقية/تخفيض', 'done' => false, 'count' => 0, 'link' => null, 'blocker' => 'يتطلّب مزوّد دفع + قرار تجاري'],
                ],
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function modules(): array
    {
        return [
            ['group' => 'الوكالة', 'items' => [
                ['label' => 'لوحة التحكم', 'href' => '/app'],
                ['label' => 'الطلبات', 'href' => '/app/service-requests'],
                ['label' => 'العملاء', 'href' => '/app/clients'],
                ['label' => 'العلامات', 'href' => '/app/brands'],
                ['label' => 'المؤثرون', 'href' => '/app/creators'],
                ['label' => 'الناشرون', 'href' => '/app/publishers'],
                ['label' => 'طلبات الانضمام', 'href' => '/app/creator-applications'],
                ['label' => 'الحملات', 'href' => '/app/campaigns'],
                ['label' => 'الترشيحات', 'href' => '/app/shortlisting'],
                ['label' => 'التعاونات', 'href' => '/app/collaborations'],
                ['label' => 'المحتوى', 'href' => '/app/content'],
                ['label' => 'العقود', 'href' => '/app/contracts'],
                ['label' => 'المستحقات', 'href' => '/app/payouts'],
                ['label' => 'التقارير', 'href' => '/app/reports'],
                ['label' => 'التكاملات', 'href' => '/app/integrations'],
                ['label' => 'الفريق', 'href' => '/app/team'],
                ['label' => 'الإعدادات', 'href' => '/app/settings'],
                ['label' => 'حسابي', 'href' => '/app/account'],
            ]],
            ['group' => 'بوابة العميل', 'items' => [
                ['label' => 'اللوحة', 'href' => '/client'],
                ['label' => 'حساب المنشأة', 'href' => '/client/account'],
                ['label' => 'الحملات', 'href' => '/client/campaigns'],
                ['label' => 'المحتوى', 'href' => '/client/content'],
                ['label' => 'العقود', 'href' => '/client/contracts'],
                ['label' => 'الطلبات', 'href' => '/client/requests'],
                ['label' => 'العلامات', 'href' => '/client/brands'],
                ['label' => 'الفريق', 'href' => '/client/team'],
                ['label' => 'المستندات', 'href' => '/client/documents'],
                ['label' => 'الإشعارات', 'href' => '/client/notifications'],
            ]],
            ['group' => 'بوابة المبدع', 'items' => [
                ['label' => 'اللوحة', 'href' => '/creator'],
                ['label' => 'حسابي', 'href' => '/creator/account'],
                ['label' => 'التعاونات', 'href' => '/creator/collaborations'],
                ['label' => 'المحتوى', 'href' => '/creator/content'],
                ['label' => 'العقود', 'href' => '/creator/contracts'],
                ['label' => 'المستحقات', 'href' => '/creator/payouts'],
                ['label' => 'الإشعارات', 'href' => '/creator/notifications'],
            ]],
            ['group' => 'الشريك وSaaS', 'items' => [
                ['label' => 'بوابة الشريك', 'href' => '/partner'],
                ['label' => 'طلبات الشريك', 'href' => '/partner/requests'],
                ['label' => 'الوكالات الشريكة', 'href' => '/app/partner-agencies'],
                ['label' => 'لوحة النظام', 'href' => '/beta/admin'],
                ['label' => 'المستأجرون', 'href' => '/beta/admin/tenants'],
                ['label' => 'الخطط', 'href' => '/beta/admin/plans'],
                ['label' => 'الاشتراكات', 'href' => '/beta/admin/subscriptions'],
                ['label' => 'سجل التدقيق', 'href' => '/beta/admin/audit'],
            ]],
        ];
    }

    /**
     * حسابات التجربة بحسب الدور — البريد ومسار الدخول فقط.
     * لا كلمات مرور ولا رموز: تُقرأ من ملف الاعتمادات المحلي غير المتتبَّع.
     *
     * @return array<int,array<string,mixed>>
     */
    private function accounts(): array
    {
        $emails = [
            ['role' => 'مدير النظام', 'email' => 'system_admin@demo.test', 'login' => '/login', 'lands' => '/beta/admin'],
            ['role' => 'مدير المؤسسة', 'email' => 'agency_admin@demo.test', 'login' => '/login', 'lands' => '/app'],
            ['role' => 'مدير الحملات', 'email' => 'campaign_manager@demo.test', 'login' => '/login', 'lands' => '/app'],
            ['role' => 'مدير المؤثرين', 'email' => 'creator_manager@demo.test', 'login' => '/login', 'lands' => '/app'],
            ['role' => 'المالية', 'email' => 'finance@demo.test', 'login' => '/login', 'lands' => '/app/payouts'],
            ['role' => 'مدير حساب العميل', 'email' => 'client_admin@demo.test', 'login' => '/client/login', 'lands' => '/client'],
            ['role' => 'عضو فريق العميل', 'email' => 'client_member@demo.test', 'login' => '/client/login', 'lands' => '/client'],
            ['role' => 'مؤثر', 'email' => 'influencer@demo.test', 'login' => '/creator/login', 'lands' => '/creator'],
            ['role' => 'صانع محتوى UGC', 'email' => 'ugc_creator@demo.test', 'login' => '/creator/login', 'lands' => '/creator'],
            ['role' => 'شريك (وكالة خارجية)', 'email' => 'partner@najma.test', 'login' => '/partner/login', 'lands' => '/partner'],
        ];

        $existing = User::withoutGlobalScopes()->whereIn('email', array_column($emails, 'email'))->pluck('email')->all();

        return array_map(fn (array $a) => $a + ['exists' => in_array($a['email'], $existing, true)], $emails);
    }

    /** @return array<int,array<string,mixed>> */
    private function integrations(): array
    {
        $available = config('platforms.available_statuses', []);
        $out = [];
        foreach (PlatformRegistry::all() as $key => $p) {
            $status = $p['status'] ?? 'draft';
            $out[] = [
                'key' => $key,
                'name' => $p['label_ar'] ?? $key,
                'status' => $status,
                'label' => self::STATUS_LABEL[$status] ?? $status,
                'available' => in_array($status, $available, true),
            ];
        }

        // تكاملات خارج سجلّ المنصّات — تُعرض بحالتها الصادقة لا كفراغ
        foreach ([
            ['Google Drive', 'unavailable', 'غير مبنيّ — لا كود ولا إعداد'],
            ['مزوّد الدفع', 'unavailable', 'FakeBillingProvider فقط — لا مدفوعات حقيقية'],
            ['SMS', 'waiting_for_credentials', 'NullSmsSender — الرموز لا تصل جوالًا'],
            ['البريد (SMTP)', 'available_manual', 'يعمل فعليًا في الإنتاج'],
            ['مزوّد ذكاء اصطناعي', 'unavailable', 'لا مزوّد — المطابقة خوارزمية شفّافة'],
        ] as [$name, $st, $note]) {
            $out[] = ['key' => $name, 'name' => $name, 'status' => $st,
                'label' => self::STATUS_LABEL[$st] ?? $st, 'available' => $st === 'available_manual', 'note' => $note];
        }

        return $out;
    }

    private const STATUS_LABEL = [
        'connected' => 'متصل', 'sandbox' => 'بيئة اختبار', 'available_manual' => 'إدخال يدوي',
        'available_import' => 'استيراد', 'available_api' => 'عبر API',
        'waiting_for_credentials' => 'بانتظار اعتمادات', 'waiting_for_platform_approval' => 'بانتظار اعتماد المنصّة',
        'degraded' => 'متعثّر', 'disconnected' => 'غير متصل', 'unavailable' => 'غير متاح',
        'draft' => 'مسودة (مخفيّة)', 'suspended' => 'موقوف', 'deprecated' => 'مهجور',
    ];

    /** @return array<int,array<string,string>> */
    private function blockers(): array
    {
        return [
            ['title' => 'الفوترة التجارية والمدفوعات', 'impact' => 'لا فواتير ولا مدفوعات ولا ledger ولا webhooks', 'needs' => 'قرار تجاري: مزوّد (ميسر/Stripe) + حساب تاجر + سياسة ضريبة وفوترة إلكترونية'],
            ['title' => 'Google Drive', 'impact' => 'غير مبنيّ إطلاقًا؛ التخزين محلي خاص', 'needs' => 'قرار: هل يُراد أصلًا؟ ثم مشروع Google Cloud + OAuth + نطاق صلاحيات'],
            ['title' => 'الاكتشاف الحيّ للناشرين', 'impact' => 'كل المنصّات إدخال يدوي', 'needs' => 'اعتمادات APIs لكل منصّة + موافقاتها'],
            ['title' => 'رسائل SMS', 'impact' => 'رموز OTP لا تصل عبر الجوال', 'needs' => 'مزوّد SMS + اعتماداته'],
            ['title' => 'التحقّق بخطوتين (2FA)', 'impact' => 'الحالة تُعرض بلا تدفّق تفعيل', 'needs' => 'قرار منتَج: TOTP أم SMS، وهل يكون إلزاميًا'],
        ];
    }

    /** @return array<string,mixed> */
    private function services(): array
    {
        $dbOk = true;
        $dbError = null;
        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            $dbOk = false;
            $dbError = $e->getMessage();
        }

        return [
            'database' => ['ok' => $dbOk, 'driver' => config('database.default'), 'error' => $dbError],
            'queue' => ['driver' => config('queue.default'), 'pending' => $this->safeCount('jobs'), 'failed' => $this->safeCount('failed_jobs')],
            'cache' => ['driver' => config('cache.default')],
            'session' => ['driver' => config('session.driver'), 'active' => $this->safeCount('sessions')],
            'mail' => ['driver' => config('mail.default')],
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
        ];
    }

    private function safeCount(string $table): ?int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private function dataset(): array
    {
        $t = Tenant::withoutGlobalScopes()->where('slug', 'showcase')->first();
        if (! $t) {
            return ['exists' => false];
        }

        $c = fn (string $m) => (int) $m::withoutGlobalScopes()->where('tenant_id', $t->id)->count();

        return [
            'exists' => true,
            'tenant' => $t->name,
            'counts' => [
                'العملاء' => $c(Client::class), 'العلامات' => $c(Brand::class), 'المؤثرون' => $c(Creator::class),
                'الحملات' => $c(Campaign::class), 'التعاونات' => $c(Collaboration::class), 'المحتوى' => $c(ContentItem::class),
                'العقود' => $c(Contract::class), 'المستحقات' => $c(Payout::class), 'الطلبات' => $c(ServiceRequest::class),
                'الشركاء' => $c(ExternalAgency::class),
            ],
        ];
    }

    /** @return array<int,array<string,string>> */
    private function changelog(): array
    {
        $out = [];
        try {
            $log = shell_exec('cd ' . escapeshellarg(base_path()) . ' && git log -12 --pretty=format:"%h%x09%ad%x09%s" --date=short 2>/dev/null');
            foreach (explode("\n", trim((string) $log)) as $line) {
                if (! $line) {
                    continue;
                }
                [$hash, $date, $subject] = array_pad(explode("\t", $line, 3), 3, '');
                $out[] = ['hash' => $hash, 'date' => $date, 'subject' => $subject];
            }
        } catch (\Throwable) {
            // سجل التغييرات ترف لا ضرورة — لا يُعطّل الصفحة
        }

        return $out;
    }

    /** إعادة بناء بيانات العرض — أمر موجود مسبقًا، محجوب في الإنتاج. */
    public function reseed(Request $r)
    {
        $this->guard();
        Artisan::call('preview:reset-showcase');
        Artisan::call('preview:seed-showcase');

        return back()->with('ok', 'أُعيد بناء بيانات العرض.');
    }
}
