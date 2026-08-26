<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\SiteController;
use App\Http\Controllers\Web\PreviewCenterController;
// ===== الموقع العام — أوّل ما يراه الزائر (لا يهبط في لوحة داخلية) =====
use Illuminate\Support\Facades\Route;

Route::middleware('inertia')->controller(SiteController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::view('/session-expired', 'errors.419')->name('session-expired');
    // تحويل دائم إلى `/start` — المسار الرسمي الوحيد لاختيار نوع الحساب
    Route::get('/register', 'legacyRegister')->name('register');
    Route::get('/register/account-type', 'legacyRegister');
});

// الصفحات التعريفية والنظامية — بلا تحويل للمصادَق: الشروط والخصوصية والأسعار
// يحتاجها من يعمل داخل النظام، وتحويله عنها يكسر روابط التذييل لكل مستخدم مسجّل.
use App\Http\Controllers\Public\MarketingController;

Route::middleware('inertia')->controller(MarketingController::class)->group(function () {
    Route::get('/features', 'features')->name('features');
    Route::get('/solutions/{role}', 'solution')->whereIn('role', ['clients', 'agencies', 'creators']);
    Route::get('/pricing', 'pricing')->name('pricing');
    Route::get('/help', 'help')->name('help');
    Route::get('/info', 'info')->name('info');
    Route::get('/terms', 'terms')->name('terms');
    Route::get('/privacy', 'privacy')->name('privacy');
});

// طلب عرض توضيحي — يُحفظ فعليًّا ويُعاد بمرجع (نفس حدّ الإغراق المستخدم للطلبات العامة)
use App\Http\Controllers\Public\DemoRequestController;

Route::middleware('inertia')->controller(DemoRequestController::class)->group(function () {
    Route::get('/demo', 'form')->name('demo');
    Route::post('/demo', 'store')->middleware('throttle:join-start');
    Route::get('/demo/submitted/{reference}', 'submitted');
});

// المسار الذاتي لمساحة الوكالة: تحقّق بريد ← إعداد ← مستأجر بتجربة مجانية
use App\Http\Controllers\Public\SelfSignupController;

Route::middleware('inertia')->controller(SelfSignupController::class)->group(function () {
    Route::get('/register/agency', 'startForm');
    Route::post('/register/agency/start', 'start')->middleware('throttle:join-start');
    Route::get('/register/agency/verify/{reference}', 'verifyForm');
    Route::get('/register/agency/verify/{reference}/{code}', 'verifyLink')->name('register.agency.verify-link')->middleware('signed');
    Route::post('/register/agency/verify/{reference}', 'verify')->middleware('throttle:join-otp');
    Route::post('/register/agency/resend/{reference}', 'resend')->middleware('throttle:join-otp');
    Route::get('/register/agency/setup/{reference}', 'setupForm');
    Route::post('/register/agency/complete/{reference}', 'complete')->middleware('throttle:join-op');
});

// تسجيل العميل والوكالة — طلب يُراجَع (التفعيل الفوري موقوف على المزوّد المالي)
use App\Http\Controllers\Public\SignupRequestController;

Route::middleware('inertia')->controller(SignupRequestController::class)->group(function () {
    // العميل سجلّ داخل مستأجر وكالة، فتسجيله مسار مطابقة لا إنشاء مستأجر
    Route::get('/register/client', 'form')->defaults('type', 'client');
    Route::post('/register/client', 'store')->defaults('type', 'client')->middleware('throttle:join-start');
    // المسار اليدوي للوكالات: الخطط المخصّصة والحالات المؤسسية فقط
    Route::get('/register/agency/enterprise', 'form')->defaults('type', 'agency');
    Route::post('/register/agency/enterprise', 'store')->defaults('type', 'agency')->middleware('throttle:join-start');
    Route::get('/register/{type}/submitted/{reference}', 'submitted')->whereIn('type', ['client', 'agency']);
});

// ===== بوابة الانضمام العامة (بلا تسجيل دخول) — Phase 4 =====
use App\Http\Controllers\Public\JoinController;

Route::controller(JoinController::class)->group(function () {
    Route::get('/join', 'index');
    // استعادة الوصول (بريد) — قبل المسارات ذات المتغيّرات
    Route::get('/join/recover', 'recoverForm');
    Route::post('/join/recover', 'recover')->middleware('throttle:join-recover'); // حدّ محاولات الاستعادة
    // حلّ المؤسسة صريح عبر ?a={slug} (SaaS) — لا "أول مستأجر". دعم subdomain/custom-domain لاحقًا.
    Route::get('/join/creator', 'creatorForm');
    Route::post('/join/creator', 'storeCreator')->middleware('throttle:join-start');       // منع الإغراق (30/دقيقة/IP)
    Route::get('/join/creator/{reference}/status', 'status');
    Route::post('/join/creator/{reference}/continue', 'continue')->middleware('throttle:30,1');
    Route::post('/join/creator/{reference}/verify-email', 'verifyEmail')->middleware('throttle:join-otp'); // حد OTP
    Route::post('/join/creator/{reference}/verify-phone', 'verifyPhone')->middleware('throttle:join-otp');
    Route::post('/join/creator/{reference}/platforms', 'addPlatform')->middleware('throttle:join-op');
    Route::post('/join/creator/{reference}/services', 'addService')->middleware('throttle:join-op');
    Route::post('/join/creator/{reference}/portfolio', 'addPortfolio')->middleware('throttle:join-op');
    Route::post('/join/creator/{reference}/mowthooq', 'saveMowthooq')->middleware('throttle:join-op');
    Route::post('/join/creator/{reference}/financial', 'saveFinancial')->middleware('throttle:join-op');
    Route::post('/join/creator/{reference}/upload', 'uploadDocument')->middleware('throttle:join-op');
    Route::post('/join/creator/{reference}/submit', 'submit')->middleware('throttle:join-op');
});

// المصادقة (جلسة الويب)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
});
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect')->middleware('guest');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback')->middleware('guest');
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth');

// ===== بوابة المبدع (Portal مستقل) — Phase 4 =====
use App\Http\Controllers\Creator\CreatorAuthController;
use App\Http\Controllers\Creator\CreatorPortalController;

