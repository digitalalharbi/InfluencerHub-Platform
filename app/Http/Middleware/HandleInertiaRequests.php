<?php

namespace App\Http\Middleware;

use App\Domain\AdminPool\Support\CreatorDatabaseAbilities;
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Communications\Models\Notification;
use App\Domain\CRM\Support\CrmAbilities;
use App\Domain\Identity\Models\User;
use App\Domain\Nomination\Access\NominationAccess;
use App\Domain\Platform\Support\PlatformCapabilities;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Brand;
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
            // مالك المنصّة — عَلَم لعرض مدخل /platform في البوّابات العادية (لا يظهر لغيره).
            'isPlatformOwner' => fn () => PlatformCapabilities::isOwner($request->user()),
            'nav' => fn () => [
                'badges' => $request->user() ? NavigationBadges::all() : [],
                'can' => $this->navCapabilities($request),
            ],
            // عدّاد الإشعارات غير المقروءة — لجرس شريط العنوان في كل البوابات
            'unreadNotifications' => fn () => $request->user()
                ? Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count()
                : 0,
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
            // معاينة مالك المنصّة (§P3) — يُشارَك فقط داخل معاينة نشطة. الرمز يمرَّر
            // في الروابط الداخلية (u()) للتنقّل الآمن متعدّد النوافذ، والشريط يعرض الهدف.
            'preview' => function () use ($request) {
                $p = $request->attributes->get('preview');
                if (! is_array($p)) {
                    return null;
                }
                $target = User::withoutGlobalScopes()->find($p['userId']);

                return [
                    'active' => true,
                    'token' => $p['token'],
                    'portal' => $p['portal'],
                    'tenantId' => $p['tenantId'],
                    'targetName' => $target?->name ?? '—',
                    'exitHref' => '/platform/preview/exit?token='.$p['token'],
                ];
            },
            // هوية المنتج القانونية — مصدر واحد لواجهة React (تذييل/دخول/معلومات).
            'brand' => [
                'name' => Brand::name(),
                'tagline' => Brand::tagline(),
                'url' => Brand::url(),
                'domain' => Brand::domain(),
                'infoUrl' => Brand::infoUrl(),
                // مسارات نسبية (نفس المضيف influencerhub.io) — تنقّل بلا تبويب جديد
                'infoPath' => (string) config('influencerhub.info_path', '/info'),
                'privacyPath' => (string) config('influencerhub.privacy_path', '/privacy'),
                'termsPath' => (string) config('influencerhub.terms_path', '/terms'),
                'helpPath' => (string) config('influencerhub.help_path', '/help'),
                'publicEmail' => Brand::publicEmail(),
                'publicPhone' => Brand::publicPhone(),
                'publicPhoneDisplay' => Brand::publicPhoneDisplay(),
                'year' => (int) date('Y'),
            ],
        ]);
    }

    /**
     * قدرات عناصر القائمة للمستخدم الحالي (تُصفّي عناصر nav ذات `can`).
     * تُحسب من دور الوكالة؛ للبوابات الأخرى تبقى false (تستخدم قوائمها الخاصة).
     *
     * @return array<string,bool>
     */
    private function navCapabilities(Request $request): array
    {
        try {
            // أدوات المطوّر (مركز المعاينة) — محكومة بعَلَم صريح config('app.dev_tools')
            // افتراضه false. لا يعتمد على APP_ENV، فيبقى الرابط محجوبًا في الإنتاج حتى
            // لو أُسيء ضبط APP_ENV. يُفعَّل صراحةً في التطوير/الاختبار عبر DEV_TOOLS_ENABLED.
            $dev = (bool) config('app.dev_tools');
            $user = $request->user();
            $oid = TenantContext::organizationId();
            if (! $user || ! $oid) {
                return ['reviews' => false, 'admin' => false, 'dev_tools' => $dev, 'creator_database' => false, 'influencer_nomination' => false];
            }
            $role = $user->roleIn($oid);

            // ترشيح المؤثرين: يُخفى رابطه إذا كانت الميزة مُطفأة لهذا النطاق أو الدور بلا
            // صلاحية عرض — من نفس مصدر القرار الذي يفرضه الخادم (NominationAccess).
            $nomination = app(NominationAccess::class)->canView($user, 'agency');

            // قاعدة المؤثرين: يظهر رابطها فقط إذا كانت المؤسسة مستحقّة (خطة/إضافة/تجاوز)
            // والدور يملك صلاحية العرض — حوكمة مزدوجة تُطابق ما يفرضه المتحكّم في الخادم.
            $cdb = false;
            if (CreatorDatabaseAbilities::can($role, CreatorDatabaseAbilities::VIEW)) {
                $org = TenantContext::withBypass(fn () => Organization::find($oid));
                $cdb = $org !== null && app(EntitlementService::class)->allows($org, 'creator_database.access');
            }

            return [
                'reviews' => CrmAbilities::can($role, CrmAbilities::WRITE),
                'admin' => CrmAbilities::can($role, CrmAbilities::MANAGE_PORTAL),
                'dev_tools' => $dev,
                'creator_database' => $cdb,
                'influencer_nomination' => $nomination,
            ];
        } catch (\Throwable) {
            return ['reviews' => false, 'admin' => false, 'dev_tools' => false, 'creator_database' => false, 'influencer_nomination' => false];
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
            if (! $oid) {
                return false;
            }
            $org = Organization::withoutGlobalScopes()->find($oid);

            return $org?->tenant?->slug === 'showcase';
        } catch (\Throwable) {
            return false;
        }
    }
}
