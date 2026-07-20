<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\PreviewCenterController;
use Illuminate\Support\Facades\Route;

// ===== الموقع العام — أوّل ما يراه الزائر (لا يهبط في لوحة داخلية) =====
use App\Http\Controllers\Public\SiteController;
Route::middleware('inertia')->controller(SiteController::class)->group(function () {
    Route::get('/', 'home')->name('home');
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
    Route::post('/login', [LoginController::class, 'login']);
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth');

// ===== بوابة المبدع (Portal مستقل) — Phase 4 =====
use App\Http\Controllers\Creator\{CreatorAuthController, CreatorPortalController};
Route::middleware('guest')->group(function () {
    Route::get('/creator/login', [CreatorAuthController::class, 'show'])->name('creator.login');
    Route::post('/creator/login', [CreatorAuthController::class, 'login']);

    // قبول دعوة صانع المحتوى — الرمز في الرابط هو الإذن، فلا مصادقة قبله
    Route::get('/creator/invitation/{token}', [\App\Http\Controllers\Creator\InvitationAcceptController::class, 'show'])
        ->middleware('inertia')->name('creator.invitation');
    Route::post('/creator/invitation/{token}/verify-email', [\App\Http\Controllers\Creator\InvitationAcceptController::class, 'verifyEmail'])->middleware('inertia');
    Route::post('/creator/invitation/{token}/verify-phone', [\App\Http\Controllers\Creator\InvitationAcceptController::class, 'verifyPhone'])->middleware('inertia');
    Route::post('/creator/invitation/{token}/accept', [\App\Http\Controllers\Creator\InvitationAcceptController::class, 'accept'])->middleware('inertia');
});
Route::post('/creator/logout', [CreatorAuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', 'creator'])->prefix('creator')->group(function () {
    // سطح المنتَج — React/Inertia (قُصّ من Blade)
    Route::middleware('inertia')->group(function () {
        Route::get('/', [\App\Http\Controllers\Inertia\Creator\DashboardController::class, 'index']);
        Route::get('/dashboard', [\App\Http\Controllers\Inertia\Creator\DashboardController::class, 'index']); // الرابط التاريخي بعد الدخول

        // الحساب: ملف/منصّات/خدمات/أعمال/موثوق/مالية في مساحة واحدة بتبويبات
        Route::get('/account', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'index']);
        Route::post('/account/profile', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateProfile']);
        // القدرات يحرّرها الصانع نفسه — ما يجيده يتغيّر بعد التقديم
        Route::post('/account/capabilities', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateCapabilities']);
        Route::post('/account/avatar', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'uploadAvatar']);
        Route::post('/account/platforms', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePlatform']);
        Route::post('/account/platforms/{platform}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePlatform']);
        Route::post('/account/services', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storeService']);
        Route::post('/account/services/{service}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deleteService']);
        Route::post('/account/portfolio', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePortfolio']);
        Route::post('/account/portfolio/{item}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePortfolio']);
        Route::post('/account/mowthooq', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateMowthooq']);
        Route::post('/account/financial', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateFinancial']);
    Route::post('/account/settings/notifications', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateNotificationPrefs']);
    Route::post('/account/settings/password', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'changePassword']);
    Route::post('/account/settings/sessions/revoke-others', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'revokeOtherSessions']);
        Route::post('/account/settings/notifications', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateNotificationPrefs']);
        Route::post('/account/settings/password', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'changePassword']);
        Route::post('/account/settings/sessions/revoke-others', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'revokeOtherSessions']);

        Route::get('/collaborations', [\App\Http\Controllers\Inertia\Creator\CollaborationController::class, 'index']);
        Route::get('/collaborations/{collaboration}', [\App\Http\Controllers\Inertia\Creator\CollaborationController::class, 'show']);
        Route::post('/collaborations/{collaboration}/{action}', [\App\Http\Controllers\Inertia\Creator\CollaborationController::class, 'action'])
            ->where('action', 'accept|decline|start|submit');
        Route::get('/content', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'index']);
        Route::post('/content', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'store']);
        Route::get('/content/{content}', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'show']);
        Route::post('/content/{content}/update', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'update']);
        Route::post('/content/{content}/submit', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'submit']);
        Route::get('/contracts', [\App\Http\Controllers\Inertia\Creator\ContractController::class, 'index']);
        Route::get('/contracts/{contract}', [\App\Http\Controllers\Inertia\Creator\ContractController::class, 'show']);
        Route::post('/contracts/{contract}/sign', [\App\Http\Controllers\Inertia\Creator\ContractController::class, 'sign']);
        Route::get('/payouts', [\App\Http\Controllers\Inertia\Creator\PayoutController::class, 'index']);
        Route::get('/notifications', [\App\Http\Controllers\Inertia\Creator\NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\Inertia\Creator\NotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Inertia\Creator\NotificationController::class, 'read']);
    });

    // مسارات الحساب التاريخية: صفحاتها صارت تبويبات، وإجراءاتها تستدعي المتحكّم نفسه
    Route::redirect('/profile', '/creator/account#profile');
    Route::redirect('/platforms', '/creator/account#platforms');
    Route::redirect('/services', '/creator/account#services');
    Route::redirect('/portfolio', '/creator/account#portfolio');
    Route::redirect('/mowthooq', '/creator/account#verification');
    Route::redirect('/financial', '/creator/account#financial');
    Route::post('/profile', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateProfile']);
    Route::post('/capabilities', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateCapabilities']);
    Route::post('/platforms', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePlatform']);
    Route::post('/platforms/{platform}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePlatform']);
    Route::post('/services', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storeService']);
    Route::post('/services/{service}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deleteService']);
    Route::post('/portfolio', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePortfolio']);
    Route::post('/portfolio/{item}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePortfolio']);
    Route::post('/mowthooq', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateMowthooq']);
    Route::post('/financial', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateFinancial']);
    Route::post('/avatar', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'uploadAvatar']);

    // وحدات لاحقة (بنية فقط، بلا بيانات وهمية)
    Route::get('/{section}', [CreatorPortalController::class, 'stub'])
        ->whereIn('section', ['opportunities', 'settings']);
});

// ===== بوابة العميل (Portal مستقل) — Phase 5 =====
use App\Http\Controllers\Client\{ClientAuthController, ClientPortalController};
Route::middleware('guest')->group(function () {
    Route::get('/client/login', [ClientAuthController::class, 'show'])->name('client.login');
    Route::post('/client/login', [ClientAuthController::class, 'login']);
});
Route::post('/client/logout', [ClientAuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', 'client_member'])->prefix('client')->group(function () {
    // تبديل العميل النشِط يبقى Blade (جزء من تدفّق المصادقة)
    Route::post('/switch', [ClientAuthController::class, 'switch']);

    // سطح المنتَج — React/Inertia (قُصّ من Blade)
    Route::middleware('inertia')->group(function () {
        Route::get('/', [\App\Http\Controllers\Inertia\Client\DashboardController::class, 'index']);
        Route::get('/dashboard', [\App\Http\Controllers\Inertia\Client\DashboardController::class, 'index']); // الرابط التاريخي بعد الدخول

        // حساب المنشأة: الملف/الفوترة/العناوين/الإعدادات في مساحة واحدة
        Route::get('/account', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'index']);
        Route::post('/account/profile', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateProfile']);
        Route::post('/account/logo', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'uploadLogo']);
        Route::post('/account/billing', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateBilling']);
        Route::post('/account/addresses', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'storeAddress']);
        Route::post('/account/addresses/{address}', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateAddress']);
        Route::post('/account/addresses/{address}/default', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'setDefaultAddress']);
        Route::post('/account/addresses/{address}/archive', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'archiveAddress']);
        Route::post('/account/addresses/{address}/restore', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'restoreAddress']);
        Route::post('/account/settings/notifications', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateNotificationPrefs']);
        Route::post('/account/settings/password', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'changePassword']);
        Route::post('/account/settings/sessions/revoke-others', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'revokeOtherSessions']);

        Route::get('/notifications', [\App\Http\Controllers\Inertia\Client\NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\Inertia\Client\NotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Inertia\Client\NotificationController::class, 'read']);

        Route::get('/content', [\App\Http\Controllers\Inertia\Client\ContentController::class, 'index']);
        Route::get('/content/{content}', [\App\Http\Controllers\Inertia\Client\ContentController::class, 'show']);
        Route::post('/content/{content}/approve', [\App\Http\Controllers\Inertia\Client\ContentController::class, 'approve']);
        Route::post('/content/{content}/request-changes', [\App\Http\Controllers\Inertia\Client\ContentController::class, 'requestChanges']);
        Route::get('/campaigns', [\App\Http\Controllers\Inertia\Client\CampaignController::class, 'index']);
        Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Inertia\Client\CampaignController::class, 'show']);
        Route::get('/campaigns/{campaign}/shortlist', [\App\Http\Controllers\Inertia\Client\CampaignController::class, 'shortlist']);
        Route::post('/campaigns/{campaign}/shortlist/items/{item}/decision', [\App\Http\Controllers\Inertia\Client\CampaignController::class, 'shortlistDecision']);
        Route::get('/contracts', [\App\Http\Controllers\Inertia\Client\ContractController::class, 'index']);
        Route::get('/contracts/{contract}', [\App\Http\Controllers\Inertia\Client\ContractController::class, 'show']);
        Route::post('/contracts/{contract}/sign', [\App\Http\Controllers\Inertia\Client\ContractController::class, 'sign']);
        Route::get('/requests', [\App\Http\Controllers\Inertia\Client\RequestController::class, 'index']);
        Route::post('/requests', [\App\Http\Controllers\Inertia\Client\RequestController::class, 'store']);
        Route::get('/requests/{request}', [\App\Http\Controllers\Inertia\Client\RequestController::class, 'show']);
        Route::post('/requests/{request}/comment', [\App\Http\Controllers\Inertia\Client\RequestController::class, 'comment']);
        Route::get('/brands', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'index']);
        Route::post('/brands', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'store']);
        Route::get('/brands/{brand}', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'show']);
        Route::post('/brands/{brand}/update', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'update']);
        Route::post('/brands/{brand}/submit', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'submit']);
        Route::get('/team', [\App\Http\Controllers\Inertia\Client\TeamController::class, 'index']);
        Route::post('/team/invite', [\App\Http\Controllers\Inertia\Client\TeamController::class, 'invite']);
        Route::post('/team/{member}/role', [\App\Http\Controllers\Inertia\Client\TeamController::class, 'changeRole']);
        Route::post('/team/{member}/status', [\App\Http\Controllers\Inertia\Client\TeamController::class, 'changeStatus']);
        Route::get('/documents', [\App\Http\Controllers\Inertia\Client\DocumentController::class, 'index']);
        Route::post('/documents', [\App\Http\Controllers\Inertia\Client\DocumentController::class, 'upload']);
        Route::get('/documents/{document}/download', [\App\Http\Controllers\Inertia\Client\DocumentController::class, 'download']);
    });

    // مسارات الحساب التاريخية: صفحاتها صارت تبويبات، وإجراءاتها تستدعي المتحكّم نفسه
    Route::redirect('/profile', '/client/account#profile');
    Route::redirect('/billing-profile', '/client/account#billing');
    Route::redirect('/addresses', '/client/account#addresses');
    Route::redirect('/settings', '/client/account#settings');
    Route::post('/profile', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateProfile']);
    Route::post('/profile/logo', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'uploadLogo']);
    Route::post('/billing-profile', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateBilling']);
    Route::post('/addresses', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'storeAddress']);
    Route::post('/addresses/{address}', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateAddress']);
    Route::post('/addresses/{address}/default', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'setDefaultAddress']);
    Route::post('/addresses/{address}/archive', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'archiveAddress']);
    Route::post('/addresses/{address}/restore', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'restoreAddress']);
    Route::post('/settings/notifications', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateNotificationPrefs']);
    Route::post('/settings/password', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'changePassword']);
    Route::post('/settings/sessions/revoke-others', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'revokeOtherSessions']);
    Route::post('/brands/{brand}', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'update']); // الشكل التاريخي لتحديث العلامة

    // وحدات لاحقة (بنية فقط، بلا بيانات وهمية)
    Route::get('/{section}', [ClientPortalController::class, 'stub'])
        ->whereIn('section', ['proposals', 'reports', 'payments']);
});

