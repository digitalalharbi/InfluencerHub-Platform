<?php
namespace App\Http\Middleware;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Portal\CreatorPortalContextResolver;
use Closure;
use Illuminate\Http\Request;

/**
 * بوابة المبدع: تتأكد أن المستخدم المسجَّل له ملف مبدع مؤهَّل، وتضبط سياق المستأجر
 * منه، وتشارك $creator. المبدع يصل لملفه فقط (منع IDOR على مستوى الحل نفسه).
 *
 * الحلّ + قاعدة الأهلية القانونية الوحيدة (fail-closed: ملف + مؤسسة + بوّابة مفعّلة)
 * في CreatorPortalContextResolver — مصدر واحد تشاركه المعاينة.
 */
class EnsureCreator {
    public function handle(Request $request, Closure $next) {
        // معاينة مالك المنصّة للقراءة فقط — تخطّي الحلّ العادي (PortalPreview ضبط كل شيء).
        if ($request->attributes->get('platform_preview')) { return $next($request); }

        $user = $request->user();
        if (! $user) return redirect('/creator/login');

        $ctx = app(CreatorPortalContextResolver::class)->resolve($user, null, false);
        if ($ctx === null) { abort(403, 'بوابة المبدع غير متاحة لحسابك. تواصل مع الوكالة.'); }

        TenantContext::set($ctx->tenantId);
        foreach ($ctx->attributes as $k => $v) { $request->attributes->set($k, $v); }
        view()->share($ctx->share);
        return $next($request);
    }
}
