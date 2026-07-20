<?php
namespace App\Http\Controllers\Auth;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class LoginController extends Controller {
    public function show() { return view('auth.login'); }
    public function login(Request $r) {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($cred, $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
        }
        $r->session()->regenerate();
        AuditLogger::log('auth.login', Auth::user(), [], null, Auth::id());
        return redirect()->intended('/app');
    }
    public function logout(Request $r) {
        AuditLogger::log('auth.logout', Auth::user(), [], null, Auth::id());
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect('/login');
    }
}
