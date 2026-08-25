<?php

namespace App\Http\Middleware;

use App\Domain\CRM\Models\Client;
use App\Domain\Creators\Models\Creator;
use App\Domain\Identity\Models\User;
use App\Domain\Partners\Models\ExternalAgency;
use App\Domain\Platform\Services\PlatformPortalEligibilityService;
use App\Domain\Platform\Support\{PlatformCapabilities, PlatformPreviewToken};
use App\Domain\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * معاينة بوّابة للقراءة فقط لمالك المنصّة (§P3). يُشغَّل قبل حارس البوّابة. عند وجود
 * منحة معاينة موقَّعة صالحة (_pv) لمالك منصّة:
 *   - يتحقّق من التوقيع والانتهاء ومطابقة المالك والبوّابة، ومن الرباعية الدقيقة
 *     عبر isContextEligible (لا «ينتمي لمكان ما في المستأجر»).
 *   - يفرض القراءة فقط (GET/HEAD/OPTIONS) قبل أي شيء.
 *   - يضبط سياق المستأجر **للطلب فقط** (لا كتابة جلسة، لا active_client_id/agency)،
 *     ويطفئ bypass لعزلٍ حقيقي على المستأجر الهدف (متعدّد النوافذ آمن — الحالة في الـURL).
 *   - هوية مزدوجة: platform_owner_id يُحفظ قبل استبدال الفاعل بالهدف (Auth::setUser)،
 *     كي يظهر للمتحكّمات ما يراه المستخدم الهدف فعلًا بينما يبقى المالك هو المدقَّق.
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

        $claims = PlatformPreviewToken::verify($token, now()->timestamp);
        abort_if($claims === null, 403, 'منحة معاينة غير صالحة أو منتهية.');
        abort_unless($claims['owner'] === (int) $owner->id, 403);
        abort_unless($claims['portal'] === $portal, 403);

        // القراءة فقط — قبل أي إعداد أو تنفيذ متحكّم (§9).
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

        // سياق المستأجر للطلب فقط — عزل حقيقي (bypass مطفأ، لا جلسة).
        TenantContext::bypass(false);
        TenantContext::set($claims['tenant'], $claims['org']);

        // خصائص البوّابة التي تقرؤها متحكّماتها — بلا كتابة جلسة.
        TenantContext::withBypass(function () use ($request, $portal, $claims) {
            match ($portal) {
                'client' => $request->attributes->set('activeClient', Client::withoutGlobalScopes()->find($claims['entity'])),
                'creator' => $request->attributes->set('creator', Creator::withoutGlobalScopes()->find($claims['entity'])),
                'partner' => $request->attributes->set('activeAgency', ExternalAgency::withoutGlobalScopes()->find($claims['entity'])),
                default => null,   // الوكالة: السياق + roleIn يكفيان
            };
        });

        // الفاعل الظاهر للمتحكّمات = الهدف (عرض حقيقي)؛ الأصل محفوظ في السمة أعلاه.
        Auth::setUser($target);

        // إشارة للحرّاس بتخطّي حلّهم العادي (الذي يكتب الجلسة).
        $request->attributes->set('platform_preview', true);

        return $next($request);
    }
}
