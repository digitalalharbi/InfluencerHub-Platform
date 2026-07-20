<?php
namespace App\Http\Middleware;
use App\Domain\CRM\Models\{Client, ClientMember};
use App\Domain\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * بوابة العميل: تحلّ العميل النشِط للمستخدم من عضوياته، تضبط سياق المستأجر منه،
 * وتفرض العزل. عضوية غير نشطة (invited/suspended/revoked) تمنع الوصول (fail-closed).
 */
class EnsureClientMember {
    public function handle(Request $request, Closure $next) {
        $user = $request->user();
        if (! $user) return redirect('/client/login');

        // البحث عن العضوية يسبق معرفة المستأجر، فيلزمه تجاوز — يُغلق بعده حتمًا
        [$memberships, $active, $client] = TenantContext::withBypass(function () use ($user, $request) {
            $memberships = ClientMember::where('user_id', $user->id)->where('status', 'active')->get();
            // العميل النشِط: من الجلسة إن كان ضمن عضويات فعّالة، وإلا الأول
            $activeId = $request->session()->get('active_client_id');
            $active = $memberships->firstWhere('client_id', $activeId) ?? $memberships->first();

            return [$memberships, $active, $active ? Client::withoutGlobalScopes()->find($active->client_id) : null];
        });

        if (! $active || ! $client) { abort(403, 'لا توجد عضوية عميل فعّالة لحسابك.'); }

        $request->session()->put('active_client_id', $active->client_id); // ربط الجلسة بالعميل النشِط
        TenantContext::set($client->tenant_id);
        $request->attributes->set('activeClient', $client);
        $request->attributes->set('clientMembership', $active);
        // عدّاد الإشعارات غير المقروءة (السياق مضبوط على مستأجر العميل)
        $unread = \App\Domain\Communications\Models\Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count();
        // كل العضويات النشطة (للمبدّل)
        //
        // ⚠️ هنا كان أخطر عيب في العزل: `reset()` بعد هذا التجاوز كان يمسح
        // سياق المستأجر المضبوط أعلاه — فيصل الطلب إلى المتحكّم **بلا سياق**.
        // ولهذا كان كل متحكّم بوابة يُعيد ضبطه بنفسه (157 موضعًا) تعويضًا عن
        // وسيط يهدم ما يبنيه. `withBypass` يستعيد ما كان بدل أن يُفرغه.
        $allClients = TenantContext::withBypass(
            fn () => Client::withoutGlobalScopes()->whereIn('id', $memberships->pluck('client_id'))->get()
        );
        view()->share(['activeClient' => $client, 'clientMembership' => $active, 'myClients' => $allClients, 'clientUnread' => $unread]);
        return $next($request);
    }
}
