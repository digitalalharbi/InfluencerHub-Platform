<?php
namespace App\Http\Controllers\Auth;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $r)
    {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (! Auth::validate($cred)) {
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
        }

        $user = User::where('email', $cred['email'])->first();

        $canUseAgencyPortal = $user && ($user->is_system_admin || TenantContext::withBypass(fn () =>
            $user->memberships()->where('status', 'active')->whereIn('role', Role::agencyPortalRoleValues())->exists()
        ));

        if (! $canUseAgencyPortal) {
            throw ValidationException::withMessages(['email' => 'هذا الحساب لا يملك صلاحية دخول بوابة الوكالة. اختر بوابة الحساب المناسبة.']);
        }

        Auth::attempt($cred, $r->boolean('remember'));
        $r->session()->regenerate();
        AuditLogger::log('auth.login', Auth::user(), [], null, Auth::id());

        return redirect()->intended('/app');
    }

    public function logout(Request $r)
    {
        AuditLogger::log('auth.logout', Auth::user(), [], null, Auth::id());
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }
}