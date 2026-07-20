<?php

namespace App\Http\Middleware;

use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Http\MountPrefix;
use App\Support\Navigation\NavigationBadges;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * طبقة الربط بين Laravel وReact عبر Inertia.
 * تشارك فقط بيانات العرض العامة (المستخدم/مساحة العمل/الفلاش/العدّادات) — لا منطق أعمال.
 * الصلاحيات تبقى في Policies/Middleware؛ لا يُعتمد على إخفاء الأزرار.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'inertia';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            'workspace' => fn () => $this->workspaceName(),
            'showcase' => fn () => $this->isShowcase(),
            'nav' => fn () => [
                'badges' => $request->user() ? NavigationBadges::all() : [],
                'can' => $this->navCapabilities($request),
            ],
            'flash' => [
                'ok' => fn () => $request->session()->get('ok'),
                'error' => fn () => $request->session()->get('error'),
                // رمز دعوة بوابة العميل — يُعرض مرة واحدة بعد الإنشاء ثم يزول مع الفلاش
                'inviteToken' => fn () => $request->session()->get('invite_token'),
                // ورابط دعوة صانع المحتوى — الرمز لا يُخزَّن خامًا فلا سبيل لعرضه لاحقًا
                'invitation_link' => fn () => $request->session()->get('invitation_link'),
            ],
            'locale' => app()->getLocale(),
            'dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'base' => MountPrefix::for($request),
        ]);
    }


    /**
     * قدرات عناصر القائمة للمستخدم الحالي (تُصفّي عناصر nav ذات `can`).
     * تُحسب من دور الوكالة؛ للبوابات الأخرى تبقى false (تستخدم قوائمها الخاصة).
     * @return array<string,bool>
     */
    private function navCapabilities(Request $request): array
    {
        try {
            $user = $request->user();
            $oid = TenantContext::organizationId();
            if (! $user || ! $oid) return ['reviews' => false, 'admin' => false];
            $role = $user->roleIn($oid);
            return [
                'reviews' => CrmAbilities::can($role, CrmAbilities::WRITE),
                'admin' => CrmAbilities::can($role, CrmAbilities::MANAGE_PORTAL),
            ];
        } catch (\Throwable) {
            return ['reviews' => false, 'admin' => false];
        }
    }

    private function workspaceName(): ?string
    {
        try {
            $oid = TenantContext::organizationId();
            if ($oid) {
                return Organization::withoutGlobalScopes()->find($oid)?->name;
            }
        } catch (\Throwable) {
            // ignore
        }
        return null;
    }

    private function isShowcase(): bool
    {
        try {
            $oid = TenantContext::organizationId();
            if (! $oid) return false;
            $org = Organization::withoutGlobalScopes()->find($oid);
            return $org?->tenant?->slug === 'showcase';
        } catch (\Throwable) {
            return false;
        }
    }
}
