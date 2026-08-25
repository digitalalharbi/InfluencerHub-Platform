<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Support\{PlatformCapabilities, PlatformPreviewToken};
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حارس معاينة عالميّ (§P3-hardening §4). يعمل على مجموعة `web` كلّها — لا على مجموعات
 * البوّابات وحدها — فيغطّي المسارات القابلة للوصول من داخل معاينة نشطة والواقعة **خارج**
 * تلك المجموعات: تسجيل الخروج (`/logout`، `/creator|client|partner/logout`)، أي POST.
 *
 * الثابت المفروض: **أيّ طلب غير آمن يحمل منحة معاينة صالحة لمالك المنصّة ⇒ 403 قبل أي
 * تنفيذ/تحوّر** — فلا يُسجَّل خروج جلسة المالك ولا تُكتب حالة بسبب زرّ داخل معاينة. جلسة
 * المالك تبقى كما هي (لم يُنفَّذ أي متحكّم). الطلبات الآمنة (GET/HEAD/OPTIONS) تمرّ، ويُخزَّن
 * ما تحقّق منه هنا في `_pv_claims` كي لا يكرّر PortalPreview التحقّق.
 *
 * يعمل فقط حين يوجد `_pv`؛ بغيره سلوك عاديّ تمامًا. لا يضبط سياقًا ولا يستبدل مستخدمًا —
 * ذلك مسؤولية PortalPreview على مسارات البوّابات وحدها.
 */
class PlatformPreviewGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('_pv');
        if (! is_string($token) || $token === '') {
            return $next($request);   // ليست معاينة
        }

        // منحة معاينة موجودة: يجب أن تكون لمالك منصّة مصادَق وصالحة وغير منتهية.
        abort_unless(PlatformCapabilities::isOwner($request->user()), 403);
        $claims = PlatformPreviewToken::verify($token, now()->timestamp);
        abort_if($claims === null, 403, 'منحة معاينة غير صالحة أو منتهية.');
        abort_unless($claims['owner'] === (int) $request->user()->id, 403);

        // القراءة فقط في كل مكان — قبل أي متحكّم/تحوّر (يشمل مسارات الخروج خارج البوّابات).
        abort_unless($request->isMethodSafe(), 403, 'معاينة مالك المنصّة للقراءة فقط — لا إجراءات.');

        // نمرّر ما تحقّقنا منه كي يعيد PortalPreview استعماله بلا تحقّق مكرّر.
        $request->attributes->set('_pv_claims', $claims);

        return $next($request);
    }
}