// ===== بوابة الشريك (الوكالة الخارجية) — Phase 5 =====
use App\Http\Controllers\Partner\{PartnerAuthController, PartnerPortalController};
Route::middleware('guest')->group(function () {
    Route::get('/partner/login', [PartnerAuthController::class, 'show'])->name('partner.login');
    Route::post('/partner/login', [PartnerAuthController::class, 'login']);
    // قبول دعوة الشريك (عام، مُقيّد المعدّل، برمز بالـhash)
    Route::get('/partner/invite/{token}', [\App\Http\Controllers\Partner\PartnerInvitationController::class, 'show'])->middleware('throttle:20,1');
    Route::post('/partner/invite/{token}', [\App\Http\Controllers\Partner\PartnerInvitationController::class, 'accept'])->middleware('throttle:10,1');
});
Route::post('/partner/logout', [PartnerAuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', 'partner_member'])->prefix('partner')->group(function () {
    // تبديل الوكالة يبقى Blade (جزء من تدفّق المصادقة)
    Route::post('/switch', [PartnerAuthController::class, 'switch']);

    // سطح المنتَج — React/Inertia (قُصّ من Blade بتكافؤ كامل)
    Route::middleware('inertia')->group(function () {
        Route::get('/', [\App\Http\Controllers\Inertia\Partner\DashboardController::class, 'index']);
        Route::get('/dashboard', [\App\Http\Controllers\Inertia\Partner\DashboardController::class, 'index']); // الرابط التاريخي بعد الدخول/قبول الدعوة
        Route::get('/requests', [\App\Http\Controllers\Inertia\Partner\RequestController::class, 'index']);
        Route::post('/requests', [\App\Http\Controllers\Inertia\Partner\RequestController::class, 'store']);
        Route::get('/requests/{request}', [\App\Http\Controllers\Inertia\Partner\RequestController::class, 'show']);
        Route::post('/requests/{request}/comment', [\App\Http\Controllers\Inertia\Partner\RequestController::class, 'comment']);
    });

    // وحدات لاحقة (بنية فقط، بلا بيانات وهمية)
    Route::get('/{section}', [PartnerPortalController::class, 'stub'])
        ->whereIn('section', ['briefs', 'content', 'reports', 'team', 'settings']);
});

