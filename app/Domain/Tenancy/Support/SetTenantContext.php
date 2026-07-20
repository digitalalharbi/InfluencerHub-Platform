<?php
namespace App\Domain\Tenancy\Support;
use Closure;
use Illuminate\Http\Request;
/** يضبط سياق المستأجر من عضوية المستخدم/رأس الطلب بعد المصادقة. system_admin يتجاوز. */
class SetTenantContext {
    public function handle(Request $request, Closure $next) {
        // نقرأ من حارس sanctum أولاً ثم الافتراضي (الافتراضي قد يعيد null تحت token auth).
        $user = $request->user('sanctum') ?? $request->user();
        if ($user) {
            if ($user->is_system_admin) {
                TenantContext::bypass(true);
                // كل تجاوز إداري يُسجَّل في سجل التدقيق (يتطلب مستخدمًا مصادقًا بصلاحية is_system_admin).
                \App\Domain\Audit\Services\AuditLogger::log('tenant.bypass.system_admin', null, ['path' => $request->path()], null, $user->id);
            } else {
                // البحث عن العضوية يتجاوز TenantScope (نحن نحدّد المستأجر منها).
                // يُدعم اختيار المؤسسة عبر رأس X-Organization-Id (ضمن عضويات المستخدم فقط).
                $q = $user->memberships()->withoutGlobalScopes()->where('status', 'active');
                if ($orgId = $request->header('X-Organization-Id')) {
                    $q->where('organization_id', (int) $orgId);
                }
                $m = $q->first();
                if ($m) { TenantContext::set($m->tenant_id, $m->organization_id, $m->workspace_id); }
            }
        }
        return $next($request);
    }
}
