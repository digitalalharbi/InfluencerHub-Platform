<?php
namespace App\Http\Middleware;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Portal\ClientPortalContextResolver;
use Closure;
use Illuminate\Http\Request;

/**
 * بوابة العميل: تحلّ العميل النشِط للمستخدم من عضوياته، تضبط سياق المستأجر منه،
 * وتفرض العزل. عضوية غير نشطة (invited/suspended/revoked) تمنع الوصول (fail-closed).
 *
 * الحلّ نفسه في ClientPortalContextResolver — مصدر واحد تشاركه معاينة مالك المنصّة،
 * فلا يتباعد سياق المعايِن عن سياق المستخدم الحقيقي. الفرق الوحيد: هنا نكتب الجلسة
 * (الوضع العاديّ)، والمعاينة لا تكتبها.
 */
class EnsureClientMember {
    public function handle(Request $request, Closure $next) {
        // معاينة مالك المنصّة للقراءة فقط — تخطّي الحلّ العادي (PortalPreview ضبط كل شيء).
        if ($request->attributes->get('platform_preview')) { return $next($request); }

        $user = $request->user();
        if (! $user) return redirect('/client/login');

        $ctx = app(ClientPortalContextResolver::class)->resolve($user, $request->session()->get('active_client_id'), false);
        if ($ctx === null) { abort(403, 'لا توجد عضوية عميل فعّالة لحسابك.'); }

        $request->session()->put($ctx->sessionKey, $ctx->sessionValue); // ربط الجلسة بالعميل النشِط
        TenantContext::set($ctx->tenantId);
        foreach ($ctx->attributes as $k => $v) { $request->attributes->set($k, $v); }
        view()->share($ctx->share);
        return $next($request);
    }
}