// واجهة CRM (جلسة + سياق المستأجر). لا localStorage ولا بيانات وهمية — كل شيء من قاعدة البيانات.
// ==== React/Inertia (تطوير متوازٍ — لا يحذف نسخة Blade في /app حتى تُثبت بوابة القبول) ====
Route::middleware(['auth', 'tenant', 'agency_member', 'inertia'])->prefix('beta')->group(function () {
    Route::get('/', \App\Http\Controllers\Inertia\AgencyDashboardController::class);
    Route::get('/clients', [\App\Http\Controllers\Inertia\ClientsController::class, 'index']);
    Route::post('/clients', [\App\Http\Controllers\Inertia\ClientsController::class, 'store']);
    Route::get('/clients/{client}', [\App\Http\Controllers\Inertia\ClientDetailController::class, 'show']);
    Route::delete('/clients/{client}', [\App\Http\Controllers\Inertia\ClientsController::class, 'destroy']);
    Route::post('/clients/{client}/brands', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'storeBrand']);
    Route::post('/clients/{client}/contacts', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'storeContact']);
    Route::post('/clients/{client}/documents', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'storeDocument']);
    Route::post('/clients/{client}/members/invite', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'inviteMember']);
    Route::post('/clients/{client}/custom-fields', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'defineField']);
    Route::post('/clients/{client}/custom-fields/{definition}/set', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'setField']);
    Route::get('/creators', [\App\Http\Controllers\Inertia\CreatorsController::class, 'index']);
    Route::post('/creators', [\App\Http\Controllers\Inertia\CreatorsController::class, 'store']);
    Route::get('/creators/{creator}', [\App\Http\Controllers\Inertia\CreatorDetailController::class, 'show']);
    Route::get('/campaigns', [\App\Http\Controllers\Inertia\CampaignsController::class, 'index']);
    Route::post('/campaigns', [\App\Http\Controllers\Inertia\CampaignsController::class, 'store']);
    Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'show']);
    Route::post('/campaigns/{campaign}', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'update']);
    Route::post('/campaigns/{campaign}/deliverables', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'addDeliverable']);
    Route::get('/campaigns/{campaign}/deliverables/{deliverable}/suggest', [\App\Http\Controllers\Inertia\DeliverableMatchController::class, 'suggest']);
    Route::post('/campaigns/{campaign}/deliverables/{deliverable}/offer', [\App\Http\Controllers\Inertia\DeliverableMatchController::class, 'offer']);
    Route::delete('/campaigns/{campaign}/deliverables/{deliverable}', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'removeDeliverable']);
    Route::get('/service-requests', [\App\Http\Controllers\Inertia\ServiceRequestsController::class, 'index']);
    Route::get('/service-requests/{serviceRequest}', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'show']);
    Route::post('/service-requests', [\App\Http\Controllers\Inertia\ServiceRequestsController::class, 'store']);
    Route::post('/service-requests/{serviceRequest}/assign', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'assign']);
    Route::post('/service-requests/{serviceRequest}/comment', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'comment']);
    Route::post('/service-requests/{serviceRequest}/convert-campaign', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'convertToCampaign']);
    Route::post('/service-requests/{serviceRequest}/{action}', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'transition']);
    Route::get('/brands', [\App\Http\Controllers\Inertia\BrandsController::class, 'index']);
    Route::get('/brands/{brand}', [\App\Http\Controllers\Inertia\BrandDetailController::class, 'show']);
    Route::post('/brands/{brand}/{action}', [\App\Http\Controllers\Inertia\BrandDetailController::class, 'action']);
    Route::get('/content', [\App\Http\Controllers\Inertia\ContentController::class, 'index']);
    Route::get('/content/{content}', [\App\Http\Controllers\Inertia\ContentDetailController::class, 'show']);
    Route::post('/content/{content}/{action}', [\App\Http\Controllers\Inertia\ContentDetailController::class, 'action']);
    Route::get('/contracts', [\App\Http\Controllers\Inertia\ContractsController::class, 'index']);
    Route::post('/contracts', [\App\Http\Controllers\Inertia\ContractsController::class, 'store']);
    Route::get('/contracts/{contract}', [\App\Http\Controllers\Inertia\ContractDetailController::class, 'show']);
    Route::post('/contracts/{contract}', [\App\Http\Controllers\Inertia\ContractDetailController::class, 'update']);
    Route::post('/contracts/{contract}/{action}', [\App\Http\Controllers\Inertia\ContractDetailController::class, 'action']);
    Route::get('/payouts', [\App\Http\Controllers\Inertia\PayoutsController::class, 'index']);
    Route::post('/payouts', [\App\Http\Controllers\Inertia\PayoutsController::class, 'store']);
    Route::get('/payouts/{payout}', [\App\Http\Controllers\Inertia\PayoutDetailController::class, 'show']);
    Route::post('/payouts/{payout}/{action}', [\App\Http\Controllers\Inertia\PayoutDetailController::class, 'action']);
    Route::get('/collaborations', [\App\Http\Controllers\Inertia\CollaborationsController::class, 'index']);
    Route::post('/collaborations', [\App\Http\Controllers\Inertia\CollaborationsController::class, 'store']);
    Route::get('/collaborations/{collaboration}', [\App\Http\Controllers\Inertia\CollaborationDetailController::class, 'show']);
    Route::post('/collaborations/{collaboration}/{action}', [\App\Http\Controllers\Inertia\CollaborationDetailController::class, 'action']);
    Route::get('/creator-applications', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'index']);
    Route::get('/creator-applications/{application}', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'show']);
    Route::post('/creator-applications/{application}/assign', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'assign']);
    Route::post('/creator-applications/{application}/request-completion', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'requestCompletion']);
    Route::post('/creator-applications/{application}/reject', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'reject']);
    Route::post('/creator-applications/{application}/approve', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'approve']);
    Route::post('/creator-applications/{application}/suspend', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'suspend']);
    Route::post('/creator-applications/{application}/note', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'addNote']);
    Route::post('/creator-applications/{application}/message', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'sendMessage']);
    Route::post('/creator-applications/{application}/mowthooq-review', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'reviewMowthooq']);
    Route::post('/creator-applications/{application}/financial-review', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'reviewFinancial']);
    Route::get('/creator-applications/{application}/documents/{document}/download', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'downloadDocument']);
    Route::get('/my-tasks', [\App\Http\Controllers\Inertia\MyTasksController::class, 'index']);
    Route::get('/shortlisting', [\App\Http\Controllers\Inertia\ShortlistingController::class, 'index']);
    Route::get('/partner-agencies', [\App\Http\Controllers\Inertia\PartnersController::class, 'index']);
    Route::post('/partner-agencies', [\App\Http\Controllers\Inertia\PartnersController::class, 'store']);
    Route::get('/partner-agencies/{partnerAgency}', [\App\Http\Controllers\Inertia\PartnersController::class, 'show']);
    Route::post('/partner-agencies/{partnerAgency}', [\App\Http\Controllers\Inertia\PartnersController::class, 'update']);
    Route::post('/partner-agencies/{partnerAgency}/invite', [\App\Http\Controllers\Inertia\PartnersController::class, 'invite']);
    Route::post('/partner-agencies/{partnerAgency}/links', [\App\Http\Controllers\Inertia\PartnersController::class, 'linkClient']);
    Route::post('/partner-agencies/{partnerAgency}/links/{link}/revoke', [\App\Http\Controllers\Inertia\PartnersController::class, 'revokeLink']);
    Route::post('/partner-agencies/{partnerAgency}/{action}', [\App\Http\Controllers\Inertia\PartnersController::class, 'action'])
        ->whereIn('action', ['submit', 'start', 'approve', 'request-changes', 'suspend']);
    Route::get('/publishers', [\App\Http\Controllers\Inertia\PublishersController::class, 'index']);
    Route::get('/publishers/{publisher}', [\App\Http\Controllers\Inertia\PublishersController::class, 'show']);
    Route::post('/publishers/{publisher}/save', [\App\Http\Controllers\Inertia\PublishersController::class, 'save']);
    Route::post('/publishers/{publisher}/convert', [\App\Http\Controllers\Inertia\PublishersController::class, 'convert']);
    Route::get('/reports', [\App\Http\Controllers\Inertia\ReportsController::class, 'index']);
    Route::get('/client-reviews', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'index']);
    Route::post('/client-reviews/profile/{changeRequest}/approve', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'approveProfile']);
    Route::post('/client-reviews/profile/{changeRequest}/reject', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'rejectProfile']);
    Route::post('/client-reviews/documents/{document}/review', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'reviewDocument']);
    Route::get('/client-reviews/documents/{document}/download', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'downloadDocument']);
    Route::get('/integrations', [\App\Http\Controllers\Inertia\IntegrationsController::class, 'index']);
    Route::get('/team', [\App\Http\Controllers\Inertia\TeamController::class, 'index']);
    Route::get('/team', [\App\Http\Controllers\Inertia\TeamController::class, 'index']);
    Route::get('/settings', [\App\Http\Controllers\Inertia\SettingsController::class, 'index']);
    Route::get('/campaigns/{campaign}/shortlist', [\App\Http\Controllers\Inertia\ShortlistController::class, 'index']);
    Route::post('/campaigns/{campaign}/shortlist/add', [\App\Http\Controllers\Inertia\ShortlistController::class, 'add']);
    Route::post('/campaigns/{campaign}/shortlist/submit', [\App\Http\Controllers\Inertia\ShortlistController::class, 'submit']);
    Route::post('/campaigns/{campaign}/shortlist/revise', [\App\Http\Controllers\Inertia\ShortlistController::class, 'revise']);
    Route::post('/campaigns/{campaign}/shortlist/items/{item}/remove', [\App\Http\Controllers\Inertia\ShortlistController::class, 'remove']);
    Route::post('/campaigns/{campaign}/{action}', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'transition'])
        ->whereIn('action', ['plan', 'activate', 'pause', 'resume', 'complete', 'cancel']);
});

