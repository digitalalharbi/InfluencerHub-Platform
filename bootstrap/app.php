<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: SymfonyRequest::HEADER_X_FORWARDED_FOR
            | SymfonyRequest::HEADER_X_FORWARDED_HOST
            | SymfonyRequest::HEADER_X_FORWARDED_PORT
            | SymfonyRequest::HEADER_X_FORWARDED_PROTO);
        $middleware->alias([
            'tenant' => \App\Domain\Tenancy\Support\SetTenantContext::class,
            'creator' => \App\Http\Middleware\EnsureCreator::class,
            'client_member' => \App\Http\Middleware\EnsureClientMember::class,
            'agency_member' => \App\Http\Middleware\EnsureAgencyMember::class,
            'partner_member' => \App\Http\Middleware\EnsurePartnerMember::class,
            'brand_member' => \App\Http\Middleware\EnsureBrandMember::class,
            'system_admin' => \App\Http\Middleware\EnsureSystemAdmin::class,
            'platform_owner' => \App\Http\Middleware\EnsurePlatformOwner::class,
            'platform_preview' => \App\Http\Middleware\PortalPreview::class,
            'inertia' => \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // حارس معاينة عالميّ (§P3-hardening §4): أيّ طلب غير آمن يحمل منحة معاينة ⇒ 403
        // قبل أي تحوّر — يغطّي مسارات الخروج/التبديل الواقعة خارج مجموعات البوّابات.
        $middleware->web(append: [\App\Http\Middleware\PlatformPreviewGuard::class]);

        // يجب أن يردّ الحارس **قبل** SubstituteBindings كي يكون الردّ 403 قبل أي ربط
        // نموذج أو تحوّر (لا 404 يسبق الحظر) — الثابت: «403 قبل التحوّر» يتحقّق فعليًّا.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\PlatformPreviewGuard::class,
        );

        // حرِج: يجب أن يُضبط سياق المستأجر قبل SubstituteBindings، وإلا فإن
        // route-model binding يعمل بلا سياق → TenantScope يُغلق (fail-closed) حتى للمالك.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Domain\Tenancy\Support\SetTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'انتهت الجلسة. حدّث الصفحة ثم أعد المحاولة.', 'error' => 'session_expired'], 419);
            }

            if ($request->header('X-Inertia')) {
                return redirect('/session-expired');
            }

            return response()->view('errors.419', [], 419);
        });
        // استجابات JSON موحّدة لأخطاء API
        $exceptions->render(function (\App\Domain\Billing\Exceptions\EntitlementLimitExceeded $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'تم بلوغ حد الخطة', 'error' => 'entitlement_limit'], 422);
            }
        });
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'غير مصرّح لك بهذا الإجراء', 'error' => 'forbidden'], 403);
            }
        });
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'السجل أو المسار غير موجود', 'error' => 'not_found'], 404);
            }
        });
    })->create();
