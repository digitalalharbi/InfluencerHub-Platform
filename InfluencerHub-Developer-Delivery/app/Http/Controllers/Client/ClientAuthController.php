<?php
namespace App\Http\Controllers\Client;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\ClientMember;
use App\Domain\Tenancy\Support\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class ClientAuthController extends Controller {
    public function show() { return view('client.login'); }
    public function login(Request $r) {
        $cred = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($cred, $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
        }
        $hasActive = TenantContext::withBypass(fn () => ClientMember::where('user_id', Auth::id())->where('status', 'active')->exists());
        if (! $hasActive) { Auth::logout(); throw ValidationException::withMessages(['email' => 'لا توجد عضوية عميل فعّالة لهذا الحساب.']); }
        $r->session()->regenerate();
        AuditLogger::log('client.login', Auth::user(), [], null, Auth::id());
        return redirect()->intended('/client/dashboard');
    }
    public function logout(Request $r) {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect('/client/login');
    }
    /** تبديل العميل النشِط (ضمن عضويات المستخدم الفعّالة فقط). */
    public function switch(Request $r) {
        $data = $r->validate(['client_id' => 'required|integer']);
        $ok = TenantContext::withBypass(fn () => ClientMember::where('user_id', $r->user()->id)->where('client_id', $data['client_id'])->where('status', 'active')->exists());
        abort_unless($ok, 403); // لا يبدّل لعميل ليس عضوًا فيه
        $r->session()->put('active_client_id', $data['client_id']);
        return redirect('/client/dashboard')->with('ok', 'تم تبديل العميل.');
    }
}
