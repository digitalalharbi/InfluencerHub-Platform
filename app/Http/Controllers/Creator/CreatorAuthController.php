<?php
namespace App\Http\Controllers\Creator;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Creators\Models\Creator;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class CreatorAuthController extends Controller {
    public function show() { return view('creator.login'); }
    public function login(Request $r) {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($cred, $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
        }
        // يجب أن يكون له ملف مبدع
        $isCreator = TenantContext::withBypass(fn () => Creator::where('user_id', Auth::id())->exists());
        if (! $isCreator) { Auth::logout(); throw ValidationException::withMessages(['email' => 'هذا الحساب ليس حساب مبدع.']); }
        $r->session()->regenerate();
        AuditLogger::log('creator.login', Auth::user(), [], null, Auth::id());
        return redirect()->intended('/creator/dashboard');
    }
    public function logout(Request $r) {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect('/creator/login');
    }
}
