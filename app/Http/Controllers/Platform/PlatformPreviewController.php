<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\PlatformPortalEligibilityService;
use App\Domain\Platform\Support\{PlatformCapabilities, PlatformPreviewToken};
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * بدء/إنهاء معاينة بوّابة للقراءة فقط (§P3). المالك يختار **سياقًا بعينه** (مستخدم +
 * كيان + مؤسسة عند اللزوم) من صفحة تفصيل المستأجر؛ نتحقّق من الرباعية الدقيقة كما
 * اختارها — **بلا first() نيابةً عنه** (§P3-hardening §1) — ثم نُصدر منحة موقَّعة قصيرة
 * العمر ونحوّله إلى جذر البوّابة حاملًا المنحة. إن لم تكن الرباعية مؤهَّلة → 404
 * NOT_AVAILABLE_NO_ELIGIBLE_USER. يُدقَّق الفتح/الإنهاء بالمالك فاعلًا والهدف هويةً.
 *
 * دلالة الإنهاء (موثَّقة صراحةً): **تنقّل فقط**. المنحة تبقى صالحة تشفيريًّا حتّى انتهائها
 * (١٥ دقيقة)؛ لا إبطال خادميّ في P3. هذا مقبول لأن المعاينة للقراءة فقط بالكامل
 * (لا تحوّر ممكن بها). الإبطال الفوري للمنح يأتي مع مخزن المنح في P4/P5.
 */
class PlatformPreviewController extends Controller
{
    private const ROOTS = ['agency' => '/app', 'client' => '/client', 'creator' => '/creator', 'partner' => '/partner'];

    public function start(Request $r, PlatformPortalEligibilityService $svc, int $tenant, string $portal, int $user, int $entity): RedirectResponse
    {
        abort_unless(isset(self::ROOTS[$portal]), 404);
        $owner = $r->user();

        $orgRaw = $r->query('organization');
        $org = is_numeric($orgRaw) ? (int) $orgRaw : null;

        $target = User::withoutGlobalScopes()->find($user);
        abort_if($target === null || ! $target->is_active, 404, 'NOT_AVAILABLE_NO_ELIGIBLE_USER');

        // الرباعية الدقيقة كما اختارها المالك (مستخدم/مستأجر/بوّابة/كيان/مؤسسة) — لا اختيار
        // نيابةً عنه: مستخدم بعميلين أو بمؤسستين يفتح كلُّ زرّ سياقَه الصحيح لا الأوّل.
        abort_unless($svc->isContextEligible($user, $tenant, $portal, $entity, $org), 404, 'NOT_AVAILABLE_NO_ELIGIBLE_USER');

        $token = PlatformPreviewToken::issue((int) $owner->id, $user, $tenant, $portal, $entity, $org, now()->timestamp);

        AuditLogger::log('platform.preview.start', $target,
            ['tenant' => $tenant, 'portal' => $portal, 'entity' => $entity, 'organization' => $org, 'preview_user_id' => $user],
            $tenant, (int) $owner->id);   // الفاعل = المالك، والهدف في الميتاداتا

        return redirect(self::ROOTS[$portal] . '?_pv=' . $token);
    }

    public function exit(Request $r): RedirectResponse
    {
        $claims = PlatformPreviewToken::verify((string) $r->query('token', ''), now()->timestamp);
        $owner = $r->user();

        // لا نُدقّق الخروج إلا بمنحة صالحة تخصّ **المالك الحاليّ نفسه** (§P3-hardening: تحقّق
        // owner قبل كتابة تدقيق الخروج) — فلا يُكتب خروج بمنحة غريب أو منتهية.
        if ($claims !== null && PlatformCapabilities::isOwner($owner) && $claims['owner'] === (int) $owner->id) {
            AuditLogger::log('platform.preview.exit', null,
                ['tenant' => $claims['tenant'], 'portal' => $claims['portal'], 'preview_user_id' => $claims['user'], 'jti' => $claims['jti']],
                $claims['tenant'], (int) $owner->id);
        }

        return redirect('/platform');
    }
}
