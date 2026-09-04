<?php

namespace App\Http\Controllers\Inertia;

use App\Domain\Communications\Services\NotificationService;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Concerns\ManagesAccountSecurity;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * حساب موظّف الوكالة — أمان الحساب فقط (إشعارات/كلمة مرور/جلسات).
 *
 * متاح لكل الأدوار بلا استثناء: `/app/settings` بوابة إدارية تعرض الاشتراك
 * والحقوق، أما تغيير كلمة المرور وإنهاء جلسة مسروقة فحقّ كل مستخدم.
 * قبل هذا لم يكن لموظّفي الوكالة أي سبيل إلى ذلك.
 */
class AccountController extends Controller
{
    use ManagesAccountSecurity;

    protected function securityTenantId(Request $r): int
    {
        return (int) TenantContext::tenantId();
    }

    public function index(Request $r, NotificationService $svc): Response
    {
        return Inertia::render('Account/Index', [
            'user' => [
                'name' => $r->user()->name,
                'email' => $r->user()->email,
            ],
            ...$this->securityPayload($r, $svc),
        ]);
    }

    /** تحديث الاسم المعروض للمستخدم — حقّ كل مستخدم على اسمه. */
    public function updateName(Request $r): RedirectResponse
    {
        $data = $r->validate(['name' => 'required|string|min:2|max:120'], [], ['name' => 'الاسم']);
        $user = $r->user();
        $user->name = trim($data['name']);
        $user->save();

        return back()->with('ok', 'حُدِّث اسمك.');
    }
}
