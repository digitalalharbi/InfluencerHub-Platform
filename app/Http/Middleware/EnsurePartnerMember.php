<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Portal\PartnerPortalContextResolver;
use Closure;
use Illuminate\Http\Request;

/**
 * بوابة الشريك: تحلّ الوكالة الخارجية النشِطة للمستخدم من عضوياته، وتفرض:
 * (1) عضوية شريك نشطة، و(2) أن تكون الوكالة نفسها معتمدة (approved) — وإلا fail-closed.
 *
 * الحلّ في PartnerPortalContextResolver — مصدر واحد تشاركه المعاينة.
 */
class EnsurePartnerMember
{
    public function handle(Request $request, Closure $next)
    {
        // معاينة مالك المنصّة للقراءة فقط — تخطّي الحلّ العادي (PortalPreview ضبط كل شيء).
        if ($request->attributes->get('platform_preview')) { return $next($request); }

        $user = $request->user();
        if (! $user) return redirect('/partner/login');

        $ctx = app(PartnerPortalContextResolver::class)->resolve($user, $request->session()->get('active_agency_id'), false);
        if ($ctx === null) { abort(403, 'لا توجد عضوية شريك فعّالة معتمدة لحسابك.'); }

        $request->session()->put($ctx->sessionKey, $ctx->sessionValue);
        TenantContext::set($ctx->tenantId);
        foreach ($ctx->attributes as $k => $v) { $request->attributes->set($k, $v); }
        view()->share($ctx->share);
        return $next($request);
    }
}
