<?php

namespace App\Http\Controllers\Public;

use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Enums\Role;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use App\Support\Content\GatewayContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * الموقع العام — أوّل ما يراه الزائر.
 *
 * قبل هذا كان الجذر `/` يحوّل مباشرة إلى `/app`، فيهبط زائر لا يملك حسابًا
 * داخل لوحة تشغيل داخلية. الزائر يحتاج أن يفهم المنتَج ثم يختار طريقه.
 *
 * المصادَق يُحوَّل إلى بوابته: لا معنى لعرض صفحة تسويقية لمن يعمل بالفعل.
 */
class SiteController extends Controller
{
    /** الجذر بوّابة المنتَج: تعريف واختيار مسار داخل شاشة واحدة. */
    public function home(Request $r): Response|RedirectResponse
    {
        if ($redirect = $this->portalRedirect($r)) {
            return $redirect;
        }

        return Inertia::render('Public/Gateway', GatewayContent::props());
    }

    /**
     * `/register` و`/register/account-type` — تحويل دائم إلى `/start`.
     *
     * كانا يعرضان اختيار نوع الحساب بنسخةٍ ثانية من النصّ والروابط، وأشار
     * أحدهما إلى `/register/client` وهو مسار لا يُنشئ مساحة علامة. والاختيار
     * صار في `/start` وحده.
     *
     * والمعاملات تُحمَل كما هي: رابطٌ إعلاني يحمل `?plan=pro&referral=x` يصل
     * إلى نهاية التسجيل ولا يُفقَد مصدره.
     */
    public function legacyRegister(Request $r): RedirectResponse
    {
        $query = collect($r->query())
            ->only(StartController::CARRIED)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->all();

        return redirect('/start'.($query === [] ? '' : '?'.http_build_query($query)), 301);
    }

    /**
     * وجهةُ من سجّل دخوله — مساحته لا صفحة تسجيل.
     *
     * عامّة لأن `/start` يحتاجها أيضًا: عرضُ نموذج تسجيل على من يعمل بالفعل
     * خطأ، ونسخُ المنطق في متحكّمين يُنتج وجهتين تفترقان.
     *
     * الترتيب مقصود ومحروس باختبار: **العلامة قبل الوكالة**. مالك العلامة
     * عضوٌ في مؤسّسة أيضًا، فلو فُحصت العضوية أوّلًا لَذهب إلى `/app`.
     */
    public function portalRedirect(Request $r): ?RedirectResponse
    {
        $user = $r->user();
        if (! $user) {
            return null;
        }

        // على الجذر لا سياق مستأجر بعد، وTenantScope مغلق افتراضيًّا: بلا تجاوز
        // مؤقّت تعود العضويات فارغة فيُرسَل عضو الوكالة إلى بوابة العميل.
        [$isBrandMember, $isAgencyMember, $isCreator] = TenantContext::withBypass(fn () => [
            // مالك العلامة عضوٌ في مؤسّسة أيضًا، فلا يكفي وجود العضوية للتمييز:
            // بدون هذا الفحص كان يُرسَل إلى `/app` — مساحة الوكالة — لا إلى مساحته.
            $user->memberships()->where('status', 'active')
                ->whereIn('role', [
                    Role::BrandAdmin->value,
                    Role::BrandMember->value,
                ])
                ->whereIn('tenant_id', Tenant::query()
                    ->where('type', Tenant::TYPE_BRAND)->select('id'))
                ->exists(),
            $user->memberships()->where('status', 'active')->exists(),
            Creator::where('user_id', $user->id)->exists(),
        ]);

        return match (true) {
            $user->is_system_admin => redirect('/admin'),
            $isBrandMember => redirect('/brand'),
            $isAgencyMember => redirect('/app'),
            $isCreator => redirect('/creator'),
            default => redirect('/client'),
        };
    }
}
