<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Support\PlatformCapabilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوّابة «مالك المنصّة» — تحرس مساحة /platform. لا تُدخل إلّا من يملك قدرة
 * platform.owner (المرساة: is_system_admin). ليست بابًا خلفيًّا: تمرّ عبر مستخدم
 * مُصادَق، ثم فحص قدرة صريح، ثم تدقيق (§11). لا تضبط سياق مستأجر — المالك عابر
 * للمستأجرين ويعمل بـTenantContext::withBypass داخل المتحكّمات (§1).
 */
class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(PlatformCapabilities::isOwner($user), 403);

        return $next($request);
    }
}
