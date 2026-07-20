<?php

namespace App\Http\Middleware;

use App\Domain\CRM\Models\Brand;
use App\Domain\CRM\Models\BrandWorkspaceRelationship;
use App\Domain\Identity\Enums\Role;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * بوّابة مساحة العلامة.
 *
 * تحلّ مؤسّسة المستخدم داخل مستأجر من نوع `brand`، وتضبط سياق الطلب منها،
 * وتُحضر العلامة المملوكة.
 *
 * ## قيدان لا يكفي أحدهما
 *
 * 1. **المستأجر نوعه `brand`.** عضويّةٌ في مستأجر وكالة لا تفتح هذه البوّابة
 *    ولو كان دورها `brand_admin`.
 * 2. **وللمستأجر علاقة ملكية حيّة.** مستأجرٌ من نوع علامة بلا صفّ `owner` هو
 *    نصف تزويد — لا يُفتح، بل يُردّ صراحةً.
 *
 * والسياق يُضبط **مرّة واحدة** قرب النهاية، ولا يُعاد ضبطه ولا يُمحى داخل
 * الوسيط: ذلك بالضبط ما كان يهدم سياق الطلب فيصل المتحكّم بلا مستأجر.
 */
class EnsureBrandMember
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        // البحث عن العضوية يسبق معرفة المستأجر، فيلزمه تجاوز — يُغلق حتمًا بعده
        [$membership, $tenant, $brand, $relationship] = TenantContext::withBypass(function () use ($user) {
            $memberships = OrganizationMembership::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereIn('role', [Role::BrandAdmin->value, Role::BrandMember->value])
                ->get();

            foreach ($memberships as $candidate) {
                $tenant = Tenant::withoutGlobalScopes()->find($candidate->tenant_id);

                if (! $tenant || $tenant->type !== Tenant::TYPE_BRAND) {
                    continue;
                }

                $relationship = BrandWorkspaceRelationship::where('tenant_id', $tenant->id)
                    ->where('relationship_type', BrandWorkspaceRelationship::OWNER)
                    ->where('status', 'active')
                    ->whereNull('ended_at')
                    ->first();

                if (! $relationship) {
                    continue;
                }

                $brand = Brand::withoutGlobalScopes()->find($relationship->brand_id);

                if ($brand) {
                    return [$candidate, $tenant, $brand, $relationship];
                }
            }

            return [null, null, null, null];
        });

        if (! $membership || ! $brand) {
            abort(403, 'هذه المساحة لأصحاب العلامات. لا توجد علامة مرتبطة بحسابك.');
        }

        TenantContext::set($tenant->id, $membership->organization_id, $membership->workspace_id);

        $request->attributes->set('brand', $brand);
        $request->attributes->set('brandMembership', $membership);
        $request->attributes->set('brandRelationship', $relationship);

        return $next($request);
    }
}
