<?php

namespace App\Http\Middleware;

use App\Domain\Partners\Models\{ExternalAgency, ExternalAgencyMember};
use App\Domain\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * بوابة الشريك: تحلّ الوكالة الخارجية النشِطة للمستخدم من عضوياته، وتفرض:
 * (1) عضوية شريك نشطة، و(2) أن تكون الوكالة نفسها معتمدة (approved) — وإلا fail-closed.
 */
class EnsurePartnerMember
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) return redirect('/partner/login');

        // البحث عن العضوية يسبق معرفة المستأجر، فيلزمه تجاوز — يُغلق بعده حتمًا
        [$memberships, $active, $agency] = TenantContext::withBypass(function () use ($user, $request) {
            $memberships = ExternalAgencyMember::where('user_id', $user->id)->where('status', 'active')->get();
            $activeId = $request->session()->get('active_agency_id');
            $active = $memberships->firstWhere('external_agency_id', $activeId) ?? $memberships->first();

            return [$memberships, $active, $active ? ExternalAgency::withoutGlobalScopes()->find($active->external_agency_id) : null];
        });

        // fail-closed: عضوية نشطة + وكالة معتمدة (لا وصول لوكالة معلّقة/مؤرشفة)
        if (! $active || ! $agency) { abort(403, 'لا توجد عضوية شريك فعّالة لحسابك.'); }
        if ($agency->status !== 'approved') { abort(403, 'الوكالة الشريكة غير معتمدة أو معلّقة.'); }

        $request->session()->put('active_agency_id', $agency->id);
        TenantContext::set($agency->tenant_id);
        $request->attributes->set('activeAgency', $agency);
        $request->attributes->set('partnerMembership', $active);

        // كان `reset()` هنا يمسح سياق المستأجر المضبوط أعلاه، فيصل الطلب
        // للمتحكّم بلا سياق — نفس عيب بوابة العميل.
        $allAgencies = TenantContext::withBypass(
            fn () => ExternalAgency::withoutGlobalScopes()->whereIn('id', $memberships->pluck('external_agency_id'))->get()
        );
        view()->share(['activeAgency' => $agency, 'partnerMembership' => $active, 'myAgencies' => $allAgencies]);
        return $next($request);
    }
}
