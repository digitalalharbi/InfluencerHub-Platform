<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
class PreviewCenterController extends Controller {
    /** مركز معاينة التطوير — يعرض حالة كل وحدة. محجوب في الإنتاج. */
    public function index() {
        abort_if(app()->environment('production'), 404);
        // تجاوز مؤقّت لقراءة حالة بيئة العرض، ثم يعود سياق الطلب كما كان
        // (حتى تعمل شارة «بيانات تجريبية» العامة التي تعتمد على TenantContext).
        $showcase = \App\Domain\Tenancy\Support\TenantContext::withBypass(function () {
            $st = \App\Domain\Tenancy\Models\Tenant::withoutGlobalScopes()->where('slug', 'showcase')->first();
            if (! $st) {
                return ['exists' => false];
            }
            $c = fn ($m) => $m::withoutGlobalScopes()->where('tenant_id', $st->id)->count();

            return [
                'exists' => true,
                'clients' => $c(\App\Domain\CRM\Models\Client::class),
                'creators' => $c(\App\Domain\Creators\Models\Creator::class),
                'campaigns' => $c(\App\Domain\Campaigns\Models\Campaign::class),
                'content' => $c(\App\Domain\Content\Models\ContentItem::class),
            ];
        });
        return view('dev.preview-center', ['modules' => self::MODULES, 'showcase' => $showcase]);
    }

    /** معرض نظام التصميم (ih-) — مرجع بصري للـ tokens والمكوّنات. محجوب في الإنتاج. */
    public function designSystem() {
        abort_if(app()->environment('production'), 404);
        return view('dev.design-system');
    }

    /** توليد/إعادة توليد بيئة العرض التجريبية (Showcase). محجوب في الإنتاج. */
    public function seedShowcase() {
        abort_if(app()->environment('production'), 404);
        $summary = (new \App\Support\Showcase\ShowcaseBuilder())->build();
        $msg = 'تم توليد بيئة العرض: ' . collect($summary)->except('tenant')
            ->map(fn ($v, $k) => "$k=$v")->implode(' · ');
        return redirect('/app/preview')->with('ok', $msg);
    }