// بوابة المبدع — React/Inertia (بالتوازي مع Blade `/creator`)
Route::middleware(['auth', 'creator', 'inertia'])->prefix('beta/creator')->group(function () {
    Route::get('/', [\App\Http\Controllers\Inertia\Creator\DashboardController::class, 'index']);
    Route::get('/collaborations', [\App\Http\Controllers\Inertia\Creator\CollaborationController::class, 'index']);
    Route::get('/collaborations/{collaboration}', [\App\Http\Controllers\Inertia\Creator\CollaborationController::class, 'show']);
    Route::post('/collaborations/{collaboration}/{action}', [\App\Http\Controllers\Inertia\Creator\CollaborationController::class, 'action'])
        ->where('action', 'accept|decline|start|submit');
    Route::get('/content', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'index']);
    Route::post('/content', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'store']);
    Route::get('/content/{content}', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'show']);
    Route::post('/content/{content}/update', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'update']);
    Route::post('/content/{content}/submit', [\App\Http\Controllers\Inertia\Creator\ContentController::class, 'submit']);
    Route::get('/contracts', [\App\Http\Controllers\Inertia\Creator\ContractController::class, 'index']);
    Route::get('/contracts/{contract}', [\App\Http\Controllers\Inertia\Creator\ContractController::class, 'show']);
    Route::post('/contracts/{contract}/sign', [\App\Http\Controllers\Inertia\Creator\ContractController::class, 'sign']);
    Route::get('/payouts', [\App\Http\Controllers\Inertia\Creator\PayoutController::class, 'index']);
    // حساب المبدع (ملف/منصّات/خدمات/أعمال/موثوق/مالية)
    Route::get('account', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'index']);
    Route::post('account/profile', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateProfile']);
    Route::post('account/avatar', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'uploadAvatar']);
    Route::post('account/platforms', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePlatform']);
    Route::post('account/platforms/{platform}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePlatform']);
    Route::post('account/services', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storeService']);
    Route::post('account/services/{service}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deleteService']);
    Route::post('account/portfolio', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'storePortfolio']);
    Route::post('account/portfolio/{item}/delete', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'deletePortfolio']);
    Route::post('account/mowthooq', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateMowthooq']);
    Route::post('account/financial', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateFinancial']);
    Route::post('account/settings/notifications', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'updateNotificationPrefs']);
    Route::post('account/settings/password', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'changePassword']);
    Route::post('account/settings/sessions/revoke-others', [\App\Http\Controllers\Inertia\Creator\AccountController::class, 'revokeOtherSessions']);
});

