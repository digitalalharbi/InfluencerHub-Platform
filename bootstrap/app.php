<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Domain\Tenancy\Support\SetTenantContext::class,
            'creator' => \App\Http\Middleware\EnsureCreator::class,
            'client_member' => \App\Http\Middleware\EnsureClientMember::class,
            'agency_member' => \App\Http\Middleware\EnsureAgencyMember::class,
            'partner_member' => \App\Http\Middleware\EnsurePartnerMember::class,
            'brand_member' => \App\Http\Middleware\EnsureBrandMember::class,
            'system_admin' => \App\Http\Middleware\EnsureSystemAdmin::class,
            'inertia' => \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // حرِج: يجب أن يُضبط سياق المستأجر قبل SubstituteBindings، وإلا فإن
        // route-model binding يعمل بلا سياق → TenantScope يُغلق (fail-closed) حتى للمالك.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Domain\Tenancy\Support\SetTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