    /** حذف بيئة العرض التجريبية بالكامل. محجوب في الإنتاج. */
    public function resetShowcase() {
        abort_if(app()->environment('production'), 404);
        (new \App\Support\Showcase\ShowcaseBuilder())->reset();
        return redirect('/app/preview')->with('ok', 'تم حذف بيئة العرض التجريبية.');
    }
    // الحالة: browser_verified | ui_ready | backend_ready | in_progress | blocked
    private const MODULES = [
        ['المرحلة 1-2: تسجيل الدخول', '/login', 'الجميع', 'browser_verified'],
        ['لوحة الوكالة', '/app', 'agency team', 'browser_verified'],
        ['العملاء', '/app/clients', 'agency team', 'browser_verified'],
        ['تفاصيل العميل (تبويبات)', '/app/clients/1', 'agency team', 'browser_verified'],
        ['العلامات التجارية', '/app/brands', 'agency team', 'ui_ready'],
        ['مراجعة العلامات (سير عمل)', '/app/brand-reviews', 'agency team', 'browser_verified'],
        ['مراجعات العملاء (قانوني + مستندات)', '/app/client-reviews', 'admin/ops/finance', 'browser_verified'],
        ['الوكالات الخارجية (قبول + دعوة + روابط)', '/app/partner-agencies', 'admin/ops', 'browser_verified'],
        ['بوابة الشريك — قبول الدعوة (عام)', '/partner/invite/{token}', 'مدعو', 'browser_verified'],
        ['بوابة الشريك — دخول', '/partner/login', 'partner_*', 'browser_verified'],
        ['بوابة الشريك — الرئيسية (روابط مُنطّقة)', '/partner/dashboard', 'partner_*', 'browser_verified'],
        // Phase 6 — طلبات الخدمة
        ['طلبات الخدمة (الوكالة — فرز/إسناد/SLA)', '/app/service-requests', 'agency team', 'browser_verified'],
        ['طلبات الخدمة (العميل)', '/client/requests', 'client_*', 'browser_verified'],
        ['طلبات الخدمة (الشريك — مُنطّقة)', '/partner/requests', 'partner_*', 'browser_verified'],
        // Phase 7 — منشئ الحملات
        ['الحملات (الوكالة — مخرجات/ميزانية/حالة)', '/app/campaigns', 'agency team', 'browser_verified'],
        ['تحويل طلب حملة → حملة', '/app/service-requests/{id}', 'agency team', 'browser_verified'],
        ['الحملات (العميل — عرض فقط)', '/client/campaigns', 'client_*', 'browser_verified'],
        // Phase 8 — التعاونات + المطابقة
        ['التعاونات (الوكالة — عرض/دورة حياة)', '/app/collaborations', 'agency team', 'browser_verified'],
        ['اقتراح مبدعين لمخرَج (مطابقة)', '/app/campaigns/{id}/deliverables/{d}/suggest', 'agency team', 'browser_verified'],
        ['التعاونات (المبدع — قبول/تسليم)', '/creator/collaborations', 'creator', 'browser_verified'],
        // Phase 9 — المحتوى والموافقات
        ['المحتوى (الوكالة — مراجعة/نشر)', '/app/content', 'agency team', 'browser_verified'],
        ['المحتوى (المبدع — تقديم/إصدارات)', '/creator/content', 'creator', 'browser_verified'],
        ['موافقات المحتوى (العميل)', '/client/content', 'client_*', 'browser_verified'],
        // Phase 10 — العقود
        ['العقود (الوكالة — إصدار/إرسال/تفعيل)', '/app/contracts', 'agency team', 'browser_verified'],
        ['العقود (المبدع — قبول داخل المنصّة)', '/creator/contracts', 'creator', 'browser_verified'],
        ['العقود (العميل — قبول)', '/client/contracts', 'client_admin', 'browser_verified'],
        // Phase 11 — المستحقات المالية
        ['المستحقات (الوكالة — حالات صادقة، بلا تنفيذ دفع)', '/app/payouts', 'admin/finance', 'browser_verified'],
        ['المستحقات (المبدع — عرض)', '/creator/payouts', 'creator', 'browser_verified'],
        // Phase 12 — التقارير
        ['التقارير (تجميعات حقيقية عبر الوحدات)', '/app/reports', 'agency team', 'browser_verified'],
        // Phase 13 — محرّك الأتمتة/SLA
        ['محرّك SLA (تذكيرات + رصد تجاوزات — مجدول)', 'sla:scan (hourly)', 'نظام', 'browser_verified'],
        ['أعضاء فريق العميل', '/app/clients/1 (تبويب الأعضاء)', 'admin/ops', 'ui_ready'],
        ['المستندات', '/app/clients/1 (تبويب المستندات)', 'admin/ops/finance', 'ui_ready'],
        ['الحقول المخصّصة', '/app/clients/1 (تبويب الحقول)', 'agency team', 'ui_ready'],
        // Phase 5 — بوابة العميل (قيد البناء)
        ['بوابة العميل — دخول', '/client/login', 'client_member', 'browser_verified'],
        ['بوابة العميل — الرئيسية + مبدّل', '/client/dashboard', 'client_*', 'browser_verified'],
        ['بوابة العميل — ملف العميل (تعديل+مراجعة)', '/client/profile', 'client_admin', 'browser_verified'],
        ['بوابة العميل — الملف المالي', '/client/billing-profile', 'client_finance/admin', 'browser_verified'],
        ['بوابة العميل — العناوين (CRUD+افتراضي)', '/client/addresses', 'client_admin', 'browser_verified'],
        ['بوابة العميل — المستندات (خاصة+versioning)', '/client/documents', 'client_admin/finance', 'browser_verified'],
        ['بوابة العميل — العلامات (مسودة/إرسال/إصدارات)', '/client/brands', 'client_admin/campaign', 'browser_verified'],
        ['بوابة العميل — الفريق (دعوة/دور/حالة)', '/client/team', 'client_admin', 'browser_verified'],
        ['بوابة العميل — الإشعارات (مركز + شارة)', '/client/notifications', 'client_*', 'browser_verified'],
        ['بوابة العميل — الإعدادات (إشعارات/كلمة مرور/جلسات)', '/client/settings', 'client_*', 'browser_verified'],
        // Phase 4 — المبدعون (مبنية)
        ['المؤثرون', '/app/creators?type=influencer', 'creator_manager', 'ui_ready'],
        ['صنّاع UGC', '/app/creators?type=ugc_creator', 'creator_manager', 'ui_ready'],
        ['كل المبدعين', '/app/creators', 'creator_manager', 'ui_ready'],
        ['بوابة طلبات الانضمام (عامة)', '/join/creator', 'عام', 'browser_verified'],
        ['متابعة الطلب (عامة)', '/join/creator/{ref}/status', 'عام', 'browser_verified'],
        ['طلبات الانضمام (إدارة)', '/app/creator-applications', 'creator_manager', 'browser_verified'],
        ['مراجعة طلب انضمام', '/app/creator-applications/{id}', 'creator_manager', 'browser_verified'],
        ['بوابة المبدع — دخول', '/creator/login', 'creator', 'browser_verified'],
        ['بوابة المبدع — الرئيسية', '/creator/dashboard', 'creator', 'browser_verified'],
        ['بوابة المبدع — ملفي (+صورة)', '/creator/profile', 'creator', 'browser_verified'],
        ['بوابة المبدع — المنصات (CRUD)', '/creator/platforms', 'creator', 'browser_verified'],
        ['بوابة المبدع — الخدمات (CRUD)', '/creator/services', 'creator', 'browser_verified'],
        ['بوابة المبدع — نماذج الأعمال (CRUD)', '/creator/portfolio', 'creator', 'browser_verified'],
        ['بوابة المبدع — موثوق', '/creator/mowthooq', 'creator', 'browser_verified'],
        ['بوابة المبدع — المالية (IBAN مشفّر)', '/creator/financial', 'creator', 'browser_verified'],
        ['بوابة العملاء', null, 'client_*', 'blocked'],
        ['طلبات الحملات', null, 'campaign_manager', 'blocked'],
        ['منشئ الحملات', null, 'campaign_manager', 'blocked'],
        ['سوق الحملات', null, 'campaign_manager', 'blocked'],
        ['التعاونات', null, 'ops', 'blocked'],
        ['المهام', null, 'الجميع', 'blocked'],
        ['المحتوى والموافقات', null, 'content_reviewer', 'blocked'],
        ['العقود', null, 'finance/admin', 'blocked'],
        ['الشحن والهدايا', null, 'ops', 'blocked'],
        ['المدفوعات والمستحقات', null, 'finance', 'blocked'],
        ['التقارير والتحليلات', null, 'admin', 'blocked'],
        ['الاشتراكات والخطط', '/api/v1/billing/subscription', 'admin', 'backend_ready'],
        ['الإعدادات', null, 'admin', 'blocked'],
        ['التكاملات', null, 'admin', 'blocked'],
        ['الأتمتة', null, 'admin', 'blocked'],
        ['لوحة إدارة النظام', null, 'system_admin', 'blocked'],
    ];
}