// بوابة مدير النظام (SaaS) — React/Inertia؛ إشراف عبر المستأجرين للقراءة فقط
Route::middleware(['auth', 'system_admin', 'inertia'])->prefix('beta/admin')->group(function () {
    // مراجعة طلبات فتح الحساب — استثناء مقصود عن كون اللوحة للقراءة فقط
    Route::get('/signup-requests', [\App\Http\Controllers\Inertia\Admin\SignupReviewController::class, 'index']);
    Route::post('/signup-requests/{signupRequest}/contacted', [\App\Http\Controllers\Inertia\Admin\SignupReviewController::class, 'markContacted']);
    Route::post('/signup-requests/{signupRequest}/approve', [\App\Http\Controllers\Inertia\Admin\SignupReviewController::class, 'approve']);
    Route::post('/signup-requests/{signupRequest}/reject', [\App\Http\Controllers\Inertia\Admin\SignupReviewController::class, 'reject']);
    Route::get('/', [\App\Http\Controllers\Inertia\Admin\PlatformController::class, 'dashboard']);
    Route::get('/tenants', [\App\Http\Controllers\Inertia\Admin\PlatformController::class, 'tenants']);
    Route::get('/plans', [\App\Http\Controllers\Inertia\Admin\PlatformController::class, 'plans']);
    Route::get('/subscriptions', [\App\Http\Controllers\Inertia\Admin\PlatformController::class, 'subscriptions']);
    Route::get('/audit', [\App\Http\Controllers\Inertia\Admin\PlatformController::class, 'audit']);
});

// بوابة الشريك — React/Inertia (بالتوازي مع Blade `/partner`)
Route::middleware(['auth', 'partner_member', 'inertia'])->prefix('beta/partner')->group(function () {
    Route::get('/', [\App\Http\Controllers\Inertia\Partner\DashboardController::class, 'index']);
    Route::get('/requests', [\App\Http\Controllers\Inertia\Partner\RequestController::class, 'index']);
    Route::post('/requests', [\App\Http\Controllers\Inertia\Partner\RequestController::class, 'store']);
    Route::get('/requests/{request}', [\App\Http\Controllers\Inertia\Partner\RequestController::class, 'show']);
    Route::post('/requests/{request}/comment', [\App\Http\Controllers\Inertia\Partner\RequestController::class, 'comment']);
});

