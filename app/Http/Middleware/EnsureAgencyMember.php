<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * مساحة الوكالة `/app` تتطلّب عضوية مؤسسة نشطة.
 *
 * لماذا: `SetTenantContext` يضبط السياق إن وُجدت عضوية ولا يمنع من لا عضوية له،
 * فكان عضو بوابة العميل أو المبدع يفتح لوحة الوكالة بحالة 200. البيانات كانت
 * فارغة (TenantScope مغلق افتراضيًا) لكن الحدّ كان مخترقًا: واجهة ليست له،
 * وقائمة ليست له. الحماية هنا صريحة ومغلقة افتراضيًا لا معتمدة على إخفاء الروابط.
 *
 * مدير النظام يمرّ لأنه يتجاوز المستأجر بتصميم مُدقَّق (يُسجَّل تجاوزه).
 */
class EnsureAgencyMember
{
    /**
     * أدوار البوابات — تُمنح عضوية مؤسسة ليعمل نطاق المستأجر، لا لتفتح الوكالة.
     * أي دور جديد لبوابة يُضاف هنا، وإلا فُتحت له لوحة الوكالة صامتةً.
     */
    private const PORTAL_ROLES = ['influencer', 'ugc_creator', 'influencer_and_ugc'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum') ?? $request->user();
        abort_unless($user, 401);

        // مدير النظام يتجاوز نطاق المستأجر ليُشرف — والإشراف قراءة.
        //
        // التجاوز كان يمتدّ إلى الكتابة أيضًا، فيستطيع مدير النظام إرسال عقد
        // أو اعتماد مستحقّ داخل مساحة مستأجر لا عضوية له فيها. وقع فعليًّا:
        // العقد 298 (المستأجر 14) أُرسل بالفاعل 2، وهو مدير نظام عضويته في
        // المستأجر 1 وحده — يشهد عليه سجلّ التدقيق نفسه.
        //
        // القرار الأمني المكتوب: إشراف للقراءة فقط، بلا انتحال هوية ولا إجراءات
        // هدّامة. هنا يُنفَّذ: الطرق الآمنة تمرّ، والكتابة تُردّ بسببها وبديلها.
        if ($user->is_system_admin) {
            abort_unless(
                $request->isMethodSafe(),
                403,
                'إشراف مدير النظام على مساحات المستأجرين للقراءة فقط. '
                . 'لتنفيذ إجراء داخل هذه المساحة، اطلب من عضو في الوكالة تنفيذه.',
            );

            return $next($request);
        }

        // السياق يُضبط من العضوية النشطة؛ غيابه يعني أن لا عضوية مؤسسة له
        abort_unless(TenantContext::check(), 403, 'هذه المساحة لأعضاء الوكالة.');

        // ووجود العضوية وحده لا يكفي: صانع المحتوى يُمنح عضوية بدور بوابته
        // (`influencer` / `ugc_creator`) عند تفعيل حسابه، فكان يمرّ من هنا
        // ويرى لوحة الوكالة كاملةً. الدور — لا مجرّد العضوية — هو الحدّ.
        $orgId = TenantContext::organizationId();
        $role = $orgId ? $request->user()->roleIn($orgId) : null;
        abort_if(in_array($role, self::PORTAL_ROLES, true), 403,
            'هذه المساحة لأعضاء الوكالة. حسابك حساب بوابة — افتح بوابتك من /creator.');

        return $next($request);
    }
}
