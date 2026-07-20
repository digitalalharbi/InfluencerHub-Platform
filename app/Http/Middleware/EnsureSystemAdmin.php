<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * بوابة مدير النظام (SaaS) — الوصول لمالك المنصّة فقط (is_system_admin).
 * عبر المستأجرين (cross-tenant) للإشراف فقط؛ fail-closed.
 */
class EnsureSystemAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) return redirect('/login');
        abort_unless((bool) $user->is_system_admin, 403, 'الوصول مقصور على مدير النظام.');
        return $next($request);
    }
}