// بوابة العميل — React/Inertia (بالتوازي مع Blade `/client`)
Route::middleware(['auth', 'client_member', 'inertia'])->prefix('beta/client')->group(function () {
    Route::get('/', [\App\Http\Controllers\Inertia\Client\DashboardController::class, 'index']);
    Route::get('/content', [\App\Http\Controllers\Inertia\Client\ContentController::class, 'index']);
    Route::get('/content/{content}', [\App\Http\Controllers\Inertia\Client\ContentController::class, 'show']);
    Route::post('/content/{content}/approve', [\App\Http\Controllers\Inertia\Client\ContentController::class, 'approve']);
    Route::post('/content/{content}/request-changes', [\App\Http\Controllers\Inertia\Client\ContentController::class, 'requestChanges']);
    Route::get('/campaigns', [\App\Http\Controllers\Inertia\Client\CampaignController::class, 'index']);
    Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Inertia\Client\CampaignController::class, 'show']);
    Route::get('/campaigns/{campaign}/shortlist', [\App\Http\Controllers\Inertia\Client\CampaignController::class, 'shortlist']);
    Route::post('/campaigns/{campaign}/shortlist/items/{item}/decision', [\App\Http\Controllers\Inertia\Client\CampaignController::class, 'shortlistDecision']);
    Route::get('/contracts', [\App\Http\Controllers\Inertia\Client\ContractController::class, 'index']);
    Route::get('/contracts/{contract}', [\App\Http\Controllers\Inertia\Client\ContractController::class, 'show']);
    Route::post('/contracts/{contract}/sign', [\App\Http\Controllers\Inertia\Client\ContractController::class, 'sign']);
    Route::get('/requests', [\App\Http\Controllers\Inertia\Client\RequestController::class, 'index']);
    Route::post('/requests', [\App\Http\Controllers\Inertia\Client\RequestController::class, 'store']);
    Route::get('/requests/{request}', [\App\Http\Controllers\Inertia\Client\RequestController::class, 'show']);
    Route::post('/requests/{request}/comment', [\App\Http\Controllers\Inertia\Client\RequestController::class, 'comment']);
    Route::get('/brands', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'index']);
    Route::post('/brands', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'store']);
    Route::get('/brands/{brand}', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'show']);
    Route::post('/brands/{brand}/update', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'update']);
    Route::post('/brands/{brand}/submit', [\App\Http\Controllers\Inertia\Client\BrandController::class, 'submit']);
    Route::get('/team', [\App\Http\Controllers\Inertia\Client\TeamController::class, 'index']);
    Route::post('/team/invite', [\App\Http\Controllers\Inertia\Client\TeamController::class, 'invite']);
    Route::post('/team/{member}/role', [\App\Http\Controllers\Inertia\Client\TeamController::class, 'changeRole']);
    Route::post('/team/{member}/status', [\App\Http\Controllers\Inertia\Client\TeamController::class, 'changeStatus']);
    Route::get('/documents', [\App\Http\Controllers\Inertia\Client\DocumentController::class, 'index']);
    Route::get('/documents/{document}/download', [\App\Http\Controllers\Inertia\Client\DocumentController::class, 'download']);
    Route::post('/documents', [\App\Http\Controllers\Inertia\Client\DocumentController::class, 'upload']);
    Route::get('/notifications', [\App\Http\Controllers\Inertia\Client\NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Inertia\Client\NotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Inertia\Client\NotificationController::class, 'read']);
    // حساب المنشأة: الملف/الفوترة/العناوين/الإعدادات
    Route::get('/account', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'index']);
    Route::post('/account/profile', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateProfile']);
    Route::post('/account/logo', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'uploadLogo']);
    Route::post('/account/billing', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateBilling']);
    Route::post('/account/addresses', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'storeAddress']);
    Route::post('/account/addresses/{address}', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateAddress']);
    Route::post('/account/addresses/{address}/default', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'setDefaultAddress']);
    Route::post('/account/addresses/{address}/archive', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'archiveAddress']);
    Route::post('/account/addresses/{address}/restore', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'restoreAddress']);
    Route::post('/account/settings/notifications', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'updateNotificationPrefs']);
    Route::post('/account/settings/password', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'changePassword']);
    Route::post('/account/settings/sessions/revoke-others', [\App\Http\Controllers\Inertia\Client\AccountController::class, 'revokeOtherSessions']);
});

