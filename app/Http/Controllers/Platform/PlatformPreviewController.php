<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\PlatformPortalEligibilityService;
use App\Domain\Platform\Support\PlatformPreviewToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * بدء/إنهاء معاينة بوّابة للقراءة فقط (§P3). المالك يختار مستخدمًا مؤهَّلًا بعينه؛
 * نتحقّق من سياقه الدقيق، نُصدر منحة موقَّعة قصيرة العمر، ونحوّله إلى جذر البوّابة
 * الحقيقية حاملًا المنحة في الـURL. لا اختلاق هوية: إن لم يوجد سياق مؤهَّل → 404
 * NOT_AVAILABLE_NO_ELIGIBLE_USER. يُدقَّق كلّ فتح وإنهاء بالمالك فاعلًا والهدف هويةً.
 */
class PlatformPreviewController extends Controller
{
    private const ROOTS = ['agency' => '/app', 'client' => '/client', 'creator' => '/creator', 'partner' => '/partner'];

    public function start(Request $r, PlatformPortalEligibilityService $svc, int $tenant, string $portal, int $user): RedirectResponse
    {
        abort_unless(isset(self::ROOTS[$portal]), 404);
        $owner = $r->user();

        $target = User::withoutGlobalScopes()->find($user);
        abort_if($target === null || ! $target->is_active, 404, 'NOT_AVAILABLE_NO_ELIGIBLE_USER');

        // نُقيّد السياق الدقيق من علاقات المستخدم الفعلية لهذه البوّابة/المستأجر.
        $ctx = collect($svc->contextsForUser($target))
            ->first(fn ($c) => $c['portal'] === $portal && $c['tenantId'] === $tenant);
        abort_if($ctx === null, 404, 'NOT_AVAILABLE_NO_ELIGIBLE_USER');

        $token = PlatformPreviewToken::issue((int) $owner->id, $user, $tenant, $portal, (int) $ctx['entityId'], $ctx['organizationId'], now()->timestamp);

        AuditLogger::log('platform.preview.start', $target,
            ['tenant' => $tenant, 'portal' => $portal, 'entity' => (int) $ctx['entityId'], 'preview_user_id' => $user],
            $tenant, (int) $owner->id);   // الفاعل = المالك، والهدف في الميتاداتا

        return redirect(self::ROOTS[$portal] . '?_pv=' . $token);
    }

    public function exit(Request $r): RedirectResponse
    {
        $claims = PlatformPreviewToken::verify((string) $r->query('token', ''), now()->timestamp);
        if ($claims !== null) {
            AuditLogger::log('platform.preview.exit', null,
                ['tenant' => $claims['tenant'], 'portal' => $claims['portal'], 'preview_user_id' => $claims['user'], 'jti' => $claims['jti']],
                $claims['tenant'], (int) $r->user()->id);
        }

        return redirect('/platform');
    }
}
