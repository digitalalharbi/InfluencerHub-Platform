<?php
namespace App\Http\Middleware;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * بوابة المبدع: تتأكد أن المستخدم المسجَّل له ملف مبدع، وتضبط سياق المستأجر منه،
 * وتشارك $creator. المبدع يصل لملفه فقط (منع IDOR على مستوى الحل نفسه).
 */
class EnsureCreator {
    public function handle(Request $request, Closure $next) {
        $user = $request->user();
        if (! $user) return redirect('/creator/login');

        // البحث يسبق معرفة المستأجر. (السياق هنا يُضبط في آخر الوسيط فلم يكن
        // يُمسح، لكن التجاوز اليدوي يبقى ثغرة لو رمى ما بعده.)
        $creator = TenantContext::withBypass(fn () => Creator::where('user_id', $user->id)->first());
        if (! $creator) { abort(403, 'لا يوجد ملف مبدع مرتبط بحسابك.'); }

        // creator_portal.enabled: منع الدخول إن لم تُفعَّل الميزة (لا نكتفي بإخفاء الرابط)
        $ent = app(\App\Domain\Creators\Services\CreatorEntitlementService::class);
        $org = $ent->orgForTenant($creator->tenant_id);
        if ($org && ! $ent->portalEnabled($org)) {
            abort(403, 'بوابة المبدع غير مفعّلة في خطة الوكالة. تواصل مع الوكالة.');
        }

        TenantContext::set($creator->tenant_id);
        $request->attributes->set('creator', $creator);
        view()->share('creator', $creator);
        return $next($request);
    }
}