Route::middleware(['auth', 'tenant', 'agency_member'])->prefix('app')->group(function () {

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
        Route::get('/reports', [\App\Http\Controllers\Inertia\ReportsController::class, 'index']);

        // طلبات الانضمام — قُصّت من Blade بكامل إجراءاتها (تعليق/ملاحظة/رسالة/موثوق/مالي/تنزيل)
        Route::get('/creator-applications', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'index']);
        Route::get('/creator-applications/{application}', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'show']);
        Route::post('/creator-applications/{application}/assign', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'assign']);
        Route::post('/creator-applications/{application}/request-completion', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'requestCompletion']);
        Route::post('/creator-applications/{application}/reject', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'reject']);
        Route::post('/creator-applications/{application}/approve', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'approve']);
        Route::post('/creator-applications/{application}/suspend', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'suspend']);
        Route::post('/creator-applications/{application}/note', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'addNote']);
        Route::post('/creator-applications/{application}/message', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'sendMessage']);
        Route::post('/creator-applications/{application}/mowthooq-review', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'reviewMowthooq']);
        Route::post('/creator-applications/{application}/financial-review', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'reviewFinancial']);
        Route::get('/creator-applications/{application}/documents/{document}/download', [\App\Http\Controllers\Inertia\CreatorApplicationsController::class, 'downloadDocument']);

        // الوكالات الشريكة — قُصّت من Blade بكامل إجراءاتها (سير عمل + دعوات + روابط مُنطّقة)
        Route::get('/partner-agencies', [\App\Http\Controllers\Inertia\PartnersController::class, 'index']);
        Route::post('/partner-agencies', [\App\Http\Controllers\Inertia\PartnersController::class, 'store']);
        Route::get('/partner-agencies/{partnerAgency}', [\App\Http\Controllers\Inertia\PartnersController::class, 'show']);
        Route::post('/partner-agencies/{partnerAgency}', [\App\Http\Controllers\Inertia\PartnersController::class, 'update']);
        Route::post('/partner-agencies/{partnerAgency}/invite', [\App\Http\Controllers\Inertia\PartnersController::class, 'invite']);
        Route::post('/partner-agencies/{partnerAgency}/links', [\App\Http\Controllers\Inertia\PartnersController::class, 'linkClient']);
        Route::post('/partner-agencies/{partnerAgency}/links/{link}/revoke', [\App\Http\Controllers\Inertia\PartnersController::class, 'revokeLink']);
        Route::post('/partner-agencies/{partnerAgency}/{action}', [\App\Http\Controllers\Inertia\PartnersController::class, 'action'])
            ->whereIn('action', ['submit', 'start', 'approve', 'request-changes', 'suspend']);

        // مختبر رحلات المنتَج — تطوير فقط (المتحكّم يرفض الإنتاج بـ404)
        Route::get('/product-lab', [\App\Http\Controllers\Inertia\ProductLabController::class, 'index']);
        Route::post('/product-lab/reseed', [\App\Http\Controllers\Inertia\ProductLabController::class, 'reseed']);

        // حساب المستخدم (أمان) — متاح لكل الأدوار، لا يخصّ الإدارة وحدها
        Route::get('/account', [\App\Http\Controllers\Inertia\AccountController::class, 'index']);
        Route::post('/account/notifications', [\App\Http\Controllers\Inertia\AccountController::class, 'updateNotificationPrefs']);
        Route::post('/account/password', [\App\Http\Controllers\Inertia\AccountController::class, 'changePassword']);
        Route::post('/account/sessions/revoke-others', [\App\Http\Controllers\Inertia\AccountController::class, 'revokeOtherSessions']);

        // لوحة التحكم — قُصّت من Blade (لوحة React حسب الدور عبر OperationalDashboard)
        Route::get('/', \App\Http\Controllers\Inertia\AgencyDashboardController::class);

        // الحملات — قُصّت من Blade بكامل إجراءاتها (انتقالات + مخرجات + ترشيح + مطابقة)
        Route::get('/campaigns', [\App\Http\Controllers\Inertia\CampaignsController::class, 'index']);
        Route::post('/campaigns', [\App\Http\Controllers\Inertia\CampaignsController::class, 'store']);
        Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'show']);
        Route::post('/campaigns/{campaign}', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'update']);
        Route::post('/campaigns/{campaign}/deliverables', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'addDeliverable']);
        Route::delete('/campaigns/{campaign}/deliverables/{deliverable}', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'removeDeliverable']);
        // مطابقة المبدعين لمخرَج + عرض تعاون (قبل catch-all الإجراءات)
        Route::get('/campaigns/{campaign}/deliverables/{deliverable}/suggest', [\App\Http\Controllers\Inertia\DeliverableMatchController::class, 'suggest']);
        Route::post('/campaigns/{campaign}/deliverables/{deliverable}/offer', [\App\Http\Controllers\Inertia\DeliverableMatchController::class, 'offer']);
        // محرّك الترشيح (قبل catch-all الإجراءات)
        Route::get('/campaigns/{campaign}/shortlist', [\App\Http\Controllers\Inertia\ShortlistController::class, 'index']);
        Route::post('/campaigns/{campaign}/shortlist/add', [\App\Http\Controllers\Inertia\ShortlistController::class, 'add']);
        Route::post('/campaigns/{campaign}/shortlist/submit', [\App\Http\Controllers\Inertia\ShortlistController::class, 'submit']);
        Route::post('/campaigns/{campaign}/shortlist/revise', [\App\Http\Controllers\Inertia\ShortlistController::class, 'revise']);
        Route::post('/campaigns/{campaign}/shortlist/items/{item}/remove', [\App\Http\Controllers\Inertia\ShortlistController::class, 'remove']);
        Route::post('/campaigns/{campaign}/{action}', [\App\Http\Controllers\Inertia\CampaignDetailController::class, 'transition'])
            ->whereIn('action', ['plan', 'activate', 'pause', 'resume', 'complete', 'cancel']);

        // مراجعات العملاء — قُصّت من Blade (التنزيل استجابة ملف لا صفحة)
        Route::get('/client-reviews', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'index']);
        Route::post('/client-reviews/profile/{changeRequest}/approve', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'approveProfile']);
        Route::post('/client-reviews/profile/{changeRequest}/reject', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'rejectProfile']);
        Route::post('/client-reviews/documents/{document}/review', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'reviewDocument']);
        Route::get('/client-reviews/documents/{document}/download', [\App\Http\Controllers\Inertia\ClientReviewsController::class, 'downloadDocument']);

        // العملاء وإجراءاتهم الفرعية — قُصّت من Blade
        Route::get('/clients', [\App\Http\Controllers\Inertia\ClientsController::class, 'index']);
        Route::post('/clients', [\App\Http\Controllers\Inertia\ClientsController::class, 'store']);
        Route::get('/clients/{client}', [\App\Http\Controllers\Inertia\ClientDetailController::class, 'show']);
        Route::delete('/clients/{client}', [\App\Http\Controllers\Inertia\ClientsController::class, 'destroy']);
        Route::post('/clients/{client}/update', [\App\Http\Controllers\Inertia\ClientsController::class, 'update']);
        Route::post('/clients/{client}/brands', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'storeBrand']);
        Route::post('/clients/{client}/contacts', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'storeContact']);
        Route::post('/clients/{client}/documents', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'storeDocument']);
        Route::post('/clients/{client}/members/invite', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'inviteMember']);
        Route::post('/clients/{client}/custom-fields', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'defineField']);
        Route::post('/clients/{client}/custom-fields/{definition}/set', [\App\Http\Controllers\Inertia\ClientChildrenController::class, 'setField']);

        // العقود — قُصّت من Blade؛ التحرير على المسودة فقط (updateDraft يرفض ما بعدها)
        Route::get('/contracts', [\App\Http\Controllers\Inertia\ContractsController::class, 'index']);
        Route::post('/contracts', [\App\Http\Controllers\Inertia\ContractsController::class, 'store']);
        Route::get('/contracts/{contract}', [\App\Http\Controllers\Inertia\ContractDetailController::class, 'show']);
        Route::post('/contracts/{contract}', [\App\Http\Controllers\Inertia\ContractDetailController::class, 'update']);
        Route::post('/contracts/{contract}/{action}', [\App\Http\Controllers\Inertia\ContractDetailController::class, 'action'])
            ->whereIn('action', ['send', 'activate', 'complete', 'terminate', 'cancel']);

        // التعاونات — قُصّت من Blade؛ يبقى suggest/offer على Blade حتى يكتمل بديلهما
        Route::get('/collaborations', [\App\Http\Controllers\Inertia\CollaborationsController::class, 'index']);
        Route::post('/collaborations', [\App\Http\Controllers\Inertia\CollaborationsController::class, 'store']);
        Route::get('/collaborations/{collaboration}', [\App\Http\Controllers\Inertia\CollaborationDetailController::class, 'show']);
        Route::post('/collaborations/{collaboration}/{action}', [\App\Http\Controllers\Inertia\CollaborationDetailController::class, 'action'])
            ->whereIn('action', ['approve', 'request-revision', 'complete', 'cancel', 'issue-contract', 'create-payout']);

        // المستحقات — قُصّت من Blade؛ الإنشاء يعيد استخدام PayoutWorkflowService نفسه
        // الفواتير — الطرف الآخر من الدفتر: كانت المالية مستحقاتٍ بلا مطالبة
        Route::get('/invoices', [\App\Http\Controllers\Inertia\InvoicesController::class, 'index']);
        Route::post('/invoices', [\App\Http\Controllers\Inertia\InvoicesController::class, 'store']);
        Route::get('/invoices/{invoice}', [\App\Http\Controllers\Inertia\InvoicesController::class, 'show']);
        Route::post('/invoices/{invoice}/update', [\App\Http\Controllers\Inertia\InvoicesController::class, 'update']);
        Route::post('/invoices/{invoice}/issue', [\App\Http\Controllers\Inertia\InvoicesController::class, 'issue']);
        Route::post('/invoices/{invoice}/pay', [\App\Http\Controllers\Inertia\InvoicesController::class, 'pay']);
        Route::post('/invoices/{invoice}/cancel', [\App\Http\Controllers\Inertia\InvoicesController::class, 'cancel']);
        Route::get('/campaigns/{campaign}/invoice-items', [\App\Http\Controllers\Inertia\InvoicesController::class, 'suggestItems']);
        Route::get('/payouts', [\App\Http\Controllers\Inertia\PayoutsController::class, 'index']);
        Route::post('/payouts', [\App\Http\Controllers\Inertia\PayoutsController::class, 'store']);
        Route::get('/payouts/{payout}', [\App\Http\Controllers\Inertia\PayoutDetailController::class, 'show']);
        Route::post('/payouts/{payout}/{action}', [\App\Http\Controllers\Inertia\PayoutDetailController::class, 'action'])
            ->whereIn('action', ['approve', 'schedule', 'send-to-provider', 'mark-paid', 'mark-failed', 'cancel']);

        // المبدعون — قُصّوا من Blade؛ الإضافة تعيد استخدام CreateCreator نفسه
        Route::get('/creators', [\App\Http\Controllers\Inertia\CreatorsController::class, 'index']);
        Route::post('/creators/{creator}/update', [\App\Http\Controllers\Inertia\CreatorsController::class, 'update']);
        Route::post('/creators/{creator}/invite', [\App\Http\Controllers\Inertia\CreatorInvitationController::class, 'store']);
        Route::post('/creator-invitations/{invitation}/resend', [\App\Http\Controllers\Inertia\CreatorInvitationController::class, 'resend']);
        Route::post('/creator-invitations/{invitation}/revoke', [\App\Http\Controllers\Inertia\CreatorInvitationController::class, 'revoke']);
        Route::post('/creators', [\App\Http\Controllers\Inertia\CreatorsController::class, 'store']);
        Route::get('/creators/{creator}', [\App\Http\Controllers\Inertia\CreatorDetailController::class, 'show']);

        // طلبات الخدمة — قُصّت من Blade بتكافؤ كامل (بما فيها التحويل إلى حملة)
        Route::get('/service-requests', [\App\Http\Controllers\Inertia\ServiceRequestsController::class, 'index']);
        Route::get('/service-requests/{serviceRequest}', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'show']);
        // تسجيل طلب نيابةً عن العميل — قبل المسارات ذات المتغيّرات
        Route::post('/service-requests', [\App\Http\Controllers\Inertia\ServiceRequestsController::class, 'store']);
        Route::post('/service-requests/{serviceRequest}/assign', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'assign']);
        Route::post('/service-requests/{serviceRequest}/comment', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'comment']);
        Route::post('/service-requests/{serviceRequest}/convert-campaign', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'convertToCampaign']);
        Route::post('/service-requests/{serviceRequest}/{action}', [\App\Http\Controllers\Inertia\ServiceRequestDetailController::class, 'transition'])
            ->whereIn('action', ['triage', 'start', 'request-info', 'resolve', 'close', 'reopen', 'cancel']);

        // العلامات — قُصّت من Blade (كان index فقط)؛ التفاصيل والإجراءات إضافة
        Route::get('/brands', [\App\Http\Controllers\Inertia\BrandsController::class, 'index']);
        Route::get('/brands/{brand}', [\App\Http\Controllers\Inertia\BrandDetailController::class, 'show']);
        Route::post('/brands/{brand}/{action}', [\App\Http\Controllers\Inertia\BrandDetailController::class, 'action'])
            ->whereIn('action', ['submit', 'start', 'approve', 'request-changes', 'suspend']);

        // المحتوى والموافقات (مرحلة الوكالة) — قُصّ من Blade بتكافؤ إجراءات كامل
        Route::get('/content', [\App\Http\Controllers\Inertia\ContentController::class, 'index']);
        Route::get('/content/{content}', [\App\Http\Controllers\Inertia\ContentDetailController::class, 'show']);
        Route::post('/content/{content}/{action}', [\App\Http\Controllers\Inertia\ContentDetailController::class, 'action'])
            ->whereIn('action', ['start-review', 'send-to-client', 'request-changes', 'reject', 'publish', 'schedule', 'record-proof', 'record-results']);

        // صفحات React لا نسخة Blade لها — كانت متاحة تحت /beta فقط
        Route::get('/my-tasks', [\App\Http\Controllers\Inertia\MyTasksController::class, 'index']);
        Route::get('/shortlisting', [\App\Http\Controllers\Inertia\ShortlistingController::class, 'index']);
        Route::get('/integrations', [\App\Http\Controllers\Inertia\IntegrationsController::class, 'index']);
        Route::get('/team', [\App\Http\Controllers\Inertia\TeamController::class, 'index']);
        Route::get('/settings', [\App\Http\Controllers\Inertia\SettingsController::class, 'index']);
        Route::get('/publishers', [\App\Http\Controllers\Inertia\PublishersController::class, 'index']);
        Route::get('/publishers/{publisher}', [\App\Http\Controllers\Inertia\PublishersController::class, 'show']);
        Route::post('/publishers/{publisher}/save', [\App\Http\Controllers\Inertia\PublishersController::class, 'save']);
        Route::post('/publishers/{publisher}/convert', [\App\Http\Controllers\Inertia\PublishersController::class, 'convert']);
    });

    // مركز المعاينة (تطوير فقط — محجوب في الإنتاج)
    Route::get('/preview', [PreviewCenterController::class, 'index']);
    Route::get('/preview/design-system', [PreviewCenterController::class, 'designSystem']);
    Route::post('/preview/showcase/seed', [PreviewCenterController::class, 'seedShowcase']);
    Route::post('/preview/showcase/reset', [PreviewCenterController::class, 'resetShowcase']);
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
Route::middleware('inertia')->controller(\App\Http\Controllers\Public\BrandSignupController::class)->group(function () {
    Route::get('/register/brand', 'startForm');
    Route::post('/register/brand/start', 'start')->middleware('throttle:join-start');

    Route::get('/register/brand/verify/{reference}', 'verifyEmailForm');
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
    ->controller(\App\Http\Controllers\Inertia\Brand\WorkspaceController::class)->group(function () {
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
    Route::get('/start', [\App\Http\Controllers\Public\StartController::class, 'index']);
    Route::post('/start', [\App\Http\Controllers\Public\StartController::class, 'begin'])
        ->middleware('throttle:join-start');
});
