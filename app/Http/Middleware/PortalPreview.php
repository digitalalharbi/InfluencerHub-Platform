<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\PlatformPortalEligibilityService;
use App\Domain\Platform\Support\{PlatformCapabilities, PlatformPreviewToken};
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Portal\{ClientPortalContextResolver, CreatorPortalContextResolver, PartnerPortalContextResolver};
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * معاينة بوّابة للقراءة فقط لمالك المنصّة (§P3). يُشغَّل قبل حارس البوّابة. القراءة فقط
 * وتحقّق المالك/التوقيع/الانتهاء يفرضهما PlatformPreviewGuard عالميًّا **قبل** أي طلب
 * (حتّى تسجيل الخروج خارج مجموعات البوّابات). هنا نُكمل ما يخصّ البوّابة:
 *   - مطابقة البوّابة + الرباعية الدقيقة عبر isContextEligible (لا «ينتمي لمكان ما»).
 *   - **تكافؤ سياق حقيقي**: نستعمل نفس حلّالات الحارس (Client/Creator/Partner) بوضع
 *     مطابقة-دقيقة، فيرى المعايِن ما يراه المستخدم الحقيقي (activeClient/clientMembership/
 *     myClients/clientUnread…)، بلا كتابة جلسة (لا active_client_id/active_agency_id).
 *   - **هوية مزدوجة مُحكمة النطاق**: نحفظ المالك ثم Auth::setUser(الهدف) داخل try، ونعيد
 *     المالك في finally — فلا يتسرّب الهدف إلى فكّ المكدّس/التدقيق/المُنهيات.
 *   - يضع سمة platform_preview فيتخطّى الحارس حلَّه العادي (الذي يكتب الجلسة).
 * التدقيق (open/exit) في PlatformPreviewController؛ لا نُدقّق كل طلب معاينة (ضجيج).
 */
class PortalPreview
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $token = $request->query('_pv');
        if (! is_string($token) || $token === '') {
            return $next($request);   // ليست معاينة — سلوك عاديّ تمامًا
        }

        $owner = $request->user();
        abort_unless(PlatformCapabilities::isOwner($owner), 403);

        // يُفضَّل إعادة استعمال ما تحقّق منه الحارس العالميّ؛ وإلا نتحقّق هنا (دفاع في العمق).
        $claims = $request->attributes->get('_pv_claims') ?? PlatformPreviewToken::verify($token, now()->timestamp);
        abort_if($claims === null, 403, 'منحة معاينة غير صالحة أو منتهية.');
        abort_unless($claims['owner'] === (int) $owner->id, 403);
        abort_unless($claims['portal'] === $portal, 403);

        // القراءة فقط — الحارس العالميّ فرضها، ونؤكّدها هنا أيضًا قبل أي إعداد.
        abort_unless($request->isMethodSafe(), 403, 'معاينة مالك المنصّة للقراءة فقط.');

        // تحقّق الرباعية الدقيقة (مستخدم نشِط + رابط الكيان + مطابقة مستأجر الكيان المرجعيّ).
        $svc = app(PlatformPortalEligibilityService::class);
        abort_unless($svc->isContextEligible($claims['user'], $claims['tenant'], $portal, $claims['entity'], $claims['org']), 403, 'سياق المعاينة غير مؤهَّل.');

        $target = User::withoutGlobalScopes()->find($claims['user']);
        abort_if($target === null || ! $target->is_active, 403);

        // هوية مزدوجة تُحفظ قبل الاستبدال.
        $request->attributes->set('platform_owner_id', (int) $owner->id);
        $request->attributes->set('preview', [
            'userId' => $claims['user'], 'tenantId' => $claims['tenant'], 'portal' => $portal,
            'entityId' => $claims['entity'], 'organizationId' => $claims['org'], 'jti' => $claims['jti'], 'token' => $token,
        ]);

        // تكافؤ السياق عبر نفس حلّال الحارس (مطابقة-دقيقة) — بلا كتابة جلسة.
        $resolver = match ($portal) {
            'client' => app(ClientPortalContextResolver::class),
            'creator' => app(CreatorPortalContextResolver::class),
            'partner' => app(PartnerPortalContextResolver::class),
            default => null,   // الوكالة: السياق + roleIn(الهدف) يكفيان
        };

        TenantContext::bypass(false);
        if ($resolver !== null) {
            $ctx = $resolver->resolve($target, $claims['entity'], true);   // exact=true: لا سقوط لكيان آخر
            abort_if($ctx === null, 403, 'سياق المعاينة غير مؤهَّل.');
            TenantContext::set($ctx->tenantId);
            foreach ($ctx->attributes as $k => $v) { $request->attributes->set($k, $v); }
            view()->share($ctx->share);   // بيانات Blade نفسها (myClients/clientUnread…) — بلا جلسة
        } else {
            TenantContext::set($claims['tenant'], $claims['org']);   // الوكالة
        }

        // إشارة للحرّاس بتخطّي حلّهم العادي (الذي يكتب الجلسة).
        $request->attributes->set('platform_preview', true);

        // الفاعل الظاهر للمتحكّمات = الهدف (عرض حقيقي)، ثم يُستعاد المالك حتمًا في finally
        // فلا يراه فكّ المكدّس/التدقيق/المُنهيات هدفًا (§P3-hardening §2).
        Auth::setUser($target);
        try {
            return $next($request);
        } finally {
            Auth::setUser($owner);
        }
    }
}