Route::middleware('guest')->group(function () {
    Route::get('/creator/login', [CreatorAuthController::class, 'show'])->name('creator.login');
    Route::post('/creator/login', [CreatorAuthController::class, 'login'])->middleware('throttle:login');

    // قبول دعوة صانع المحتوى — الرمز في الرابط هو الإذن، فلا مصادقة قبله
    Route::get('/creator/invitation/{token}', [InvitationAcceptController::class, 'show'])
        ->middleware('inertia')->name('creator.invitation');
    Route::post('/creator/invitation/{token}/verify-email', [InvitationAcceptController::class, 'verifyEmail'])->middleware('inertia');
    Route::post('/creator/invitation/{token}/verify-phone', [InvitationAcceptController::class, 'verifyPhone'])->middleware('inertia');
    Route::post('/creator/invitation/{token}/accept', [InvitationAcceptController::class, 'accept'])->middleware('inertia');
});
Route::post('/creator/logout', [CreatorAuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', 'platform_preview:creator', 'creator'])->prefix('creator')->group(function () {
    // سطح المنتَج — React/Inertia (قُصّ من Blade)
    Route::middleware('inertia')->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/dashboard', [DashboardController::class, 'index']); // الرابط التاريخي بعد الدخول

        // الحساب: ملف/منصّات/خدمات/أعمال/موثوق/مالية في مساحة واحدة بتبويبات
        Route::get('/account', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'index']);
        Route::post('/account/profile', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateProfile']);
        // القدرات يحرّرها الصانع نفسه — ما يجيده يتغيّر بعد التقديم
        Route::post('/account/capabilities', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateCapabilities']);
        Route::post('/account/avatar', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'uploadAvatar']);
        Route::post('/account/platforms', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePlatform']);
        Route::post('/account/platforms/{platform}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePlatform']);
        Route::post('/account/services', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storeService']);
        Route::post('/account/services/{service}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deleteService']);
        Route::post('/account/portfolio', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePortfolio']);
        Route::post('/account/portfolio/{item}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePortfolio']);
        Route::post('/account/mowthooq', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateMowthooq']);
        Route::post('/account/financial', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateFinancial']);
        Route::post('/account/settings/notifications', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateNotificationPrefs']);
        Route::post('/account/settings/password', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'changePassword']);
        Route::post('/account/settings/sessions/revoke-others', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'revokeOtherSessions']);
        Route::post('/account/settings/notifications', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateNotificationPrefs']);
        Route::post('/account/settings/password', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'changePassword']);
        Route::post('/account/settings/sessions/revoke-others', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'revokeOtherSessions']);

        Route::get('/collaborations', [CollaborationController::class, 'index']);
        Route::get('/collaborations/{collaboration}', [CollaborationController::class, 'show']);
        Route::post('/collaborations/{collaboration}/{action}', [CollaborationController::class, 'action'])
            ->where('action', 'accept|decline|start|submit');
        Route::get('/content', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'index']);
        Route::post('/content', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'store']);
        Route::get('/content/{content}', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'show']);
        Route::post('/content/{content}/update', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'update']);
        Route::post('/content/{content}/submit', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'submit']);
        Route::get('/contracts', [ContractController::class, 'index']);
        Route::get('/contracts/{contract}', [ContractController::class, 'show']);
        Route::post('/contracts/{contract}/sign', [ContractController::class, 'sign']);
        Route::get('/payouts', [PayoutController::class, 'index']);
        Route::get('/notifications', [App\Http\Controllers\Inertia\Creator\NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [App\Http\Controllers\Inertia\Creator\NotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [App\Http\Controllers\Inertia\Creator\NotificationController::class, 'read']);
    });

    // مسارات الحساب التاريخية: صفحاتها صارت تبويبات، وإجراءاتها تستدعي المتحكّم نفسه
    Route::redirect('/profile', '/creator/account#profile');
    Route::redirect('/platforms', '/creator/account#platforms');
    Route::redirect('/services', '/creator/account#services');
    Route::redirect('/portfolio', '/creator/account#portfolio');
    Route::redirect('/mowthooq', '/creator/account#verification');
    Route::redirect('/financial', '/creator/account#financial');
    Route::post('/profile', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateProfile']);
    Route::post('/capabilities', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateCapabilities']);
    Route::post('/platforms', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePlatform']);
    Route::post('/platforms/{platform}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePlatform']);
    Route::post('/services', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storeService']);
    Route::post('/services/{service}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deleteService']);
    Route::post('/portfolio', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePortfolio']);
    Route::post('/portfolio/{item}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePortfolio']);
    Route::post('/mowthooq', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateMowthooq']);
    Route::post('/financial', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateFinancial']);
    Route::post('/avatar', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'uploadAvatar']);

    // وحدات لاحقة (بنية فقط، بلا بيانات وهمية)
    Route::get('/{section}', [CreatorPortalController::class, 'stub'])
        ->whereIn('section', ['opportunities', 'settings']);
});

// ===== بوابة العميل (Portal مستقل) — Phase 5 =====
use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\ClientPortalController;

Route::middleware('guest')->group(function () {
    Route::get('/client/login', [ClientAuthController::class, 'show'])->name('client.login');
    Route::post('/client/login', [ClientAuthController::class, 'login'])->middleware('throttle:login');
});
Route::post('/client/logout', [ClientAuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', 'platform_preview:client', 'client_member'])->prefix('client')->group(function () {
    // تبديل العميل النشِط يبقى Blade (جزء من تدفّق المصادقة)
    Route::post('/switch', [ClientAuthController::class, 'switch']);

    // سطح المنتَج — React/Inertia (قُصّ من Blade)
    Route::middleware('inertia')->group(function () {
        Route::get('/', [App\Http\Controllers\Inertia\Client\DashboardController::class, 'index']);
        Route::get('/dashboard', [App\Http\Controllers\Inertia\Client\DashboardController::class, 'index']); // الرابط التاريخي بعد الدخول

        // حساب المنشأة: الملف/الفوترة/العناوين/الإعدادات في مساحة واحدة
        Route::get('/account', [App\Http\Controllers\Inertia\Client\AccountController::class, 'index']);
        Route::post('/account/profile', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateProfile']);
        Route::post('/account/logo', [App\Http\Controllers\Inertia\Client\AccountController::class, 'uploadLogo']);
        Route::post('/account/billing', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateBilling']);
        Route::post('/account/addresses', [App\Http\Controllers\Inertia\Client\AccountController::class, 'storeAddress']);
        Route::post('/account/addresses/{address}', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateAddress']);
        Route::post('/account/addresses/{address}/default', [App\Http\Controllers\Inertia\Client\AccountController::class, 'setDefaultAddress']);
        Route::post('/account/addresses/{address}/archive', [App\Http\Controllers\Inertia\Client\AccountController::class, 'archiveAddress']);
        Route::post('/account/addresses/{address}/restore', [App\Http\Controllers\Inertia\Client\AccountController::class, 'restoreAddress']);
        Route::post('/account/settings/notifications', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateNotificationPrefs']);
        Route::post('/account/settings/password', [App\Http\Controllers\Inertia\Client\AccountController::class, 'changePassword']);
        Route::post('/account/settings/sessions/revoke-others', [App\Http\Controllers\Inertia\Client\AccountController::class, 'revokeOtherSessions']);

        Route::get('/notifications', [App\Http\Controllers\Inertia\Client\NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [App\Http\Controllers\Inertia\Client\NotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [App\Http\Controllers\Inertia\Client\NotificationController::class, 'read']);

        Route::get('/content', [App\Http\Controllers\Inertia\Client\ContentController::class, 'index']);
        Route::get('/content/{content}', [App\Http\Controllers\Inertia\Client\ContentController::class, 'show']);
        Route::post('/content/{content}/approve', [App\Http\Controllers\Inertia\Client\ContentController::class, 'approve']);
        Route::post('/content/{content}/request-changes', [App\Http\Controllers\Inertia\Client\ContentController::class, 'requestChanges']);
        Route::get('/campaigns', [CampaignController::class, 'index']);
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
        Route::get('/campaigns/{campaign}/shortlist', [CampaignController::class, 'shortlist'])->middleware('nomination:client');
        Route::post('/campaigns/{campaign}/shortlist/items/{item}/decision', [CampaignController::class, 'shortlistDecision'])->middleware('nomination:client');
        Route::get('/contracts', [App\Http\Controllers\Inertia\Client\ContractController::class, 'index']);
        Route::get('/contracts/{contract}', [App\Http\Controllers\Inertia\Client\ContractController::class, 'show']);
        Route::post('/contracts/{contract}/sign', [App\Http\Controllers\Inertia\Client\ContractController::class, 'sign']);
        Route::get('/requests', [RequestController::class, 'index']);
        Route::post('/requests', [RequestController::class, 'store']);
        Route::get('/requests/{request}', [RequestController::class, 'show']);
        Route::post('/requests/{request}/comment', [RequestController::class, 'comment']);
        Route::get('/brands', [BrandController::class, 'index']);
        Route::post('/brands', [BrandController::class, 'store']);
        Route::get('/brands/{brand}', [BrandController::class, 'show']);
        Route::post('/brands/{brand}/update', [BrandController::class, 'update']);
        Route::post('/brands/{brand}/submit', [BrandController::class, 'submit']);
        Route::get('/team', [App\Http\Controllers\Inertia\Client\TeamController::class, 'index']);
        Route::post('/team/invite', [App\Http\Controllers\Inertia\Client\TeamController::class, 'invite']);
        Route::post('/team/{member}/role', [App\Http\Controllers\Inertia\Client\TeamController::class, 'changeRole']);
        Route::post('/team/{member}/status', [App\Http\Controllers\Inertia\Client\TeamController::class, 'changeStatus']);
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'upload']);
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    });

    // مسارات الحساب التاريخية: صفحاتها صارت تبويبات، وإجراءاتها تستدعي المتحكّم نفسه
    Route::redirect('/profile', '/client/account#profile');
    Route::redirect('/billing-profile', '/client/account#billing');
    Route::redirect('/addresses', '/client/account#addresses');
    Route::redirect('/settings', '/client/account#settings');
    Route::post('/profile', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateProfile']);
    Route::post('/profile/logo', [App\Http\Controllers\Inertia\Client\AccountController::class, 'uploadLogo']);
    Route::post('/billing-profile', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateBilling']);
    Route::post('/addresses', [App\Http\Controllers\Inertia\Client\AccountController::class, 'storeAddress']);
    Route::post('/addresses/{address}', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateAddress']);
    Route::post('/addresses/{address}/default', [App\Http\Controllers\Inertia\Client\AccountController::class, 'setDefaultAddress']);
    Route::post('/addresses/{address}/archive', [App\Http\Controllers\Inertia\Client\AccountController::class, 'archiveAddress']);
    Route::post('/addresses/{address}/restore', [App\Http\Controllers\Inertia\Client\AccountController::class, 'restoreAddress']);
    Route::post('/settings/notifications', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateNotificationPrefs']);
    Route::post('/settings/password', [App\Http\Controllers\Inertia\Client\AccountController::class, 'changePassword']);
    Route::post('/settings/sessions/revoke-others', [App\Http\Controllers\Inertia\Client\AccountController::class, 'revokeOtherSessions']);
    Route::post('/brands/{brand}', [BrandController::class, 'update']); // الشكل التاريخي لتحديث العلامة

    // وحدات لاحقة (بنية فقط، بلا بيانات وهمية)
    Route::get('/{section}', [ClientPortalController::class, 'stub'])
        ->whereIn('section', ['proposals', 'reports', 'payments']);
});

// ===== بوابة الشريك (الوكالة الخارجية) — Phase 5 =====
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Creator\InvitationAcceptController;
use App\Http\Controllers\Inertia\AccountController;
use App\Http\Controllers\Inertia\Admin\CreatorPoolController;
use App\Http\Controllers\Inertia\Admin\PlatformController;
use App\Http\Controllers\Inertia\Admin\SignupReviewController;
use App\Http\Controllers\Inertia\AgencyDashboardController;
use App\Http\Controllers\Inertia\AutomationController;
use App\Http\Controllers\Inertia\Brand\WorkspaceController;
use App\Http\Controllers\Inertia\BrandDetailController;
use App\Http\Controllers\Inertia\BrandsController;
use App\Http\Controllers\Inertia\CampaignDetailController;
use App\Http\Controllers\Inertia\CampaignsController;
use App\Http\Controllers\Inertia\Client\BrandController;
use App\Http\Controllers\Inertia\Client\CampaignController;
use App\Http\Controllers\Inertia\Client\DocumentController;
use App\Http\Controllers\Inertia\Client\RecommendationController;
use App\Http\Controllers\Inertia\Client\RequestController;
use App\Http\Controllers\Inertia\ClientChildrenController;
use App\Http\Controllers\Inertia\ClientDetailController;
use App\Http\Controllers\Inertia\ClientReviewsController;
use App\Http\Controllers\Inertia\ClientsController;
use App\Http\Controllers\Inertia\CollaborationDetailController;
use App\Http\Controllers\Inertia\CollaborationsController;
use App\Http\Controllers\Inertia\ContentController;
use App\Http\Controllers\Inertia\ContentDetailController;
use App\Http\Controllers\Inertia\ContractDetailController;
use App\Http\Controllers\Inertia\ContractsController;
use App\Http\Controllers\Inertia\Creator\CollaborationController;
use App\Http\Controllers\Inertia\Creator\ContractController;
use App\Http\Controllers\Inertia\Creator\DashboardController;
use App\Http\Controllers\Inertia\Creator\PayoutController;
use App\Http\Controllers\Inertia\CreatorApplicationsController;
use App\Http\Controllers\Inertia\CreatorDatabaseController;
use App\Http\Controllers\Inertia\CreatorDetailController;
use App\Http\Controllers\Inertia\CreatorInvitationController;
use App\Http\Controllers\Inertia\CreatorsController;
use App\Http\Controllers\Inertia\DeliverableMatchController;
use App\Http\Controllers\Inertia\ExportsController;
use App\Http\Controllers\Inertia\IntegrationsController;
use App\Http\Controllers\Inertia\InvoicesController;
use App\Http\Controllers\Inertia\MyTasksController;
use App\Http\Controllers\Inertia\NotificationController;
use App\Http\Controllers\Inertia\PartnersController;
use App\Http\Controllers\Inertia\PayoutDetailController;
use App\Http\Controllers\Inertia\PayoutsController;
use App\Http\Controllers\Inertia\ProductLabController;
use App\Http\Controllers\Inertia\PublishersController;
use App\Http\Controllers\Inertia\ReportsController;
use App\Http\Controllers\Inertia\ServiceRequestDetailController;
use App\Http\Controllers\Inertia\ServiceRequestsController;
use App\Http\Controllers\Inertia\SettingsController;
use App\Http\Controllers\Inertia\ShortlistController;
use App\Http\Controllers\Inertia\ShortlistingController;
use App\Http\Controllers\Inertia\SystemHealthController;
use App\Http\Controllers\Inertia\TeamController;
use App\Http\Controllers\Partner\PartnerAuthController;
use App\Http\Controllers\Partner\PartnerInvitationController;
use App\Http\Controllers\Partner\PartnerPortalController;
use App\Http\Controllers\Platform\ControlCenterController;
use App\Http\Controllers\Platform\PlatformPreviewController;
use App\Http\Controllers\Platform\PlatformSearchController;
use App\Http\Controllers\Platform\PlatformTenantController;
use App\Http\Controllers\Public\BrandSignupController;
use App\Http\Controllers\Public\StartController;
use App\Http\Controllers\Web\DevMailGalleryController;

Route::middleware('guest')->group(function () {
    Route::get('/partner/login', [PartnerAuthController::class, 'show'])->name('partner.login');
    Route::post('/partner/login', [PartnerAuthController::class, 'login'])->middleware('throttle:login');
    // قبول دعوة الشريك (عام، مُقيّد المعدّل، برمز بالـhash)
    Route::get('/partner/invite/{token}', [PartnerInvitationController::class, 'show'])->middleware('throttle:20,1');
    Route::post('/partner/invite/{token}', [PartnerInvitationController::class, 'accept'])->middleware('throttle:10,1');
});
Route::post('/partner/logout', [PartnerAuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', 'platform_preview:partner', 'partner_member'])->prefix('partner')->group(function () {
    // تبديل الوكالة يبقى Blade (جزء من تدفّق المصادقة)
    Route::post('/switch', [PartnerAuthController::class, 'switch']);

    // سطح المنتَج — React/Inertia (قُصّ من Blade بتكافؤ كامل)
    Route::middleware('inertia')->group(function () {
        Route::get('/', [App\Http\Controllers\Inertia\Partner\DashboardController::class, 'index']);
        Route::get('/dashboard', [App\Http\Controllers\Inertia\Partner\DashboardController::class, 'index']); // الرابط التاريخي بعد الدخول/قبول الدعوة
        Route::get('/requests', [App\Http\Controllers\Inertia\Partner\RequestController::class, 'index']);
        Route::post('/requests', [App\Http\Controllers\Inertia\Partner\RequestController::class, 'store']);
        Route::get('/requests/{request}', [App\Http\Controllers\Inertia\Partner\RequestController::class, 'show']);
        Route::post('/requests/{request}/comment', [App\Http\Controllers\Inertia\Partner\RequestController::class, 'comment']);
    });

    // وحدات لاحقة (بنية فقط، بلا بيانات وهمية)
    Route::get('/{section}', [PartnerPortalController::class, 'stub'])
        ->whereIn('section', ['briefs', 'content', 'reports', 'team', 'settings']);
});

// واجهة CRM (جلسة + سياق المستأجر). لا localStorage ولا بيانات وهمية — كل شيء من قاعدة البيانات.
// ==== React/Inertia (تطوير متوازٍ — لا يحذف نسخة Blade في /app حتى تُثبت بوابة القبول) ====
Route::middleware(['auth', 'tenant', 'platform_preview:agency', 'agency_member', 'inertia'])->prefix('beta')->group(function () {
    Route::get('/', AgencyDashboardController::class);
    Route::get('/clients', [ClientsController::class, 'index']);
    Route::get('/clients/export', [ClientsController::class, 'export']);
    Route::post('/clients', [ClientsController::class, 'store']);
    Route::get('/clients/{client}', [ClientDetailController::class, 'show']);
    Route::delete('/clients/{client}', [ClientsController::class, 'destroy']);
    Route::post('/clients/{client}/brands', [ClientChildrenController::class, 'storeBrand']);
    Route::post('/clients/{client}/contacts', [ClientChildrenController::class, 'storeContact']);
    Route::post('/clients/{client}/documents', [ClientChildrenController::class, 'storeDocument']);
    Route::post('/clients/{client}/members/invite', [ClientChildrenController::class, 'inviteMember']);
    Route::post('/clients/{client}/custom-fields', [ClientChildrenController::class, 'defineField']);
    Route::post('/clients/{client}/custom-fields/{definition}/set', [ClientChildrenController::class, 'setField']);
    // قاعدة المؤثرين (منتج مميّز) — محكومة بالاستحقاق + RBAC داخل المتحكّم
    Route::get('/creator-database', [CreatorDatabaseController::class, 'index']);
    Route::get('/creator-database/{poolCreator}', [CreatorDatabaseController::class, 'show']);
    Route::post('/creator-database/{poolCreator}/overlay', [CreatorDatabaseController::class, 'overlay']);
    Route::post('/creator-database/{poolCreator}/nominate', [CreatorDatabaseController::class, 'nominate'])->middleware('nomination:agency');
    Route::get('/creators', [CreatorsController::class, 'index']);
    Route::get('/creators/export', [CreatorsController::class, 'export']);
    Route::post('/creators', [CreatorsController::class, 'store']);
    Route::get('/creators/{creator}', [CreatorDetailController::class, 'show']);
    Route::get('/campaigns', [CampaignsController::class, 'index']);
    // تصدير قائمة الحملات (داخلي) — قبل {campaign} حتى لا يُلتقط "export" كمعرّف.
    Route::get('/campaigns/export', [CampaignsController::class, 'export']);
    Route::post('/campaigns', [CampaignsController::class, 'store']);
    Route::get('/campaigns/{campaign}', [CampaignDetailController::class, 'show']);
    // ملخّص الحملة (PDF) آمن للعميل — يُشارَك مع العميل.
    Route::get('/campaigns/{campaign}/client-brief', [CampaignDetailController::class, 'exportClientPdf']);
    Route::get('/campaigns/{campaign}/client-brief/preview', [CampaignDetailController::class, 'clientBriefPreview']);
    Route::get('/campaigns/{campaign}/client-brief/download', [CampaignDetailController::class, 'clientBriefDownload']);
    Route::post('/campaigns/{campaign}/client-brief/regenerate', [CampaignDetailController::class, 'clientBriefRegenerate']);
    Route::post('/campaigns/{campaign}', [CampaignDetailController::class, 'update']);
    Route::post('/campaigns/{campaign}/deliverables', [CampaignDetailController::class, 'addDeliverable']);
    Route::get('/campaigns/{campaign}/deliverables/{deliverable}/suggest', [DeliverableMatchController::class, 'suggest']);
    Route::post('/campaigns/{campaign}/deliverables/{deliverable}/offer', [DeliverableMatchController::class, 'offer']);
    Route::delete('/campaigns/{campaign}/deliverables/{deliverable}', [CampaignDetailController::class, 'removeDeliverable']);
    Route::get('/service-requests', [ServiceRequestsController::class, 'index']);
    Route::get('/service-requests/{serviceRequest}', [ServiceRequestDetailController::class, 'show']);
    Route::post('/service-requests', [ServiceRequestsController::class, 'store']);
    Route::post('/service-requests/{serviceRequest}/assign', [ServiceRequestDetailController::class, 'assign']);
    Route::post('/service-requests/{serviceRequest}/comment', [ServiceRequestDetailController::class, 'comment']);
    Route::post('/service-requests/{serviceRequest}/convert-campaign', [ServiceRequestDetailController::class, 'convertToCampaign']);
    Route::post('/service-requests/{serviceRequest}/{action}', [ServiceRequestDetailController::class, 'transition']);
    Route::get('/brands', [BrandsController::class, 'index']);
    Route::get('/brands/{brand}', [BrandDetailController::class, 'show']);
    Route::post('/brands/{brand}/{action}', [BrandDetailController::class, 'action']);
    Route::get('/content', [ContentController::class, 'index']);
    Route::get('/content/{content}', [ContentDetailController::class, 'show']);
    Route::post('/content/{content}/{action}', [ContentDetailController::class, 'action']);
    Route::get('/contracts', [ContractsController::class, 'index']);
    Route::post('/contracts', [ContractsController::class, 'store']);
    Route::get('/contracts/{contract}', [ContractDetailController::class, 'show']);
    Route::get('/contracts/{contract}/pdf/preview', [ContractDetailController::class, 'pdfPreview']);
    Route::get('/contracts/{contract}/pdf/download', [ContractDetailController::class, 'pdfDownload']);
    Route::post('/contracts/{contract}/pdf/regenerate', [ContractDetailController::class, 'pdfRegenerate']);
    Route::post('/contracts/{contract}', [ContractDetailController::class, 'update']);
    Route::post('/contracts/{contract}/{action}', [ContractDetailController::class, 'action']);
    Route::get('/payouts', [PayoutsController::class, 'index']);
    Route::get('/payouts/export', [PayoutsController::class, 'export']);
    Route::post('/payouts', [PayoutsController::class, 'store']);
    Route::get('/payouts/{payout}', [PayoutDetailController::class, 'show']);
    Route::get('/payouts/{payout}/statement/preview', [PayoutDetailController::class, 'pdfPreview']);
    Route::get('/payouts/{payout}/statement/download', [PayoutDetailController::class, 'pdfDownload']);
    Route::post('/payouts/{payout}/statement/regenerate', [PayoutDetailController::class, 'pdfRegenerate']);
    Route::post('/payouts/{payout}/{action}', [PayoutDetailController::class, 'action']);
    Route::get('/collaborations', [CollaborationsController::class, 'index']);
    Route::post('/collaborations', [CollaborationsController::class, 'store']);
    Route::get('/collaborations/{collaboration}', [CollaborationDetailController::class, 'show']);
    Route::post('/collaborations/{collaboration}/{action}', [CollaborationDetailController::class, 'action']);
    Route::get('/creator-applications', [CreatorApplicationsController::class, 'index']);
    Route::get('/creator-applications/{application}', [CreatorApplicationsController::class, 'show']);
    Route::post('/creator-applications/{application}/assign', [CreatorApplicationsController::class, 'assign']);
    Route::post('/creator-applications/{application}/request-completion', [CreatorApplicationsController::class, 'requestCompletion']);
    Route::post('/creator-applications/{application}/reject', [CreatorApplicationsController::class, 'reject']);
    Route::post('/creator-applications/{application}/approve', [CreatorApplicationsController::class, 'approve']);
    Route::post('/creator-applications/{application}/suspend', [CreatorApplicationsController::class, 'suspend']);
    Route::post('/creator-applications/{application}/note', [CreatorApplicationsController::class, 'addNote']);
    Route::post('/creator-applications/{application}/message', [CreatorApplicationsController::class, 'sendMessage']);
    Route::post('/creator-applications/{application}/mowthooq-review', [CreatorApplicationsController::class, 'reviewMowthooq']);
    Route::post('/creator-applications/{application}/financial-review', [CreatorApplicationsController::class, 'reviewFinancial']);
    Route::get('/creator-applications/{application}/documents/{document}/download', [CreatorApplicationsController::class, 'downloadDocument']);
    Route::get('/my-tasks', [MyTasksController::class, 'index']);
    Route::get('/shortlisting', [ShortlistingController::class, 'index'])->middleware('nomination:agency');
    Route::get('/partner-agencies', [PartnersController::class, 'index']);
    Route::post('/partner-agencies', [PartnersController::class, 'store']);
    Route::get('/partner-agencies/{partnerAgency}', [PartnersController::class, 'show']);
    Route::post('/partner-agencies/{partnerAgency}', [PartnersController::class, 'update']);
    Route::post('/partner-agencies/{partnerAgency}/invite', [PartnersController::class, 'invite']);
    Route::post('/partner-agencies/{partnerAgency}/links', [PartnersController::class, 'linkClient']);
    Route::post('/partner-agencies/{partnerAgency}/links/{link}/revoke', [PartnersController::class, 'revokeLink']);
    Route::post('/partner-agencies/{partnerAgency}/{action}', [PartnersController::class, 'action'])
        ->whereIn('action', ['submit', 'start', 'approve', 'request-changes', 'suspend']);
    Route::get('/publishers', [PublishersController::class, 'index']);
    Route::get('/publishers/{publisher}', [PublishersController::class, 'show']);
    Route::post('/publishers/{publisher}/save', [PublishersController::class, 'save']);
    Route::post('/publishers/{publisher}/convert', [PublishersController::class, 'convert']);
    Route::get('/reports', [ReportsController::class, 'index']);
    Route::get('/reports/export', [ReportsController::class, 'export']);
    Route::get('/reports/pdf/preview', [ReportsController::class, 'pdfPreview']);
    Route::get('/reports/pdf/download', [ReportsController::class, 'pdfDownload']);
    Route::post('/reports/pdf/regenerate', [ReportsController::class, 'pdfRegenerate']);
    // مركز التصدير — تقارير مجدولة + سجلّ تنزيلات (تنزيل آمن بترخيص). المسارات الثابتة قبل {exportJob}.
    Route::get('/exports', [ExportsController::class, 'index']);
    Route::post('/exports/schedules', [ExportsController::class, 'storeSchedule']);
    Route::post('/exports/schedules/{scheduledReport}/toggle', [ExportsController::class, 'toggleSchedule']);
    Route::delete('/exports/schedules/{scheduledReport}', [ExportsController::class, 'destroySchedule']);
    Route::get('/exports/{exportJob}/download', [ExportsController::class, 'download']);
    Route::get('/client-reviews', [ClientReviewsController::class, 'index']);
    Route::post('/client-reviews/profile/{changeRequest}/approve', [ClientReviewsController::class, 'approveProfile']);
    Route::post('/client-reviews/profile/{changeRequest}/reject', [ClientReviewsController::class, 'rejectProfile']);
    Route::post('/client-reviews/documents/{document}/review', [ClientReviewsController::class, 'reviewDocument']);
    Route::get('/client-reviews/documents/{document}/download', [ClientReviewsController::class, 'downloadDocument']);
    Route::get('/integrations', [IntegrationsController::class, 'index']);
    Route::get('/integrations/{provider}', [IntegrationsController::class, 'show']);
    Route::post('/integrations/{provider}/sync', [IntegrationsController::class, 'syncNow']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification');
    Route::get('/team', [TeamController::class, 'index']);
    Route::get('/team/{member}', [TeamController::class, 'member'])->whereNumber('member');
    Route::post('/team/invite', [TeamController::class, 'invite']);
    Route::post('/team/{member}/role', [TeamController::class, 'changeRole']);
    Route::post('/team/{member}/status', [TeamController::class, 'changeStatus']);
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::get('/system-health', [SystemHealthController::class, 'index']);
    Route::get('/automation', [AutomationController::class, 'index']);
    Route::post('/automation/{rule}/toggle', [AutomationController::class, 'toggle'])->whereNumber('rule');
    Route::post('/automation/{rule}', [AutomationController::class, 'update'])->whereNumber('rule');
    Route::post('/settings', [SettingsController::class, 'update']);
    // ترشيح المؤثرين (بوّابة الوكالة) — محكوم بمصدر القرار الموحّد: مُطفأ ⇒ 403 لكل هذه المسارات.
    Route::middleware('nomination:agency')->group(function () {
        Route::get('/campaigns/{campaign}/shortlist', [ShortlistController::class, 'index']);
        // تصدير الترشيحات — داخلي (XLSX/CSV) ومقترح PDF آمن للعميل. GET فلا يتعارض مع POST catch-all.
        Route::get('/campaigns/{campaign}/shortlist/export', [ShortlistController::class, 'export']);
        Route::get('/campaigns/{campaign}/shortlist/proposal', [ShortlistController::class, 'exportClientPdf']);
        Route::get('/campaigns/{campaign}/shortlist/proposal/preview', [ShortlistController::class, 'proposalPreview']);
        Route::get('/campaigns/{campaign}/shortlist/proposal/download', [ShortlistController::class, 'proposalDownload']);
        Route::post('/campaigns/{campaign}/shortlist/proposal/regenerate', [ShortlistController::class, 'proposalRegenerate']);
        Route::post('/campaigns/{campaign}/shortlist/add', [ShortlistController::class, 'add']);
        Route::post('/campaigns/{campaign}/shortlist/submit', [ShortlistController::class, 'submit']);
        Route::post('/campaigns/{campaign}/shortlist/revise', [ShortlistController::class, 'revise']);
        Route::post('/campaigns/{campaign}/shortlist/convert', [ShortlistController::class, 'convert']);
        Route::post('/campaigns/{campaign}/shortlist/items/{item}/remove', [ShortlistController::class, 'remove']);
    });
    Route::post('/campaigns/{campaign}/{action}', [CampaignDetailController::class, 'transition'])
        ->whereIn('action', ['plan', 'activate', 'pause', 'resume', 'complete', 'cancel']);
});

// بوابة المبدع — React/Inertia (بالتوازي مع Blade `/creator`)
Route::middleware(['auth', 'platform_preview:creator', 'creator', 'inertia'])->prefix('beta/creator')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/collaborations', [CollaborationController::class, 'index']);
    Route::get('/collaborations/{collaboration}', [CollaborationController::class, 'show']);
    Route::post('/collaborations/{collaboration}/{action}', [CollaborationController::class, 'action'])
        ->where('action', 'accept|decline|start|submit');
    Route::get('/content', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'index']);
    Route::post('/content', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'store']);
    Route::get('/content/{content}', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'show']);
    Route::post('/content/{content}/update', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'update']);
    Route::post('/content/{content}/submit', [App\Http\Controllers\Inertia\Creator\ContentController::class, 'submit']);
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/{contract}', [ContractController::class, 'show']);
    Route::post('/contracts/{contract}/sign', [ContractController::class, 'sign']);
    Route::get('/payouts', [PayoutController::class, 'index']);
    // حساب المبدع (ملف/منصّات/خدمات/أعمال/موثوق/مالية)
    Route::get('account', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'index']);
    Route::post('account/profile', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateProfile']);
    Route::post('account/avatar', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'uploadAvatar']);
    Route::post('account/platforms', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePlatform']);
    Route::post('account/platforms/{platform}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePlatform']);
    Route::post('account/services', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storeService']);
    Route::post('account/services/{service}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deleteService']);
    Route::post('account/portfolio', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePortfolio']);
    Route::post('account/portfolio/{item}/delete', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePortfolio']);
    Route::post('account/mowthooq', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateMowthooq']);
    Route::post('account/financial', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateFinancial']);
    Route::post('account/settings/notifications', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateNotificationPrefs']);
    Route::post('account/settings/password', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'changePassword']);
    Route::post('account/settings/sessions/revoke-others', [App\Http\Controllers\Inertia\Creator\AccountController::class, 'revokeOtherSessions']);
});

// بوابة مدير النظام (SaaS) — React/Inertia؛ إشراف عبر المستأجرين للقراءة فقط
Route::redirect('/admin', '/beta/admin');

Route::middleware(['auth', 'system_admin', 'inertia'])->prefix('beta/admin')->group(function () {
    // مراجعة طلبات فتح الحساب — استثناء مقصود عن كون اللوحة للقراءة فقط
    // قاعدة مبدعي مدير النظام — للترشيح والتحويل، وزرّ حذف كامل
    Route::get('/shortlisting', [App\Http\Controllers\Inertia\Admin\ShortlistingController::class, 'index']);
    Route::get('/creator-pool', [CreatorPoolController::class, 'index']);
    Route::post('/creator-pool/transfer', [CreatorPoolController::class, 'transfer']);
    Route::post('/creator-pool/purge', [CreatorPoolController::class, 'purge']);
    Route::get('/creator-pool/{poolCreator}', [CreatorPoolController::class, 'show'])->whereNumber('poolCreator');
    Route::post('/creator-pool/{poolCreator}/pricing', [CreatorPoolController::class, 'updatePricing'])->whereNumber('poolCreator');
    Route::post('/creator-pool/{poolCreator}/profile', [CreatorPoolController::class, 'updateProfile'])->whereNumber('poolCreator');
    Route::get('/signup-requests', [SignupReviewController::class, 'index']);
    Route::post('/signup-requests/{signupRequest}/contacted', [SignupReviewController::class, 'markContacted']);
    Route::post('/signup-requests/{signupRequest}/approve', [SignupReviewController::class, 'approve']);
    Route::post('/signup-requests/{signupRequest}/reject', [SignupReviewController::class, 'reject']);
    Route::get('/', [PlatformController::class, 'dashboard']);
    Route::get('/tenants', [PlatformController::class, 'tenants']);
    Route::post('/tenants/{tenant}', [PlatformController::class, 'updateTenant'])->whereNumber('tenant');
    Route::get('/plans', [PlatformController::class, 'plans']);
    Route::post('/plans/{plan}', [PlatformController::class, 'updatePlan'])->whereNumber('plan');
    Route::get('/subscriptions', [PlatformController::class, 'subscriptions']);
    Route::post('/subscriptions/{subscription}', [PlatformController::class, 'updateSubscription'])->whereNumber('subscription');
    Route::post('/subscriptions/{subscription}/creator-database', [PlatformController::class, 'setCreatorDatabaseAccess'])->whereNumber('subscription');
    Route::get('/audit', [PlatformController::class, 'audit']);
});

// مساحة «مالك المنصّة» (Platform Owner) — عابرة للمستأجرين، خارج نطاق أي مستأجر.
// لا middleware 'tenant': المالك يعمل بـwithBypass داخل المتحكّمات. الحرّاسة:
// auth + platform_owner (قدرة platform.owner الصريحة). P1: مركز التحكّم فقط؛
// مبدّل المستأجرين/البحث/المعاينة/التقمّص في مراحل لاحقة (P2–P4).
Route::middleware(['auth', 'platform_owner', 'inertia'])->prefix('platform')->group(function () {
    Route::get('/', ControlCenterController::class)->name('platform.home');
    // P2: مبدّل المستأجرين + البحث الشامل.
    Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('platform.tenants');
    Route::get('/tenants/{tenant}', [PlatformTenantController::class, 'show'])->whereNumber('tenant')->name('platform.tenant');
    // P3-hardening §5: بحث/تصفيح خادميّ في السياقات المؤهَّلة (بلا سقف ٢٥).
    Route::get('/tenants/{tenant}/contexts', [PlatformTenantController::class, 'contexts'])->whereNumber('tenant')->name('platform.tenant.contexts');
    // influencer_nomination.manage_feature — إتاحة الميزة لكل مستأجر (Platform Owner فقط).
    Route::post('/tenants/{tenant}/features/nomination', [PlatformTenantController::class, 'setNominationAvailability'])->whereNumber('tenant')->name('platform.tenant.nomination');
    Route::get('/search', PlatformSearchController::class)->name('platform.search');
    // P3: بدء/إنهاء معاينة بوّابة للقراءة فقط. exit قبل النمط ذي المعاملات كي لا يُلتقط.
    Route::get('/preview/exit', [PlatformPreviewController::class, 'exit'])->name('platform.preview.exit');
    Route::get('/preview/{tenant}/{portal}/{user}/{entity}', [PlatformPreviewController::class, 'start'])
        ->whereNumber(['tenant', 'user', 'entity'])->whereIn('portal', ['agency', 'client', 'creator', 'partner'])->name('platform.preview.start');
});

// بوابة الشريك — React/Inertia (بالتوازي مع Blade `/partner`)
Route::middleware(['auth', 'platform_preview:partner', 'partner_member', 'inertia'])->prefix('beta/partner')->group(function () {
    Route::get('/', [App\Http\Controllers\Inertia\Partner\DashboardController::class, 'index']);
    Route::get('/requests', [App\Http\Controllers\Inertia\Partner\RequestController::class, 'index']);
    Route::post('/requests', [App\Http\Controllers\Inertia\Partner\RequestController::class, 'store']);
    Route::get('/requests/{request}', [App\Http\Controllers\Inertia\Partner\RequestController::class, 'show']);
    Route::post('/requests/{request}/comment', [App\Http\Controllers\Inertia\Partner\RequestController::class, 'comment']);
});

// بوابة العميل — React/Inertia (بالتوازي مع Blade `/client`)
Route::middleware(['auth', 'platform_preview:client', 'client_member', 'inertia'])->prefix('beta/client')->group(function () {
    Route::get('/', [App\Http\Controllers\Inertia\Client\DashboardController::class, 'index']);
    Route::get('/content', [App\Http\Controllers\Inertia\Client\ContentController::class, 'index']);
    Route::get('/content/{content}', [App\Http\Controllers\Inertia\Client\ContentController::class, 'show']);
    Route::post('/content/{content}/approve', [App\Http\Controllers\Inertia\Client\ContentController::class, 'approve']);
    Route::post('/content/{content}/request-changes', [App\Http\Controllers\Inertia\Client\ContentController::class, 'requestChanges']);
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::get('/campaigns/{campaign}/shortlist', [CampaignController::class, 'shortlist'])->middleware('nomination:client');
    Route::post('/campaigns/{campaign}/shortlist/items/{item}/decision', [CampaignController::class, 'shortlistDecision'])->middleware('nomination:client');
    Route::get('/contracts', [App\Http\Controllers\Inertia\Client\ContractController::class, 'index']);
    Route::get('/contracts/{contract}', [App\Http\Controllers\Inertia\Client\ContractController::class, 'show']);
    Route::post('/contracts/{contract}/sign', [App\Http\Controllers\Inertia\Client\ContractController::class, 'sign']);
    // ترشيحات المؤثرين المحوّلة من مدير النظام — قرار العميل (قبول/رفض)
    Route::get('/recommendations', [RecommendationController::class, 'index']);
    Route::post('/recommendations/{recommendation}/decision', [RecommendationController::class, 'decision']);
    Route::get('/requests', [RequestController::class, 'index']);
    Route::post('/requests', [RequestController::class, 'store']);
    Route::get('/requests/{request}', [RequestController::class, 'show']);
    Route::post('/requests/{request}/comment', [RequestController::class, 'comment']);
    Route::get('/brands', [BrandController::class, 'index']);
    Route::post('/brands', [BrandController::class, 'store']);
    Route::get('/brands/{brand}', [BrandController::class, 'show']);
    Route::post('/brands/{brand}/update', [BrandController::class, 'update']);
    Route::post('/brands/{brand}/submit', [BrandController::class, 'submit']);
    Route::get('/team', [App\Http\Controllers\Inertia\Client\TeamController::class, 'index']);
    Route::post('/team/invite', [App\Http\Controllers\Inertia\Client\TeamController::class, 'invite']);
    Route::post('/team/{member}/role', [App\Http\Controllers\Inertia\Client\TeamController::class, 'changeRole']);
    Route::post('/team/{member}/status', [App\Http\Controllers\Inertia\Client\TeamController::class, 'changeStatus']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::post('/documents', [DocumentController::class, 'upload']);
    Route::get('/notifications', [App\Http\Controllers\Inertia\Client\NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Inertia\Client\NotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [App\Http\Controllers\Inertia\Client\NotificationController::class, 'read']);
    // حساب المنشأة: الملف/الفوترة/العناوين/الإعدادات
    Route::get('/account', [App\Http\Controllers\Inertia\Client\AccountController::class, 'index']);
    Route::post('/account/profile', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateProfile']);
    Route::post('/account/logo', [App\Http\Controllers\Inertia\Client\AccountController::class, 'uploadLogo']);
    Route::post('/account/billing', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateBilling']);
    Route::post('/account/addresses', [App\Http\Controllers\Inertia\Client\AccountController::class, 'storeAddress']);
    Route::post('/account/addresses/{address}', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateAddress']);
    Route::post('/account/addresses/{address}/default', [App\Http\Controllers\Inertia\Client\AccountController::class, 'setDefaultAddress']);
    Route::post('/account/addresses/{address}/archive', [App\Http\Controllers\Inertia\Client\AccountController::class, 'archiveAddress']);
    Route::post('/account/addresses/{address}/restore', [App\Http\Controllers\Inertia\Client\AccountController::class, 'restoreAddress']);
    Route::post('/account/settings/notifications', [App\Http\Controllers\Inertia\Client\AccountController::class, 'updateNotificationPrefs']);
    Route::post('/account/settings/password', [App\Http\Controllers\Inertia\Client\AccountController::class, 'changePassword']);
    Route::post('/account/settings/sessions/revoke-others', [App\Http\Controllers\Inertia\Client\AccountController::class, 'revokeOtherSessions']);
});

Route::middleware(['auth', 'tenant', 'platform_preview:agency', 'agency_member'])->prefix('app')->group(function () {

    // العلامات التجارية (عرض على مستوى الوكالة)

    // مراجعات العملاء (تعديلات قانونية + مستندات)

    // الوكالات الخارجية (الشركاء) — إدارة الوكالة

    // الحملات (منشئ الحملات) — إدارة الوكالة

    // المستحقات (إدارة الوكالة/المالية)

    // العقود (إدارة الوكالة)

    // مطابقة + عرض من مخرَج حملة: بُنيت في React وتُسجَّل أدناه في نفس المجموعة.
    // كان هنا تسجيلان يشيران إلى Web\CollaborationController المحذوف — لم يُوقعا
    // عطلًا لأن التسجيل اللاحق بنفس المسار يحلّ محلّ السابق في RouteCollection،
    // لكنهما كودٌ ميت يشير إلى صنف غير موجود فيُضلّل القارئ.

    // مراجعة العلامات (سير عمل الوكالة)

    // المبدعون (مؤثّرون + صنّاع UGC) — Phase 4

    // طلبات الانضمام — مراجعة الوكالة

    /*
     | مسارات /app المُحوَّلة إلى React/Inertia.
     | التحويل يتم مسارًا بمسار: يُنقل المسار هنا فقط بعد تكافؤ وظيفي كامل
     | ونجاح اختباراته، ثم تُحذف نسخة Blade الخاصة به. ما تبقّى في الأعلى
     | ما زال على Blade. الصفحات التي لا نسخة Blade لها أصلًا تُضاف هنا مباشرة.
     */
    Route::middleware('inertia')->group(function () {
        // التقارير (تجميعات حقيقية) — قُصّت من Blade
        Route::get('/reports', [ReportsController::class, 'index']);
        Route::get('/reports/export', [ReportsController::class, 'export']);
        Route::get('/reports/pdf/preview', [ReportsController::class, 'pdfPreview']);
        Route::get('/reports/pdf/download', [ReportsController::class, 'pdfDownload']);
        Route::post('/reports/pdf/regenerate', [ReportsController::class, 'pdfRegenerate']);

        // مركز التصدير — تقارير مجدولة + سجلّ تنزيلات (تنزيل آمن بترخيص). المسارات الثابتة قبل {exportJob}.
        Route::get('/exports', [ExportsController::class, 'index']);
        Route::post('/exports/schedules', [ExportsController::class, 'storeSchedule']);
        Route::post('/exports/schedules/{scheduledReport}/toggle', [ExportsController::class, 'toggleSchedule']);
        Route::delete('/exports/schedules/{scheduledReport}', [ExportsController::class, 'destroySchedule']);
        Route::get('/exports/{exportJob}/download', [ExportsController::class, 'download']);

        // طلبات الانضمام — قُصّت من Blade بكامل إجراءاتها (تعليق/ملاحظة/رسالة/موثوق/مالي/تنزيل)
        Route::get('/creator-applications', [CreatorApplicationsController::class, 'index']);
        Route::get('/creator-applications/{application}', [CreatorApplicationsController::class, 'show']);
        Route::post('/creator-applications/{application}/assign', [CreatorApplicationsController::class, 'assign']);
        Route::post('/creator-applications/{application}/request-completion', [CreatorApplicationsController::class, 'requestCompletion']);
        Route::post('/creator-applications/{application}/reject', [CreatorApplicationsController::class, 'reject']);
        Route::post('/creator-applications/{application}/approve', [CreatorApplicationsController::class, 'approve']);
        Route::post('/creator-applications/{application}/suspend', [CreatorApplicationsController::class, 'suspend']);
        Route::post('/creator-applications/{application}/note', [CreatorApplicationsController::class, 'addNote']);
        Route::post('/creator-applications/{application}/message', [CreatorApplicationsController::class, 'sendMessage']);
        Route::post('/creator-applications/{application}/mowthooq-review', [CreatorApplicationsController::class, 'reviewMowthooq']);
        Route::post('/creator-applications/{application}/financial-review', [CreatorApplicationsController::class, 'reviewFinancial']);
        Route::get('/creator-applications/{application}/documents/{document}/download', [CreatorApplicationsController::class, 'downloadDocument']);

        // الوكالات الشريكة — قُصّت من Blade بكامل إجراءاتها (سير عمل + دعوات + روابط مُنطّقة)
        Route::get('/partner-agencies', [PartnersController::class, 'index']);
        Route::post('/partner-agencies', [PartnersController::class, 'store']);
        Route::get('/partner-agencies/{partnerAgency}', [PartnersController::class, 'show']);
        Route::post('/partner-agencies/{partnerAgency}', [PartnersController::class, 'update']);
        Route::post('/partner-agencies/{partnerAgency}/invite', [PartnersController::class, 'invite']);
        Route::post('/partner-agencies/{partnerAgency}/links', [PartnersController::class, 'linkClient']);
        Route::post('/partner-agencies/{partnerAgency}/links/{link}/revoke', [PartnersController::class, 'revokeLink']);
        Route::post('/partner-agencies/{partnerAgency}/{action}', [PartnersController::class, 'action'])
            ->whereIn('action', ['submit', 'start', 'approve', 'request-changes', 'suspend']);

        // مختبر رحلات المنتَج — تطوير فقط (المتحكّم يرفض الإنتاج بـ404)
        Route::get('/product-lab', [ProductLabController::class, 'index']);
        Route::post('/product-lab/reseed', [ProductLabController::class, 'reseed']);

        // حساب المستخدم (أمان) — متاح لكل الأدوار، لا يخصّ الإدارة وحدها
        Route::get('/account', [AccountController::class, 'index']);
        Route::post('/account/notifications', [AccountController::class, 'updateNotificationPrefs']);
        Route::post('/account/password', [AccountController::class, 'changePassword']);
        Route::post('/account/sessions/revoke-others', [AccountController::class, 'revokeOtherSessions']);

        // لوحة التحكم — قُصّت من Blade (لوحة React حسب الدور عبر OperationalDashboard)
        Route::get('/', AgencyDashboardController::class);

        // الحملات — قُصّت من Blade بكامل إجراءاتها (انتقالات + مخرجات + ترشيح + مطابقة)
        Route::get('/campaigns', [CampaignsController::class, 'index']);
        // تصدير قائمة الحملات (داخلي) — قبل {campaign} حتى لا يُلتقط "export" كمعرّف.
        Route::get('/campaigns/export', [CampaignsController::class, 'export']);
        Route::post('/campaigns', [CampaignsController::class, 'store']);
        Route::get('/campaigns/{campaign}', [CampaignDetailController::class, 'show']);
        // ملخّص الحملة (PDF) آمن للعميل — يُشارَك مع العميل.
        Route::get('/campaigns/{campaign}/client-brief', [CampaignDetailController::class, 'exportClientPdf']);
        Route::get('/campaigns/{campaign}/client-brief/preview', [CampaignDetailController::class, 'clientBriefPreview']);
        Route::get('/campaigns/{campaign}/client-brief/download', [CampaignDetailController::class, 'clientBriefDownload']);
        Route::post('/campaigns/{campaign}/client-brief/regenerate', [CampaignDetailController::class, 'clientBriefRegenerate']);
        Route::post('/campaigns/{campaign}', [CampaignDetailController::class, 'update']);
        Route::post('/campaigns/{campaign}/deliverables', [CampaignDetailController::class, 'addDeliverable']);
        Route::delete('/campaigns/{campaign}/deliverables/{deliverable}', [CampaignDetailController::class, 'removeDeliverable']);
        // مطابقة المبدعين لمخرَج + عرض تعاون (قبل catch-all الإجراءات)
        Route::get('/campaigns/{campaign}/deliverables/{deliverable}/suggest', [DeliverableMatchController::class, 'suggest']);
        Route::post('/campaigns/{campaign}/deliverables/{deliverable}/offer', [DeliverableMatchController::class, 'offer']);
        // محرّك الترشيح (قبل catch-all الإجراءات) — محكوم بمصدر القرار الموحّد: مُطفأ ⇒ 403.
        Route::middleware('nomination:agency')->group(function () {
            Route::get('/campaigns/{campaign}/shortlist', [ShortlistController::class, 'index']);
            // تصدير الترشيحات — داخلي (XLSX/CSV) ومقترح PDF آمن للعميل. GET فلا يتعارض مع POST catch-all.
            Route::get('/campaigns/{campaign}/shortlist/export', [ShortlistController::class, 'export']);
            Route::get('/campaigns/{campaign}/shortlist/proposal', [ShortlistController::class, 'exportClientPdf']);
            Route::get('/campaigns/{campaign}/shortlist/proposal/preview', [ShortlistController::class, 'proposalPreview']);
            Route::get('/campaigns/{campaign}/shortlist/proposal/download', [ShortlistController::class, 'proposalDownload']);
            Route::post('/campaigns/{campaign}/shortlist/proposal/regenerate', [ShortlistController::class, 'proposalRegenerate']);
            Route::post('/campaigns/{campaign}/shortlist/add', [ShortlistController::class, 'add']);
            Route::post('/campaigns/{campaign}/shortlist/submit', [ShortlistController::class, 'submit']);
            Route::post('/campaigns/{campaign}/shortlist/revise', [ShortlistController::class, 'revise']);
            Route::post('/campaigns/{campaign}/shortlist/convert', [ShortlistController::class, 'convert']);
            Route::post('/campaigns/{campaign}/shortlist/items/{item}/remove', [ShortlistController::class, 'remove']);
        });
        Route::post('/campaigns/{campaign}/{action}', [CampaignDetailController::class, 'transition'])
            ->whereIn('action', ['plan', 'activate', 'pause', 'resume', 'complete', 'cancel']);

        // مراجعات العملاء — قُصّت من Blade (التنزيل استجابة ملف لا صفحة)
        Route::get('/client-reviews', [ClientReviewsController::class, 'index']);
        Route::post('/client-reviews/profile/{changeRequest}/approve', [ClientReviewsController::class, 'approveProfile']);
        Route::post('/client-reviews/profile/{changeRequest}/reject', [ClientReviewsController::class, 'rejectProfile']);
        Route::post('/client-reviews/documents/{document}/review', [ClientReviewsController::class, 'reviewDocument']);
        Route::get('/client-reviews/documents/{document}/download', [ClientReviewsController::class, 'downloadDocument']);

        // العملاء وإجراءاتهم الفرعية — قُصّت من Blade
        Route::get('/clients', [ClientsController::class, 'index']);
        Route::get('/clients/export', [ClientsController::class, 'export']);
        Route::post('/clients', [ClientsController::class, 'store']);
        Route::get('/clients/{client}', [ClientDetailController::class, 'show']);
        Route::delete('/clients/{client}', [ClientsController::class, 'destroy']);
        Route::post('/clients/{client}/update', [ClientsController::class, 'update']);
        Route::post('/clients/{client}/brands', [ClientChildrenController::class, 'storeBrand']);
        Route::post('/clients/{client}/contacts', [ClientChildrenController::class, 'storeContact']);
        Route::post('/clients/{client}/documents', [ClientChildrenController::class, 'storeDocument']);
        Route::post('/clients/{client}/members/invite', [ClientChildrenController::class, 'inviteMember']);
        Route::post('/clients/{client}/custom-fields', [ClientChildrenController::class, 'defineField']);
        Route::post('/clients/{client}/custom-fields/{definition}/set', [ClientChildrenController::class, 'setField']);

        // العقود — قُصّت من Blade؛ التحرير على المسودة فقط (updateDraft يرفض ما بعدها)
        Route::get('/contracts', [ContractsController::class, 'index']);
        Route::post('/contracts', [ContractsController::class, 'store']);
        Route::get('/contracts/{contract}', [ContractDetailController::class, 'show']);
        Route::get('/contracts/{contract}/pdf/preview', [ContractDetailController::class, 'pdfPreview']);
        Route::get('/contracts/{contract}/pdf/download', [ContractDetailController::class, 'pdfDownload']);
        Route::post('/contracts/{contract}/pdf/regenerate', [ContractDetailController::class, 'pdfRegenerate']);
        Route::post('/contracts/{contract}', [ContractDetailController::class, 'update']);
        Route::post('/contracts/{contract}/{action}', [ContractDetailController::class, 'action'])
            ->whereIn('action', ['send', 'activate', 'complete', 'terminate', 'cancel']);

        // التعاونات — قُصّت من Blade؛ يبقى suggest/offer على Blade حتى يكتمل بديلهما
        Route::get('/collaborations', [CollaborationsController::class, 'index']);
        Route::post('/collaborations', [CollaborationsController::class, 'store']);
        Route::get('/collaborations/{collaboration}', [CollaborationDetailController::class, 'show']);
        Route::post('/collaborations/{collaboration}/{action}', [CollaborationDetailController::class, 'action'])
            ->whereIn('action', ['approve', 'request-revision', 'complete', 'cancel', 'issue-contract', 'create-payout']);

        // المستحقات — قُصّت من Blade؛ الإنشاء يعيد استخدام PayoutWorkflowService نفسه
        // الفواتير — الطرف الآخر من الدفتر: كانت المالية مستحقاتٍ بلا مطالبة
        Route::get('/invoices', [InvoicesController::class, 'index']);
        Route::post('/invoices', [InvoicesController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoicesController::class, 'show']);
        Route::get('/invoices/{invoice}/pdf', [InvoicesController::class, 'exportPdf']);
        Route::get('/invoices/{invoice}/pdf/preview', [InvoicesController::class, 'pdfPreview']);
        Route::get('/invoices/{invoice}/pdf/download', [InvoicesController::class, 'pdfDownload']);
        Route::post('/invoices/{invoice}/pdf/regenerate', [InvoicesController::class, 'pdfRegenerate']);
        Route::post('/invoices/{invoice}/update', [InvoicesController::class, 'update']);
        Route::post('/invoices/{invoice}/issue', [InvoicesController::class, 'issue']);
        Route::post('/invoices/{invoice}/pay', [InvoicesController::class, 'pay']);
        Route::post('/invoices/{invoice}/cancel', [InvoicesController::class, 'cancel']);
        Route::get('/campaigns/{campaign}/invoice-items', [InvoicesController::class, 'suggestItems']);
        Route::get('/payouts', [PayoutsController::class, 'index']);
        Route::get('/payouts/export', [PayoutsController::class, 'export']);
        Route::post('/payouts', [PayoutsController::class, 'store']);
        Route::get('/payouts/{payout}', [PayoutDetailController::class, 'show']);
        Route::get('/payouts/{payout}/statement/preview', [PayoutDetailController::class, 'pdfPreview']);
        Route::get('/payouts/{payout}/statement/download', [PayoutDetailController::class, 'pdfDownload']);
        Route::post('/payouts/{payout}/statement/regenerate', [PayoutDetailController::class, 'pdfRegenerate']);
        Route::post('/payouts/{payout}/{action}', [PayoutDetailController::class, 'action'])
            ->whereIn('action', ['approve', 'schedule', 'send-to-provider', 'mark-paid', 'mark-failed', 'cancel']);

        // قاعدة المؤثرين (منتج مميّز) — محكومة بالاستحقاق + RBAC داخل المتحكّم
        Route::get('/creator-database', [CreatorDatabaseController::class, 'index']);
        Route::get('/creator-database/{poolCreator}', [CreatorDatabaseController::class, 'show']);
        Route::post('/creator-database/{poolCreator}/overlay', [CreatorDatabaseController::class, 'overlay']);
        Route::post('/creator-database/{poolCreator}/nominate', [CreatorDatabaseController::class, 'nominate'])->middleware('nomination:agency');
        // المبدعون — قُصّوا من Blade؛ الإضافة تعيد استخدام CreateCreator نفسه
        Route::get('/creators', [CreatorsController::class, 'index']);
        Route::get('/creators/export', [CreatorsController::class, 'export']);
        Route::post('/creators/{creator}/update', [CreatorsController::class, 'update']);
        Route::post('/creators/{creator}/invite', [CreatorInvitationController::class, 'store']);
        Route::post('/creator-invitations/{invitation}/resend', [CreatorInvitationController::class, 'resend']);
        Route::post('/creator-invitations/{invitation}/revoke', [CreatorInvitationController::class, 'revoke']);
        Route::post('/creators', [CreatorsController::class, 'store']);
        Route::get('/creators/{creator}', [CreatorDetailController::class, 'show']);

        // طلبات الخدمة — قُصّت من Blade بتكافؤ كامل (بما فيها التحويل إلى حملة)
        Route::get('/service-requests', [ServiceRequestsController::class, 'index']);
        Route::get('/service-requests/{serviceRequest}', [ServiceRequestDetailController::class, 'show']);
        // تسجيل طلب نيابةً عن العميل — قبل المسارات ذات المتغيّرات
        Route::post('/service-requests', [ServiceRequestsController::class, 'store']);
        Route::post('/service-requests/{serviceRequest}/assign', [ServiceRequestDetailController::class, 'assign']);
        Route::post('/service-requests/{serviceRequest}/comment', [ServiceRequestDetailController::class, 'comment']);
        Route::post('/service-requests/{serviceRequest}/convert-campaign', [ServiceRequestDetailController::class, 'convertToCampaign']);
        Route::post('/service-requests/{serviceRequest}/{action}', [ServiceRequestDetailController::class, 'transition'])
            ->whereIn('action', ['triage', 'start', 'request-info', 'resolve', 'close', 'reopen', 'cancel']);

        // العلامات — قُصّت من Blade (كان index فقط)؛ التفاصيل والإجراءات إضافة
        Route::get('/brands', [BrandsController::class, 'index']);
        Route::get('/brands/{brand}', [BrandDetailController::class, 'show']);
        Route::post('/brands/{brand}/{action}', [BrandDetailController::class, 'action'])
            ->whereIn('action', ['submit', 'start', 'approve', 'request-changes', 'suspend']);

        // المحتوى والموافقات (مرحلة الوكالة) — قُصّ من Blade بتكافؤ إجراءات كامل
        Route::get('/content', [ContentController::class, 'index']);
        Route::get('/content/{content}', [ContentDetailController::class, 'show']);
        Route::post('/content/{content}/{action}', [ContentDetailController::class, 'action'])
            ->whereIn('action', ['start-review', 'send-to-client', 'request-changes', 'reject', 'publish', 'schedule', 'reschedule', 'record-proof', 'record-results']);

        // صفحات React لا نسخة Blade لها — كانت متاحة تحت /beta فقط
        Route::get('/my-tasks', [MyTasksController::class, 'index']);
        Route::get('/shortlisting', [ShortlistingController::class, 'index'])->middleware('nomination:agency');
        Route::get('/integrations', [IntegrationsController::class, 'index']);
        Route::get('/integrations/{provider}', [IntegrationsController::class, 'show']);
        Route::post('/integrations/{provider}/sync', [IntegrationsController::class, 'syncNow']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
        Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereNumber('notification');
        Route::get('/team', [TeamController::class, 'index']);
        Route::get('/team/{member}', [TeamController::class, 'member'])->whereNumber('member');
        Route::post('/team/invite', [TeamController::class, 'invite']);
        Route::post('/team/{member}/role', [TeamController::class, 'changeRole']);
        Route::post('/team/{member}/status', [TeamController::class, 'changeStatus']);
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::get('/system-health', [SystemHealthController::class, 'index']);
        Route::get('/automation', [AutomationController::class, 'index']);
        Route::post('/automation/{rule}/toggle', [AutomationController::class, 'toggle'])->whereNumber('rule');
        Route::post('/automation/{rule}', [AutomationController::class, 'update'])->whereNumber('rule');
        Route::post('/settings', [SettingsController::class, 'update']);
        Route::get('/publishers', [PublishersController::class, 'index']);
        Route::get('/publishers/{publisher}', [PublishersController::class, 'show']);
        Route::post('/publishers/{publisher}/save', [PublishersController::class, 'save']);
        Route::post('/publishers/{publisher}/convert', [PublishersController::class, 'convert']);
    });

    // مركز المعاينة (تطوير فقط — محجوب في الإنتاج)
    Route::get('/preview', [PreviewCenterController::class, 'index']);
    Route::get('/preview/design-system', [PreviewCenterController::class, 'designSystem']);
    Route::post('/preview/showcase/seed', [PreviewCenterController::class, 'seedShowcase']);
    Route::post('/preview/showcase/reset', [PreviewCenterController::class, 'resetShowcase']);
    // معرض البريد (تطوير فقط) — فحص بصريّ لحالات البريد بلغتَي ar/en. المسارات الثابتة قبل {state}.
    Route::get('/preview/mail', [DevMailGalleryController::class, 'index']);
    Route::get('/preview/mail/{state}', [DevMailGalleryController::class, 'show'])->where('state', '[a-z_]+');
});

/*
|--------------------------------------------------------------------------
| تسجيل العلامة التجارية لنفسها
|--------------------------------------------------------------------------
|
| مسار عامّ بلا مصادقة. الخانق نفسه المستعمَل في تسجيل الوكالة: رمزٌ من ستّ
| خانات بلا خنق يُخمَّن، وحدُّ المحاولات في الخدمة وحده لا يمنع التوزيع على
| عدّة سجلّات.
|
| ولا يتفرّع المسار في الواجهة بعد المطابقة: الخادم يقرّر الوجهة، فلا يستدلّ
| المستخدم من تفرّعٍ ظاهر على أن علامته موجودة عندنا.
*/
Route::middleware('inertia')->controller(BrandSignupController::class)->group(function () {
    Route::get('/register/brand', 'startForm');
    Route::post('/register/brand/start', 'start')->middleware('throttle:join-start');

    Route::get('/register/brand/verify/{reference}', 'verifyEmailForm');
    Route::get('/register/brand/verify/{reference}/{code}', 'verifyEmailLink')->name('register.brand.verify-link')->middleware('signed');
    Route::post('/register/brand/verify/{reference}', 'verifyEmail')->middleware('throttle:join-otp');

    Route::get('/register/brand/phone/{reference}', 'phoneForm');
    Route::post('/register/brand/phone/{reference}', 'startPhone')->middleware('throttle:join-otp');
    Route::post('/register/brand/phone/{reference}/verify', 'verifyPhone')->middleware('throttle:join-otp');
    Route::post('/register/brand/resend/{reference}', 'resend')->middleware('throttle:join-otp');

    Route::get('/register/brand/details/{reference}', 'detailsForm');
    Route::post('/register/brand/details/{reference}', 'saveDetails')->middleware('throttle:join-op');

    Route::get('/register/brand/owner/{reference}', 'ownerForm');
    Route::post('/register/brand/complete/{reference}', 'complete')->middleware('throttle:join-op');

    // مسار التطابق القويّ: إثبات ملكية قبل أيّ وصول
    Route::get('/register/brand/verify-ownership/{reference}', 'verifyOwnershipForm');
    Route::post('/register/brand/claim/{reference}', 'submitClaim')->middleware('throttle:join-op');
    Route::post('/register/brand/claim/{reference}/document', 'uploadDocument')->middleware('throttle:join-op');
});

/*
|--------------------------------------------------------------------------
| مساحة العلامة
|--------------------------------------------------------------------------
|
| `brand_member` يحلّ العلامة ويضبط السياق مرّة واحدة. لا متحكّم هنا يضبط
| سياقًا ولا يُعيده.
*/
Route::prefix('brand')->middleware(['auth', 'brand_member', 'inertia'])
    ->controller(WorkspaceController::class)->group(function () {
        Route::get('/', 'overview');
        Route::get('/requests', 'requests');
        Route::get('/campaigns', 'campaigns');
        Route::get('/shortlists', 'shortlists');
        Route::get('/content', 'content');
        Route::get('/contracts', 'contracts');
        Route::get('/invoices', 'invoices');
        Route::get('/payouts', 'payouts');
        Route::get('/reports', 'reports');
        Route::get('/notifications', 'notifications');
        Route::get('/team', 'team');
        Route::get('/agencies', 'agencies');
        Route::get('/settings', 'settings');

        // تفويض الوكالات — الدعوة والنطاق والإلغاء
        Route::post('/agencies/invite', 'inviteAgency');
        Route::post('/agencies/{relationship}/scope', 'updateScope');
        Route::post('/agencies/{relationship}/revoke', 'revokeAgency');
    });

/*
|--------------------------------------------------------------------------
| بدء التسجيل — المسار الرسمي الوحيد لاختيار نوع الحساب
|--------------------------------------------------------------------------
*/
Route::middleware('inertia')->group(function () {
    Route::get('/start', [StartController::class, 'index']);
    Route::post('/start', [StartController::class, 'begin'])
        ->middleware('throttle:join-start');
});
