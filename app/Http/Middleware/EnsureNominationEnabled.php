<?php

namespace App\Http\Middleware;

use App\Domain\Nomination\Access\NominationAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حارس الحافّة لميزة «ترشيح المؤثرين» — يفرض قرار {@see NominationAccess} على المسار.
 *
 * الميزة مُطفأة لهذا النطاق أو الدور بلا صلاحية عرض ⇒ 403 قبل أي تنفيذ (يغطّي المسار
 * المباشر وواجهة API والتصدير). لا يحذف بيانات — التعطيل إخفاءٌ فقط. يمرّر القرار في
 * سمات الطلب لإعادة استخدامه في المتحكّم/Inertia بلا حساب مكرّر.
 *
 * الاستخدام: `->middleware('nomination:agency')` (أو client/admin/brand حسب البوّابة).
 */
class EnsureNominationEnabled
{
    public function __construct(private NominationAccess $access) {}

    public function handle(Request $request, Closure $next, string $portal = 'agency'): Response
    {
        $decision = $this->access->decide($request->user(), $portal);
        abort_unless($decision->allowed, 403, 'ميزة ترشيح المؤثرين غير متاحة لهذا السياق.');

        $request->attributes->set('nomination_decision', $decision);

        return $next($request);
    }
}
