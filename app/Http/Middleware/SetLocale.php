<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حلّ اللغة القانونيّ الوحيد للواجهة (Blade + Inertia).
 *
 * الأولوية: تفضيل المستخدم المُصادَق (users.locale) ← تفضيل الجلسة ← الافتراضي (APP_LOCALE).
 * لا نبدّل الضيوف تلقائيًّا حسب المتصفّح: المنتج عربيّ الأساس، والتبديل صريح عبر مبدّل اللغة
 * (يُحفظ في الجلسة للضيف وفي users.locale للمُصادَق). قرار سلامة: لا قلب لغة الزوّار الجدد.
 *
 * القيَم المدعومة فقط (ar|en)؛ أيّ قيمة أخرى تسقط إلى الافتراضي (لا ثقة بمدخل عشوائيّ).
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $user = $request->user();
        if ($user && in_array($user->locale, self::SUPPORTED, true)) {
            return $user->locale;
        }

        if ($request->hasSession()) {
            $session = $request->session()->get('app_locale');
            if (in_array($session, self::SUPPORTED, true)) {
                return $session;
            }
        }

        $default = (string) config('app.locale', 'ar');

        return in_array($default, self::SUPPORTED, true) ? $default : 'ar';
    }
}
